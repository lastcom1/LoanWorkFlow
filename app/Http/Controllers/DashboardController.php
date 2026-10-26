<?php

namespace App\Http\Controllers;

use App\Models\TransferTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            return $this->admin($request);
        }
        if ($user->hasRole('md_ceo')) {
            return $this->executive($request);
        }
        if ($user->hasRole('supervisor')) {
            return $this->supervisor($request);
        }
        if ($user->hasRole('ft_officer')) {
            return $this->officer($request);
        }
        if ($user->hasRole('auditor')) {
            return $this->auditor($request);
        }

        return $this->submitter($request);
    }

    private function submitter(Request $request)
    {
        $tickets = TransferTicket::query()
            ->forSubmitter($request->user()->id)
            ->latest()
            ->paginate(15);

        $counts = TransferTicket::query()
            ->forSubmitter($request->user()->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard.submitter', compact('tickets', 'counts'));
    }

    private function officer(Request $request)
    {
        $user = $request->user();

        $queue = TransferTicket::query()
            ->available()
            ->with(['branch', 'submitter'])
            ->latest('submitted_at')
            ->paginate(15, ['*'], 'queue');

        $mine = TransferTicket::query()
            ->forOfficer($user->id)
            ->whereNotIn('status', [TransferTicket::STATUS_COMPLETED, TransferTicket::STATUS_REJECTED])
            ->with(['branch', 'submitter'])
            ->latest('claimed_at')
            ->get();

        $completedToday = TransferTicket::query()
            ->forOfficer($user->id)
            ->whereDate('completed_at', today())
            ->count();

        return view('dashboard.officer', compact('queue', 'mine', 'completedToday'));
    }

    private function supervisor(Request $request)
    {
        $stats = [
            'available' => TransferTicket::query()->available()->count(),
            'in_progress' => TransferTicket::query()->whereIn('status', [
                TransferTicket::STATUS_CLAIMED, TransferTicket::STATUS_IN_PROGRESS, TransferTicket::STATUS_INFO_REQUIRED,
            ])->count(),
            'duplicates' => TransferTicket::query()->where('is_duplicate_flagged', true)->whereNotIn('status', [
                TransferTicket::STATUS_COMPLETED, TransferTicket::STATUS_REJECTED,
            ])->count(),
            'rejected' => TransferTicket::query()->where('status', TransferTicket::STATUS_REJECTED)->count(),
            'completed' => TransferTicket::query()->where('status', TransferTicket::STATUS_COMPLETED)->count(),
        ];

        $workloads = User::role('ft_officer')
            ->where('is_active', true)
            ->withCount([
                'assignedTickets as open_tickets_count' => function ($q) {
                    $q->whereNotIn('status', [
                        TransferTicket::STATUS_COMPLETED,
                        TransferTicket::STATUS_REJECTED,
                    ]);
                },
            ])
            ->get();

        $recent = TransferTicket::query()
            ->with(['branch', 'submitter', 'assignedOfficer'])
            ->latest()
            ->limit(20)
            ->get();

        return view('dashboard.supervisor', compact('stats', 'workloads', 'recent'));
    }

    private function executive(Request $request)
    {
        $stats = [
            'open' => TransferTicket::query()->whereNotIn('status', [
                TransferTicket::STATUS_COMPLETED, TransferTicket::STATUS_REJECTED, TransferTicket::STATUS_DRAFT,
            ])->count(),
            'claimed_today' => TransferTicket::query()->whereDate('claimed_at', today())->count(),
            'completed_today' => TransferTicket::query()->whereDate('completed_at', today())->count(),
            'rejected' => TransferTicket::query()->where('status', TransferTicket::STATUS_REJECTED)->count(),
            'duplicates' => TransferTicket::query()->where('is_duplicate_flagged', true)->count(),
        ];

        $byCategory = TransferTicket::query()
            ->select('category', DB::raw('count(*) as total'))
            ->groupBy('category')
            ->pluck('total', 'category');

        $recentClaims = TransferTicket::query()
            ->whereNotNull('claimed_at')
            ->with(['assignedOfficer', 'branch'])
            ->latest('claimed_at')
            ->limit(15)
            ->get();

        return view('dashboard.executive', compact('stats', 'byCategory', 'recentClaims'));
    }

    private function admin(Request $request)
    {
        $stats = [
            'users' => User::query()->count(),
            'active_users' => User::query()->where('is_active', true)->count(),
            'tickets' => TransferTicket::query()->count(),
            'open_tickets' => TransferTicket::query()->whereNotIn('status', [
                TransferTicket::STATUS_COMPLETED, TransferTicket::STATUS_REJECTED, TransferTicket::STATUS_DRAFT,
            ])->count(),
        ];

        return view('dashboard.admin', compact('stats'));
    }

    private function auditor(Request $request)
    {
        return redirect()->route('auditor.audit-logs');
    }
}
