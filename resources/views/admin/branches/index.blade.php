@extends('layouts.app')
@section('title', 'Branches')
@section('heading', 'Branches')
@section('content')
<div class="panel">
<h2>Existing branches</h2>
<table class="data">
<thead><tr><th>Code</th><th>Name</th><th>City</th><th>State</th><th>Active</th></tr></thead>
<tbody>
@foreach($branches as $branch)
<tr>
<td>{{ $branch->code }}</td>
<td>{{ $branch->name }}</td>
<td>{{ $branch->city }}</td>
<td>{{ $branch->state }}</td>
<td>{{ $branch->is_active ? 'Yes' : 'No' }}</td>
</tr>
@endforeach
</tbody>
</table>
{{ $branches->links() }}
</div>
<div class="panel">
<h2>Add branch</h2>
<form method="POST" action="{{ route('admin.branches.store') }}" class="form-grid">
@csrf
<div><label>Code</label><input name="code" required></div>
<div><label>Name</label><input name="name" required></div>
<div><label>City</label><input name="city"></div>
<div><label>State</label><input name="state"></div>
<div><button class="btn btn-primary" type="submit">Create</button></div>
</form>
</div>
@endsection
