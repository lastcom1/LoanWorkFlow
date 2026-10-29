@extends('emails._layout')
@section('content')
<h2 style="margin:0 0 12px;font-size:20px;">Welcome to the Transfer Portal</h2>
<p>Hello {{ $user->name }},</p>
<p>An administrator created (or reset) your NNMFB Transfer &amp; Reversal Complaint Portal account. Sign in with the temporary password below, then choose a new password immediately.</p>
<table cellpadding="6" style="font-size:14px;border-collapse:collapse;">
<tr><td><strong>Login URL</strong></td><td><a href="{{ $loginUrl }}">{{ $loginUrl }}</a></td></tr>
<tr><td><strong>Email / username</strong></td><td>{{ $user->email }}</td></tr>
<tr><td><strong>Temporary password</strong></td><td style="font-family:Consolas,monospace;">{{ $temporaryPassword }}</td></tr>
</table>
<p style="margin-top:16px;">You will be required to change this password on first sign-in. Do not share it. Never enter PINs, CVVs, or OTPs in this portal.</p>
@endsection
