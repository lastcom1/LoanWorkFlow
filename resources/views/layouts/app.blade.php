<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — NNMFB Transfer Portal</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('head')
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <img src="{{ asset('images/nnmfb-logo.png') }}" alt="NNMFB logo">
            <strong>Nigerian Navy<br>Microfinance Bank</strong>
        </div>
        <nav>
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('tickets.index') }}" class="{{ request()->routeIs('tickets.*') ? 'active' : '' }}">Tickets</a>
            @if(auth()->user()->hasAnyRole(['submitter','admin']))
                <a href="{{ route('tickets.create') }}">New Ticket</a>
            @endif
            @if(auth()->user()->hasAnyRole(['admin','auditor','md_ceo','supervisor']))
                <a href="{{ route('auditor.audit-logs') }}" class="{{ request()->routeIs('auditor.*') ? 'active' : '' }}">Audit Trail</a>
            @endif
            @if(auth()->user()->hasRole('admin'))
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Users</a>
                <a href="{{ route('admin.branches.index') }}" class="{{ request()->routeIs('admin.branches.*') ? 'active' : '' }}">Branches</a>
                <a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">Settings</a>
            @endif
        </nav>
        <div class="role-chip">
            <div>{{ auth()->user()->name }}</div>
            <div>{{ auth()->user()->roleLabel() }}</div>
            @if(auth()->user()->is_demo)
                <div style="margin-top:0.35rem;opacity:0.9">DEMO ACCOUNT</div>
            @endif
            <form method="POST" action="{{ route('logout') }}" style="margin-top:0.75rem">
                @csrf
                <button class="btn btn-gold btn-sm" type="submit">Sign out</button>
            </form>
        </div>
    </aside>
    <main class="main">
        <div class="topbar">
            <div>
                <h1>@yield('heading', 'Dashboard')</h1>
                <div class="meta">@yield('subheading', 'Transfer & Reversal Complaint Management Portal')</div>
            </div>
            <div class="actions">@yield('actions')</div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul style="margin:0;padding-left:1.1rem">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
