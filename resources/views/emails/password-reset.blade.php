<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>NNMFB Password Reset</title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:'Segoe UI',Tahoma,sans-serif;color:#0b1f3a;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:24px 12px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #d9e0e8;">
<tr>
<td style="background:#0b1f3a;padding:18px 24px;color:#fff;">
<div style="font-size:18px;font-weight:700;">Nigerian Navy Microfinance Bank</div>
<div style="color:#e6c86a;font-size:13px;margin-top:4px;">Transfer &amp; Reversal Complaint Portal</div>
</td>
</tr>
<tr>
<td style="padding:24px;">
<h2 style="margin:0 0 12px;font-size:20px;">Password reset</h2>
<p>Hello {{ $recipient->name }},</p>
<p>We received a request to reset the password for your NNMFB portal account ({{ $recipient->email }}).</p>
<p style="margin:24px 0;">
<a href="{{ $resetUrl }}" style="display:inline-block;background:#0b1f3a;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:600;">
Reset password
</a>
</p>
<p style="font-size:13px;color:#5c6b7a;">This link expires in {{ $expiresMinutes }} minutes. If you did not request a reset, you can ignore this email — your password will stay unchanged.</p>
<p style="font-size:13px;color:#5c6b7a;">Never share this link. NNMFB staff will never ask for your password, PIN, CVV, or OTP.</p>
<p style="font-size:13px;"><a href="{{ $portalUrl }}" style="color:#14325c;">Return to portal sign-in</a></p>
</td>
</tr>
<tr>
<td style="background:#f7f5f0;padding:14px 24px;font-size:12px;color:#5c6b7a;">
© {{ date('Y') }} NNMFB · Secure password reset notification
</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>
