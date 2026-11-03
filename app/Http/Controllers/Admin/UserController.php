<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserAccountService;
use App\Services\UserOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private UserOnboardingService $onboarding,
        private UserAccountService $accounts,
    ) {}

    public function index()
    {
        $users = User::query()->with(['roles', 'branch'])->orderBy('name')->paginate(25);

        return view('admin.users.index', compact('users'));
    }

    public function create(Request $request)
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();
        $roles = $request->user()->assignableRoles();

        return view('admin.users.create', compact('branches', 'roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'staff_id' => ['nullable', 'string', 'max:64', 'unique:users,staff_id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
            'send_welcome' => ['nullable', 'boolean'],
        ]);

        $this->assertCanAssignRole($request->user(), $data['role']);

        $plainPassword = ! empty($data['password'])
            ? $data['password']
            : $this->onboarding->generateTemporaryPassword();

        $active = $request->boolean('is_active');

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'staff_id' => $data['staff_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($plainPassword),
            'is_active' => $active,
            'is_demo' => false,
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        $user->assignRole($data['role']);

        if (! $active) {
            $this->accounts->revokeAccess($user);
        }

        $this->audit->log('user_created', $request->user(), $user, null, null, [
            'email' => $user->email,
            'role' => $data['role'],
            'must_change_password' => true,
            'is_active' => $user->is_active,
        ]);

        $sendWelcome = $request->boolean('send_welcome');
        $mailOk = true;
        if ($sendWelcome) {
            $mailOk = $this->onboarding->sendWelcomeEmail($user, $plainPassword, $request->user());
        }

        $message = 'User created.';
        if ($sendWelcome) {
            $message .= $mailOk
                ? ' Welcome email with temporary password was sent.'
                : ' Welcome email could not be sent — share the temporary password securely out-of-band and ask them to change it on first login. The failure is in the application log.';
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }

    public function edit(Request $request, User $user)
    {
        $this->assertCanManage($request->user(), $user);

        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();
        $roles = $request->user()->rolesAssignableTo($user);
        $roleLocked = $request->user()->isViewOnlyAdmin() && $user->hasRole(User::ROLE_ADMIN);
        $canDeactivate = $request->user()->canDeactivateUser($user);

        return view('admin.users.edit', compact('user', 'branches', 'roles', 'roleLocked', 'canDeactivate'));
    }

    public function update(Request $request, User $user)
    {
        $actor = $request->user();
        $this->assertCanManage($actor, $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'staff_id' => ['nullable', 'string', 'max:64', Rule::unique('users', 'staff_id')->ignore($user->id)],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->assertCanAssignRole($actor, $data['role'], $user);

        $active = $request->boolean('is_active');
        if (! $active && ! $actor->canDeactivateUser($user)) {
            return back()->withInput()->withErrors([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        $before = $user->only(['name', 'email', 'staff_id', 'branch_id', 'phone', 'is_active']);
        $wasActive = (bool) $user->is_active;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'staff_id' => $data['staff_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $active,
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->must_change_password = true;
            $user->password_changed_at = null;
            $user->remember_token = Str::random(60);
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        if ($wasActive && ! $user->is_active) {
            $this->accounts->revokeAccess($user);
        }

        $this->audit->log('user_updated', $actor, $user, null, $before, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $data['role'],
            'is_active' => $user->is_active,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function deactivate(Request $request, User $user)
    {
        $actor = $request->user();

        if (! $actor->canDeactivateUser($user)) {
            abort(403, 'You cannot deactivate this account.');
        }

        $this->accounts->deactivate($user, $actor);

        return back()->with('success', $user->name.' has been deactivated and signed out. They cannot sign in until an administrator reactivates the account.');
    }

    public function activate(Request $request, User $user)
    {
        $actor = $request->user();
        $this->assertCanManage($actor, $user);

        $this->accounts->activate($user, $actor);

        return back()->with('success', $user->name.' has been activated.');
    }

    /**
     * Reset to a new temporary password and resend the welcome email.
     */
    public function resendWelcome(Request $request, User $user)
    {
        $actor = $request->user();

        if (! $actor->canResendWelcomeTo($user)) {
            abort(403, 'You cannot resend a welcome email to this account.');
        }

        $plain = $this->onboarding->assignTemporaryPassword($user);
        $this->accounts->deleteSessions($user);
        $mailOk = $this->onboarding->sendWelcomeEmail($user->fresh(['roles', 'branch']), $plain, $actor);

        $this->audit->log('welcome_resent', $actor, $user, null, null, [
            'email' => $user->email,
            'mail_ok' => $mailOk,
        ]);

        $message = $mailOk
            ? 'Temporary password reset and welcome email sent. They must change it on next sign-in.'
            : 'Temporary password was reset, but the email could not be sent. Share credentials securely out-of-band. The failure is in the application log.';

        return back()->with('success', $message);
    }

    private function assertCanManage(User $actor, User $target): void
    {
        if (! $actor->canManageUser($target)) {
            abort(403, 'Admins cannot edit, activate, or deactivate Super Admins.');
        }
    }

    private function assertCanAssignRole(User $actor, string $role, ?User $target = null): void
    {
        $allowed = $target ? $actor->rolesAssignableTo($target) : $actor->assignableRoles();

        if (! in_array($role, $allowed, true)) {
            abort(403, 'You cannot assign that role.');
        }
    }
}
