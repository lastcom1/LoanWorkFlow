<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function edit()
    {
        $settings = [
            'notify_supervisor_on_claim' => Setting::getValue('notify_supervisor_on_claim', '1'),
            'session_timeout_minutes' => Setting::getValue('session_timeout_minutes', '120'),
            'mail_from_name' => Setting::getValue('mail_from_name', config('mail.from.name')),
            'allow_duplicate_override_submitter' => Setting::getValue('allow_duplicate_override_submitter', '1'),
        ];

        return view('admin.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'notify_supervisor_on_claim' => ['nullable', 'boolean'],
            'session_timeout_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'mail_from_name' => ['required', 'string', 'max:120'],
            'allow_duplicate_override_submitter' => ['nullable', 'boolean'],
        ]);

        foreach ([
            'notify_supervisor_on_claim' => $request->boolean('notify_supervisor_on_claim'),
            'session_timeout_minutes' => $data['session_timeout_minutes'],
            'mail_from_name' => $data['mail_from_name'],
            'allow_duplicate_override_submitter' => $request->boolean('allow_duplicate_override_submitter'),
        ] as $key => $value) {
            Setting::setValue($key, $value, 'workflow');
        }

        $this->audit->log('settings_updated', $request->user(), null, null, null, $data);

        return back()->with('success', 'Settings saved. SMTP credentials remain in .env only.');
    }
}
