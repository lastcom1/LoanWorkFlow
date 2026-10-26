@extends('layouts.app')
@section('title', 'Users')
@section('heading', 'Users')
@section('actions')
<a class="btn btn-gold" href="{{ route('admin.users.create') }}">Add user</a>
@endsection
@section('content')
<div class="panel">
<table class="data">
<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Branch</th><th>Status</th><th></th></tr></thead>
<tbody>
@foreach($users as $user)
<tr>
<td>{{ $user->name }} @if($user->is_demo)<span class="badge badge-duplicate">DEMO</span>@endif</td>
<td>{{ $user->email }}</td>
<td>{{ $user->roleLabel() }}</td>
<td>{{ $user->branch?->name ?: '—' }}</td>
<td>{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
<td><a class="btn btn-sm btn-outline" href="{{ route('admin.users.edit', $user) }}">Edit</a></td>
</tr>
@endforeach
</tbody>
</table>
{{ $users->links() }}
</div>
@endsection
