<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->hasAnyRole(['admin', 'auditor', 'md_ceo', 'supervisor'])) {
            abort(403);
        }

        $query = AuditLog::query()->with(['actor', 'ticket'])->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->string('action').'%');
        }
        if ($request->filled('ticket_ref')) {
            $query->whereHas('ticket', fn ($q) => $q->where('ticket_ref', 'like', '%'.$request->string('ticket_ref').'%'));
        }

        $logs = $query->paginate(40)->withQueryString();

        return view('auditor.audit-logs', compact('logs'));
    }

    public function export(Request $request)
    {
        if (! $request->user()->hasAnyRole(['admin', 'auditor'])) {
            abort(403);
        }

        $logs = AuditLog::query()->with(['actor', 'ticket'])->latest('created_at')->limit(2000)->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-logs-'.now()->format('Ymd_His').'.csv"',
        ];

        $callback = function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'created_at', 'actor', 'action', 'ticket_ref', 'ip_address']);
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->id,
                    optional($log->created_at)->toDateTimeString(),
                    $log->actor?->email,
                    $log->action,
                    $log->ticket?->ticket_ref,
                    $log->ip_address,
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
