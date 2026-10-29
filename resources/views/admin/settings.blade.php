@extends('layouts.app')
@section('title', 'Settings')
@section('heading', 'Workflow settings')
@section('content')
<div class="panel">
<p class="help">Zoho SMTP / sendmail credentials are configured only via <code>.env</code> — never stored in the database UI.</p>
<form method="POST" action="{{ route('admin.settings.update') }}">
@csrf @method('PUT')
<div class="form-grid">
<div class="full"><label style="display:flex;gap:.4rem;font-weight:500"><input type="checkbox" name="notify_supervisor_on_claim" value="1" @checked($settings['notify_supervisor_on_claim']=='1')> Notify supervisors when tickets are picked up</label></div>
<div class="full"><label style="display:flex;gap:.4rem;font-weight:500"><input type="checkbox" name="allow_duplicate_override_submitter" value="1" @checked($settings['allow_duplicate_override_submitter']=='1')> Allow submitters to acknowledge and proceed on duplicate warnings</label></div>
<div><label>Session timeout (minutes)</label><input type="number" name="session_timeout_minutes" value="{{ $settings['session_timeout_minutes'] }}" min="15" max="480" required></div>
<div><label>Mail from name (display)</label><input name="mail_from_name" value="{{ $settings['mail_from_name'] }}" required></div>

<div class="full"><h2 style="margin:0.5rem 0 0;font-size:1.05rem">Pick-up SLA</h2>
<p class="help" style="margin:0.35rem 0 0">Clock starts when an FT officer picks up a ticket and stops on complete/reject. Default global target is <strong>240 minutes (4 hours)</strong>. Optional per-category overrides fall back to the global value when left blank.</p>
</div>
<div><label>Default SLA (minutes)</label><input type="number" name="claim_sla_minutes" value="{{ $settings['claim_sla_minutes'] }}" min="15" max="10080" required></div>
<div><label>Fund transfer SLA (optional)</label><input type="number" name="claim_sla_minutes_fund_transfer" value="{{ $settings['claim_sla_minutes_fund_transfer'] }}" min="15" max="10080" placeholder="Use default"></div>
<div><label>Reversal complaint SLA (optional)</label><input type="number" name="claim_sla_minutes_reversal_complaint" value="{{ $settings['claim_sla_minutes_reversal_complaint'] }}" min="15" max="10080" placeholder="Use default"></div>
</div>
<button class="btn btn-primary" style="margin-top:1rem" type="submit">Save settings</button>
</form>
</div>
@endsection
