@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('content')
@php
    $enrolled = (int) ($summary['enrolled_students'] ?? $totalStudents);
    $printedCards = (int) ($summary['printed_cards'] ?? 0);
    $uniquePrinted = (int) ($summary['unique_students_printed'] ?? 0);
    $releasedCards = (int) ($summary['released_cards'] ?? 0);
    $uniqueReleased = (int) ($summary['unique_students_released'] ?? 0);
    $remainingInOffice = (int) ($summary['printed_not_released'] ?? 0);
    $withoutPrinted = (int) ($summary['students_without_printed'] ?? 0);
    $withoutReleased = (int) ($summary['students_without_released'] ?? 0);

    $printedPct = $enrolled > 0 ? min(100, round(($uniquePrinted / $enrolled) * 100)) : 0;
    $releasedPct = $enrolled > 0 ? min(100, round(($uniqueReleased / $enrolled) * 100)) : 0;

    $trendTotal = collect($trend)->sum('value');
    $trendMax = max(1, (int) collect($trend)->max('value'));
    $periodLabel = match ($period) {
        'last7' => 'Last 7 Days',
        'month' => 'This Month',
        default => 'This Week',
    };
@endphp

{{-- Institutional Campus Header --}}
<section class="dashboard-campus-header mb-5 px-6 py-5">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-[#5B21B6]">Philippine College of Science and Technology</p>
            <h1 class="page-title mt-1">Dashboard</h1>
            <p class="page-subtitle">
                @if($activeTerm)
                    Active term overview for <span class="font-medium text-[#101828] num">AY {{ $activeTerm->academicYear?->name }} &middot; {{ $activeTerm->name }}</span>.
                @else
                    Configure an active academic term in Academic Settings to begin operational tracking.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            @if(auth()->user()->hasPermission('imports.manage'))
                <a class="btn secondary" href="{{ route('imports.students.create') }}">Import CSV</a>
            @endif
            @if(auth()->user()->hasPermission('reports.view'))
                <a class="btn secondary" href="{{ route('reports.index') }}">View Reports</a>
            @endif
            @if(auth()->user()->hasPermission('cards.view'))
                <a class="btn primary" href="{{ route('scanner.index') }}">Open Verification</a>
            @endif
        </div>
    </div>
</section>

{{-- Unified 4-Column Hairline Metric Band --}}
<section aria-label="Core issuance metrics" class="metric-strip mb-5 grid-cols-1 divide-y divide-[#E2E6ED] sm:grid-cols-2 sm:divide-y-0 sm:divide-x xl:grid-cols-4">
    <div class="metric-cell">
        <span class="kpi-label">Enrolled Students</span>
        <p class="kpi-value">{{ number_format($enrolled) }}</p>
        <p class="kpi-meta">Official active-term roster</p>
    </div>
    <div class="metric-cell">
        <span class="kpi-label">Printed Physical IDs</span>
        <p class="kpi-value">{{ number_format($printedCards) }}</p>
        <p class="kpi-meta num">{{ number_format($uniquePrinted) }} unique students printed</p>
    </div>
    <div class="metric-cell">
        <span class="kpi-label">Released Physical IDs</span>
        <p class="kpi-value">{{ number_format($releasedCards) }}</p>
        <p class="kpi-meta num">{{ number_format($uniqueReleased) }} unique students &middot; {{ number_format($releasedToday) }} today</p>
    </div>
    <div class="metric-cell">
        <span class="kpi-label">Remaining in Office</span>
        <p class="kpi-value">{{ number_format($remainingInOffice) }}</p>
        <p class="kpi-meta">Printed cards awaiting claim</p>
    </div>
</section>

{{-- Issuance Progress & Queue Summary --}}
<section class="surface mb-5 overflow-hidden" aria-label="Issuance progress and operational queue">
    <div class="grid grid-cols-1 lg:grid-cols-12">
        <div class="p-5 lg:col-span-7">
            <div class="mb-4">
                <h2 class="text-[15px] font-semibold text-[#111827]">Issuance Coverage</h2>
                <p class="text-xs text-[#6B7280]">Unique enrolled students with printed and released physical IDs in the active term.</p>
            </div>

            <div class="space-y-4">
                <div>
                    <div class="mb-1.5 flex items-baseline justify-between text-sm">
                        <span class="font-medium text-[#374151]">Printed Coverage</span>
                        <span class="text-xs font-medium text-[#111827] num">{{ number_format($uniquePrinted) }} / {{ number_format($enrolled) }} <span class="text-[#6B7280]">({{ $printedPct }}%)</span></span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-[#F2F4F7]">
                        <div class="h-full rounded-full bg-[#5B21B6]" style="width: {{ $printedPct }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="mb-1.5 flex items-baseline justify-between text-sm">
                        <span class="font-medium text-[#344054]">Released Coverage</span>
                        <span class="text-xs font-medium text-[#101828] num">{{ number_format($uniqueReleased) }} / {{ number_format($enrolled) }} <span class="text-[#667085]">({{ $releasedPct }}%)</span></span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-[#F2F4F7]">
                        <div class="h-full rounded-full bg-[#12B76A]" style="width: {{ $releasedPct }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 divide-x divide-y divide-[#E2E6ED] border-t border-[#E2E6ED] bg-[#F9FAFB] lg:col-span-5 lg:border-t-0 lg:border-l">
            <div class="p-4">
                <p class="text-xs font-medium text-[#6B7280]">Without Printed ID</p>
                <p class="mt-1 text-xl font-semibold text-[#111827] num">{{ number_format($withoutPrinted) }}</p>
            </div>
            <div class="p-4">
                <p class="text-xs font-medium text-[#6B7280]">Without Released ID</p>
                <p class="mt-1 text-xl font-semibold text-[#111827] num">{{ number_format($withoutReleased) }}</p>
            </div>
            <div class="p-4">
                <p class="text-xs font-medium text-[#6B7280]">Pending Printing</p>
                <p class="mt-1 text-xl font-semibold text-[#111827] num">{{ number_format($pendingPrinting) }}</p>
            </div>
            <div class="p-4">
                <p class="text-xs font-medium text-[#6B7280]">Ready for Release</p>
                <p class="mt-1 text-xl font-semibold text-[#111827] num">{{ number_format($readyForRelease) }}</p>
            </div>
        </div>
    </div>
</section>

{{-- Lower Operational Overview --}}
<section class="grid grid-cols-1 gap-5 lg:grid-cols-12">
    <article class="surface overflow-hidden lg:col-span-7">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E6ED] px-5 py-3.5">
            <div>
                <h2 class="text-[15px] font-semibold text-[#111827]">ID Processing Activity</h2>
                <p class="text-xs text-[#6B7280]">Cards marked printed &middot; {{ $periodLabel }} (<span class="font-medium text-[#111827] num">{{ number_format($trendTotal) }}</span> total)</p>
            </div>
            <form method="GET" class="w-36">
                <label class="sr-only" for="period">Trend period</label>
                <select id="period" name="period" onchange="this.form.submit()" class="min-h-8 py-1 text-xs">
                    <option value="week" @selected($period === 'week')>This Week</option>
                    <option value="last7" @selected($period === 'last7')>Last 7 Days</option>
                    <option value="month" @selected($period === 'month')>This Month</option>
                </select>
            </form>
        </div>

        @if($trendTotal === 0)
            <div class="px-5 py-10 text-center">
                <p class="text-sm font-medium text-[#111827]">No printing activity recorded for {{ strtolower($periodLabel) }}</p>
                <p class="mt-1 text-xs text-[#6B7280]">Daily totals appear here as physical ID cards are confirmed printed.</p>
                @if(auth()->user()->hasPermission('cards.view'))
                    <a href="{{ route('production.index') }}" class="btn secondary btn-sm mt-3.5">Open Printed IDs</a>
                @endif
            </div>
        @else
            <div class="data-table-wrap max-h-80 overflow-y-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th class="w-full">Daily Volume</th>
                            <th class="text-right">Printed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($trend as $day)
                            <tr>
                                <td class="whitespace-nowrap font-medium text-[#374151]">{{ $day['label'] }}</td>
                                <td>
                                    <div class="h-2 w-full overflow-hidden rounded-sm bg-[#EEF0F4]">
                                        <div class="h-full rounded-sm bg-[#3F1B68]" style="width: {{ $day['value'] > 0 ? max(4, round(($day['value'] / $trendMax) * 100)) : 0 }}%"></div>
                                    </div>
                                </td>
                                <td class="text-right font-semibold text-[#111827] num">{{ number_format($day['value']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>

    <article class="surface overflow-hidden lg:col-span-5">
        <div class="flex items-center justify-between gap-3 border-b border-[#E2E6ED] px-5 py-3.5">
            <div>
                <h2 class="text-[15px] font-semibold text-[#111827]">Recent Activity</h2>
                <p class="text-xs text-[#6B7280]">Latest active-term operational events</p>
            </div>
            @if(auth()->user()->hasPermission('audit.view'))
                <a href="{{ route('logs.index') }}" class="text-xs font-medium text-[#3F1B68] hover:underline">View all</a>
            @endif
        </div>

        <div class="divide-y divide-[#EEF0F4]">
            @forelse($activity as $event)
                @php
                    $dotColor = match ($event['kind']) {
                        'success' => 'bg-[#16A34A]',
                        'warning' => 'bg-[#DC2626]',
                        default => 'bg-[#0284C7]',
                    };
                @endphp
                <div class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-[#FAFBFD]">
                    <div class="flex items-start gap-2.5 min-w-0">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $dotColor }}" aria-hidden="true"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-[#111827]">{{ $event['label'] }}</p>
                            <p class="truncate text-xs text-[#6B7280]">{{ $event['detail'] }}</p>
                        </div>
                    </div>
                    <time datetime="{{ $event['at']->toIso8601String() }}" class="shrink-0 text-xs text-[#6B7280] num">
                        {{ $event['at']->setTimezone(config('app.timezone'))->format('g:i A') }}
                    </time>
                </div>
            @empty
                <div class="px-5 py-10 text-center">
                    <p class="text-sm font-medium text-[#111827]">No activity recorded today</p>
                    <p class="mt-1 text-xs text-[#6B7280]">CSV imports, printing confirmations, and ID releases appear here.</p>
                </div>
            @endforelse
        </div>
    </article>
</section>
@endsection
