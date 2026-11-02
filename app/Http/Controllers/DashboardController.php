<?php

namespace App\Http\Controllers;

use App\Models\IdCard;
use App\Models\IdHolder;
use App\Models\IdStatusHistory;
use App\Models\ImportBatch;
use App\Models\ScanLog;
use App\Models\Student;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->validate(['period' => ['nullable', 'in:week,last7,month']])['period'] ?? 'week';
        $end = now()->endOfDay();
        $start = match ($period) {
            'last7' => now()->subDays(6)->startOfDay(),
            'month' => now()->startOfMonth(),
            default => now()->startOfWeek(),
        };
        $days = CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->startOfDay());
        $trend = [];
        foreach ($days as $day) {
            $trend[$day->format('Y-m-d')] = ['label' => $day->format('D'), 'value' => 0];
        }

        $printedHistory = IdStatusHistory::query()
            ->where('new_status', 'printed')
            ->whereBetween('occurred_at', [$start, $end])
            ->get(['occurred_at']);
        foreach ($printedHistory as $history) {
            $key = $history->occurred_at->setTimezone(config('app.timezone'))->format('Y-m-d');
            if (isset($trend[$key])) {
                $trend[$key]['value']++;
            }
        }

        $activity = collect();
        $activity = $activity->merge(ImportBatch::query()->where('status', 'completed')->latest()->limit(4)->get()->map(fn ($batch) => [
            'kind' => 'success', 'icon' => 'upload', 'label' => 'CSV import completed', 'detail' => $batch->original_filename, 'at' => $batch->created_at,
        ]));
        $activity = $activity->merge(IdStatusHistory::query()->with('card.student')->whereIn('new_status', ['printed', 'released'])->latest('occurred_at')->limit(6)->get()->map(fn ($history) => [
            'kind' => $history->new_status->value === 'released' ? 'success' : 'info',
            'icon' => $history->new_status->value === 'released' ? 'release' : 'print',
            'label' => $history->new_status->value === 'released' ? 'ID released' : 'ID marked printed',
            'detail' => $history->card?->student?->full_name ?? 'Student record', 'at' => $history->occurred_at,
        ]));
        $activity = $activity->merge(ScanLog::query()->where('status', 'rejected')->latest('scanned_at')->limit(4)->get()->map(fn ($log) => [
            'kind' => 'warning', 'icon' => 'alert', 'label' => 'Unknown QR scan', 'detail' => 'Verification was rejected', 'at' => $log->scanned_at,
        ]));

        return view('dashboard', [
            'firstName' => str((string) $request->user()->name)->before(' ')->toString() ?: $request->user()->name,
            'totalStudents' => Student::count(),
            'pendingPrinting' => IdCard::whereIn('status', ['pending', 'for_printing'])->count(),
            'readyForRelease' => IdCard::where('status', 'ready_for_release')->count(),
            'releasedToday' => IdCard::whereNotNull('released_at')->whereDate('released_at', today())->count(),
            'trend' => array_values($trend),
            'period' => $period,
            'activity' => $activity->sortByDesc('at')->take(5)->values(),
            'legacyHolderCount' => IdHolder::count(),
        ]);
    }
}
