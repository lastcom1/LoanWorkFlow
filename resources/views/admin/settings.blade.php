@extends('layouts.app')
@section('title', 'Settings')
@section('heading', 'Workflow settings')
@section('content')
<div class="panel">
<p class="help">Zoho SMTP credentials are configured only via <code>.env</code> / environment variables — never stored in the database UI.</p>
<form method="POST" action="{{ route('admin.settings.update') }}">
@csrf @method('PUT')
<div class="form-grid">
<div class="full"><label style="display:flex;gap:.4rem;font-weight:500"><input type="checkbox" name="notify_supervisor_on_claim" value="1" @checked($settings['notify_supervisor_on_claim']=='1')> Notify supervisor when tickets are claimed</label></div>
<div class="full"><label style="display:flex;gap:.4rem;font-weight:500"><input type="checkbox" name="allow_duplicate_override_submitter" value="1" @checked($settings['allow_duplicate_override_submitter']=='1')> Allow submitters to acknowledge and proceed on duplicate warnings</label></div>
<div><label>Session timeout (minutes)</label><input type="number" name="session_timeout_minutes" value="{{ $settings['session_timeout_minutes'] }}" min="15" max="480" required></div>
<div><label>Mail from name (display)</label><input name="mail_from_name" value="{{ $settings['mail_from_name'] }}" required></div>
</div>
<button class="btn btn-primary" style="margin-top:1rem" type="submit">Save settings</button>
</form>
</div>
@endsection
