@extends('layouts.app')
@section('title', 'Add user')
@section('heading', 'Add user')
@section('content')
<div class="panel">
<form method="POST" action="{{ route('admin.users.store') }}" class="form-grid">
@csrf
<div><label>Name</label><input name="name" value="{{ old('name') }}" required></div>
<div><label>Email</label><input type="email" name="email" value="{{ old('email') }}" required></div>
<div><label>Staff ID</label><input name="staff_id" value="{{ old('staff_id') }}"></div>
<div><label>Phone</label><input name="phone" value="{{ old('phone') }}"></div>
<div>
<label>Branch</label>
<select name="branch_id">
<option value="">—</option>
@foreach($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id')==$b->id)>{{ $b->name }}</option>@endforeach
</select>
</div>
<div>
<label>Role</label>
<select name="role" required>
@foreach($roles as $role)<option value="{{ $role }}" @selected(old('role')==$role)>{{ $role }}</option>@endforeach
</select>
</div>
<div><label>Temporary password (optional)</label><input type="password" name="password" autocomplete="new-password" placeholder="Leave blank to auto-generate"></div>
<div><label>Confirm temporary password</label><input type="password" name="password_confirmation" autocomplete="new-password"></div>
<div class="full help">New users must change this password on first login. A welcome email includes the login URL, email, and temporary password.</div>
<div class="full"><label style="display:flex;gap:.4rem;align-items:center;font-weight:500"><input type="checkbox" name="send_welcome" value="1" @checked(old('send_welcome', true))> Send welcome email with temporary password</label></div>
<div class="full"><label style="display:flex;gap:.4rem;align-items:center;font-weight:500"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Active</label></div>
<div><button class="btn btn-primary" type="submit">Create</button></div>
</form>
</div>
@endsection
