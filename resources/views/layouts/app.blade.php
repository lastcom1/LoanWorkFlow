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
<script>
(function () {
  function fmt(seconds) {
    var neg = seconds < 0;
    seconds = Math.abs(seconds | 0);
    var h = Math.floor(seconds / 3600);
    var m = Math.floor((seconds % 3600) / 60);
    var s = seconds % 60;
    var out = h > 0
      ? h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0')
      : m + ':' + String(s).padStart(2, '0');
    return (neg ? '-' : '') + out;
  }
  function tick() {
    document.querySelectorAll('[data-sla-live="1"]').forEach(function (el) {
      var claimed = Date.parse(el.getAttribute('data-claimed-at'));
      var due = Date.parse(el.getAttribute('data-due-at'));
      var minutes = parseInt(el.getAttribute('data-minutes') || '0', 10);
      if (!claimed || !due) return;
      var now = Date.now();
      var elapsed = Math.floor((now - claimed) / 1000);
      var remaining = Math.floor((due - now) / 1000);
      var elapsedNode = el.querySelector('.sla-elapsed');
      var remainingNode = el.querySelector('.sla-remaining');
      var labelNode = el.querySelector('.sla-label');
      if (elapsedNode) elapsedNode.textContent = '⏱ ' + fmt(elapsed);
      if (remainingNode) remainingNode.textContent = '· left ' + fmt(remaining);
      el.classList.remove('sla-ok', 'sla-near', 'sla-breached');
      if (remaining < 0) {
        el.classList.add('sla-breached');
        if (labelNode) labelNode.textContent = 'Overdue';
      } else if (minutes > 0 && elapsed / (minutes * 60) >= 0.85) {
        el.classList.add('sla-near');
        if (labelNode) labelNode.textContent = 'Near SLA';
      } else {
        el.classList.add('sla-ok');
        if (labelNode) labelNode.textContent = 'On track';
      }
    });
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
</body>
</html>
