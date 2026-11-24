<?php

namespace App\Http\Controllers;

use App\Models\IdCard;
use App\Models\IdHolder;
use App\Models\IdStatusHistory;
use App\Models\ImportBatch;
use App\Models\ScanLog;
use App\Models\Student;
use App\Services\ActiveAcademicTerm;
use App\Services\OperationalReportService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, ActiveAcademicTerm $activeTerms, OperationalReportService $reports): View
    {
        $activeTerm = $activeTerms->current();
        $termId = $activeTerm?->id ?? 0;
        $summary = $activeTerm
            ? $reports->summary($activeTerm->id)
            : array_fill_keys([
                'enrolled_students', 'printed_cards', 'released_cards', 'printed_not_released',
                'unique_students_printed', 'students_without_printed', 'unique_students_released', 'students_without_released',
            ], 0);
        $period = $request->validate(['period' => ['nullable', 'in:week,last7,month']])['period'] ?? 'week';
        $end = now()->endOfDay();
        $start = match ($period) {
            'last7' => now()->subDays(6)->startOfDay(),
            'month' => now()->startOfMonth(),
            default => now()->startOfWeek(),
        };

        $trend = [];
        foreach (CarbonPeriod::create($start->copy()->startOfDay(), '1 day', $end->copy()->startOfDay()) as $day) {
            $trend[$day->format('Y-m-d')] = ['label' => $day->format('D'), 'value' => 0];
        }

        $printedHistory = IdStatusHistory::query()
            ->where('new_status', 'printed')
            ->whereBetween('occurred_at', [$start, $end])
            ->whereHas('card', fn ($query) => $query->where('academic_term_id', $termId))
            ->get(['occurred_at']);
        foreach ($printedHistory as $history) {
            $key = $history->occurred_at->setTimezone(config('app.timezone'))->format('Y-m-d');
            if (isset($trend[$key])) {
                $trend[$key]['value']++;
            }
        }

        $activity = collect(ImportBatch::query()
            ->where('status', 'completed')
            ->where('academic_term_id', $termId)
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn ($batch) => [
                'kind' => 'success', 'icon' => 'upload', 'label' => 'CSV import completed',
                'detail' => $batch->original_filename, 'at' => $batch->created_at,
            ])->all());
        $activity = $activity->merge(IdStatusHistory::query()
            ->with('card.student')
            ->whereIn('new_status', ['printed', 'released'])
            ->whereHas('card', fn ($query) => $query->where('academic_term_id', $termId))
            ->latest('occurred_at')->limit(6)->get()->map(fn ($history) => [
                'kind' => $history->new_status->value === 'released' ? 'success' : 'info',
                'icon' => $history->new_status->value === 'released' ? 'release' : 'print',
                'label' => $history->new_status->value === 'released' ? 'ID released' : 'ID marked printed',
                'detail' => $history->card?->student?->full_name ?? 'Student record', 'at' => $history->occurred_at,
            ]));
        $activity = $activity->merge(ScanLog::query()
            ->where('status', 'rejected')
            ->where(fn ($query) => $query->whereNull('id_card_id')->orWhereHas('card', fn ($card) => $card->where('academic_term_id', $termId)))
            ->latest('scanned_at')->limit(4)->get()->map(fn ($log) => [
                'kind' => 'warning', 'icon' => 'alert', 'label' => 'Verification rejected',
                'detail' => 'The submitted identifier could not be verified.', 'at' => $log->scanned_at,
            ]));

        $cards = IdCard::query()->where('academic_term_id', $termId);

        return view('dashboard', [
            'firstName' => str((string) $request->user()->name)->before(' ')->toString() ?: $request->user()->name,
            'activeTerm' => $activeTerm,
            'summary' => $summary,
            'totalStudents' => Student::query()->whereHas('enrollments', fn ($query) => $query->where('academic_term_id', $termId))->count(),
            'pendingPrinting' => (clone $cards)->whereIn('status', ['pending', 'for_printing'])->count(),
            'readyForRelease' => (clone $cards)->where('status', 'ready_for_release')->count(),
            'releasedToday' => (clone $cards)->whereHas('release', fn ($query) => $query->whereDate('released_at', today()))->count(),
            'trend' => array_values($trend), 'period' => $period,
            'activity' => $activity->sortByDesc('at')->take(5)->values(),
            'legacyHolderCount' => IdHolder::count(),
        ]);
    }
}