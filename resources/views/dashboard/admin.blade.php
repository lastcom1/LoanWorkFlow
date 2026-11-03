@extends('layouts.app')

@section('title', auth()->user()->isSuperAdmin() ? 'Super Admin' : 'Admin')
@section('heading', auth()->user()->isSuperAdmin() ? 'Super Admin' : 'Admin')
@section('subheading', auth()->user()->isSuperAdmin()
    ? 'Users, branches, workflow settings, and audit visibility'
    : 'Onboard staff, activate or deactivate accounts, and review tickets')

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
        <a class="btn btn-outline" href="{{ route('tickets.index') }}">View tickets</a>
        @if(auth()->user()->isSuperAdmin())
            <a class="btn btn-outline" href="{{ route('admin.branches.index') }}">Branches</a>
            <a class="btn btn-outline" href="{{ route('admin.settings.edit') }}">Settings</a>
            <a class="btn btn-outline" href="{{ route('auditor.audit-logs') }}">Audit trail</a>
        @endif
    </div>
    <p class="help" style="margin-top:1rem">
        @if(auth()->user()->isViewOnlyAdmin())
            Admins onboard users and turn accounts on or off. They can read tickets but cannot submit, pick up, or update them. Only a Super Admin can assign the Admin or Super Admin role, manage branches and settings, or change a Super Admin.
        @else
            Audit logs are immutable through this application UI. Mail is sent with local sendmail; credentials stay in <code>.env</code> only.
        @endif
    </p>
</div>
@endsection
