<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private UserOnboardingService $onboarding,
    ) {}

    public function index()
    {
        $users = User::query()->with(['roles', 'branch'])->orderBy('name')->paginate(25);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();
        $roles = ['admin', 'md_ceo', 'supervisor', 'ft_officer', 'submitter', 'auditor'];

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
            'role' => ['required', Rule::in(['admin', 'md_ceo', 'supervisor', 'ft_officer', 'submitter', 'auditor'])],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
            'send_welcome' => ['nullable', 'boolean'],
        ]);

        $plainPassword = ! empty($data['password'])
            ? $data['password']
            : $this->onboarding->generateTemporaryPassword();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'staff_id' => $data['staff_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($plainPassword),
            'is_active' => $request->boolean('is_active', true),
            'is_demo' => false,
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        $user->assignRole($data['role']);

        $this->audit->log('user_created', $request->user(), $user, null, null, [
            'email' => $user->email,
            'role' => $data['role'],
            'must_change_password' => true,
        ]);

        $mailOk = true;
        if ($request->boolean('send_welcome', true)) {
            $mailOk = $this->onboarding->sendWelcomeEmail($user, $plainPassword, $request->user());
        }

        $message = 'User created.';
        if ($request->boolean('send_welcome', true)) {
            $message .= $mailOk
                ? ' Welcome email with temporary password was sent.'
                : ' Welcome email could not be sent — share the temporary password securely out-of-band and ask them to change it on first login.';
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }

    public function edit(User $user)
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();
        $roles = ['admin', 'md_ceo', 'supervisor', 'ft_officer', 'submitter', 'auditor'];

        return view('admin.users.edit', compact('user', 'branches', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'staff_id' => ['nullable', 'string', 'max:64', Rule::unique('users', 'staff_id')->ignore($user->id)],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', Rule::in(['admin', 'md_ceo', 'supervisor', 'ft_officer', 'submitter', 'auditor'])],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = $user->only(['name', 'email', 'staff_id', 'branch_id', 'phone', 'is_active']);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'staff_id' => $data['staff_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->must_change_password = true;
            $user->password_changed_at = null;
        }

        $user->save();
        $user->syncRoles([$data['role']]);

        $this->audit->log('user_updated', $request->user(), $user, null, $before, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $data['role'],
            'is_active' => $user->is_active,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    /**
     * Reset to a new temporary password and resend the welcome email.
     */
    public function resendWelcome(Request $request, User $user)
    {
        $plain = $this->onboarding->assignTemporaryPassword($user);
        $mailOk = $this->onboarding->sendWelcomeEmail($user->fresh(['roles', 'branch']), $plain, $request->user());

        $this->audit->log('welcome_resent', $request->user(), $user, null, null, [
            'email' => $user->email,
            'mail_ok' => $mailOk,
        ]);

        $message = $mailOk
            ? 'Temporary password reset and welcome email sent.'
            : 'Temporary password was reset, but the email could not be sent. Share credentials securely out-of-band.';

        return back()->with('success', $message);
    }
}
