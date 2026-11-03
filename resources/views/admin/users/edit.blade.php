@extends('layouts.app')
@section('title', 'Edit user')
@section('heading', 'Edit user')
@section('content')
<div class="panel">
<form method="POST" action="{{ route('admin.users.update', $user) }}" class="form-grid">
@csrf @method('PUT')
<div><label>Name</label><input name="name" value="{{ old('name', $user->name) }}" required></div>
<div><label>Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
<div><label>Staff ID</label><input name="staff_id" value="{{ old('staff_id', $user->staff_id) }}"></div>
<div><label>Phone</label><input name="phone" value="{{ old('phone', $user->phone) }}"></div>
<div>
<label>Branch</label>
<select name="branch_id">
<option value="">—</option>
@foreach($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $user->branch_id)==$b->id)>{{ $b->name }}</option>@endforeach
</select>
</div>
<div>
<label>Role</label>
<select name="role" required @disabled($roleLocked)>
@foreach($roles as $role)<option value="{{ $role }}" @selected(old('role', $user->primaryRoleName())===$role)>{{ \App\Models\User::roleOptions()[$role] ?? $role }}</option>@endforeach
</select>
@if($roleLocked)
<input type="hidden" name="role" value="admin">
<p class="help">Only a Super Admin can change an Admin's role.</p>
@endif
</div>
<div><label>New password (optional)</label><input type="password" name="password"></div>
<div><label>Confirm password</label><input type="password" name="password_confirmation"></div>
@if($canDeactivate)
<input type="hidden" name="is_active" value="0">
<div class="full"><label style="display:flex;gap:.4rem;align-items:center;font-weight:500"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ? '1' : '0') == '1')> Active</label></div>
<p class="help">Clearing Active signs the user out, invalidates remember-me, and blocks password reset.</p>
@else
<input type="hidden" name="is_active" value="1">
<div class="full"><p class="help">You cannot deactivate your own account. Ask a Super Admin if this account should be closed.</p></div>
@endif
<div><button class="btn btn-primary" type="submit">Save</button></div>
</form>
</div>
@endsection
