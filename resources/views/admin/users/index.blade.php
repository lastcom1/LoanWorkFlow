@extends('layouts.app')
@section('title', 'Users')
@section('heading', 'Users')
@section('actions')
<a class="btn btn-gold" href="{{ route('admin.users.create') }}">Add user</a>
@endsection
@section('content')
<div class="panel">
<p class="help">Deactivating a user signs them out, clears remember-me, and blocks password reset. Resend welcome issues a new temporary password and requires a change on next sign-in.</p>
<table class="data">
<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Branch</th><th>Status</th><th></th></tr></thead>
<tbody>
@foreach($users as $user)
@php($actor = auth()->user())
<tr>
<td>{{ $user->name }} @if($user->is_demo)<span class="badge badge-duplicate">DEMO</span>@endif @if($user->must_change_password)<span class="badge badge-claimed">Must change password</span>@endif</td>
<td>{{ $user->email }}</td>
<td>{{ $user->roleLabel() }}</td>
<td>{{ $user->branch?->name ?: '—' }}</td>
<td>{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
<td style="white-space:nowrap">
@if($actor->canManageUser($user))
<a class="btn btn-sm btn-outline" href="{{ route('admin.users.edit', $user) }}">Edit</a>
@if($actor->canResendWelcomeTo($user))
<form method="POST" action="{{ route('admin.users.resend-welcome', $user) }}" style="display:inline" onsubmit="return confirm('Reset temporary password and email welcome link to {{ $user->email }}?')">
@csrf
<button class="btn btn-sm btn-gold" type="submit">Resend welcome</button>
</form>
@endif
@if($user->is_active)
@if($actor->canDeactivateUser($user))
<form method="POST" action="{{ route('admin.users.deactivate', $user) }}" style="display:inline" onsubmit="return confirm('Deactivate {{ $user->name }}? They will be signed out and cannot log in.')">
@csrf
<button class="btn btn-sm btn-outline" type="submit">Deactivate</button>
</form>
@endif
@else
<form method="POST" action="{{ route('admin.users.activate', $user) }}" style="display:inline">
@csrf
<button class="btn btn-sm btn-outline" type="submit">Activate</button>
</form>
@endif
@endif
</td>
</tr>
@endforeach
</tbody>
</table>
{{ $users->links() }}
</div>
@endsection
