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
<select name="role" required>
@foreach($roles as $role)<option value="{{ $role }}" @selected(old('role', $user->primaryRoleName())===$role)>{{ $role }}</option>@endforeach
</select>
</div>
<div><label>New password (optional)</label><input type="password" name="password"></div>
<div><label>Confirm password</label><input type="password" name="password_confirmation"></div>
<div class="full"><label style="display:flex;gap:.4rem;align-items:center;font-weight:500"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Active</label></div>
<div><button class="btn btn-primary" type="submit">Save</button></div>
</form>
</div>
@endsection
