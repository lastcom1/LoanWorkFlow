<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(private AuditService $audit) {}

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
            'password' => ['required', 'confirmed', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'staff_id' => $data['staff_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active', true),
            'is_demo' => false,
        ]);

        $user->assignRole($data['role']);

        $this->audit->log('user_created', $request->user(), $user, null, null, [
            'email' => $user->email,
            'role' => $data['role'],
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
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
}
