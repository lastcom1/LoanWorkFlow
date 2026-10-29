@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('heading', 'System Administration')
@section('subheading', 'Users, branches, workflow settings, and audit visibility')

@section('content')
<div class="stats">
    <div class="stat"><div class="label">Users</div><div class="value">{{ $stats['users'] }}</div></div>
    <div class="stat"><div class="label">Active users</div><div class="value">{{ $stats['active_users'] }}</div></div>
    <div class="stat"><div class="label">Tickets</div><div class="value">{{ $stats['tickets'] }}</div></div>
    <div class="stat"><div class="label">Open tickets</div><div class="value">{{ $stats['open_tickets'] }}</div></div>
</div>

<div class="panel">
    <h2>Quick links</h2>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('admin.users.index') }}">Manage users</a>
        <a class="btn btn-outline" href="{{ route('admin.branches.index') }}">Branches</a>
        <a class="btn btn-outline" href="{{ route('admin.settings.edit') }}">Settings</a>
        <a class="btn btn-outline" href="{{ route('auditor.audit-logs') }}">Audit trail</a>
    </div>
    <p class="help" style="margin-top:1rem">Audit logs are immutable through this application UI. SMTP secrets stay in <code>.env</code> only.</p>
</div>
@endsection
