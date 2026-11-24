@extends('layouts.app')
@section('title', 'Reports')
@section('content')
@php
    $currentReportType = $filters['report_type'] ?? 'enrolled';
    $reportTabs = [
        'enrolled' => 'Enrolled',
        'printed_cards' => 'Printed',
        'released_cards' => 'Released',
        'printed_not_released' => 'Remaining in Office',
        'without_printed' => 'Without Printed ID',
        'without_released' => 'Without Released ID',
    ];
@endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Operational Reports</h1>
        <p class="page-subtitle">
            {{ $activeTerm ? 'AY '.$activeTerm->academicYear->name.' · '.$activeTerm->name : 'No active academic term' }}
            &middot; Physical card counts remain separate from unique enrolled students.
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2.5">
        <a class="btn secondary" href="{{ route('reports.print', $filters) }}" target="_blank" rel="noopener">Print View</a>
        <a class="btn primary" href="{{ route('reports.export', $filters) }}">Export CSV</a>
    </div>
</div>

{{-- Unified 4-column summary metric band --}}
<section aria-label="Operational report summary" class="metric-strip mb-5 grid-cols-1 divide-y divide-[#E2E6ED] sm:grid-cols-2 sm:divide-y-0 sm:divide-x xl:grid-cols-4">
    <div class="metric-cell">
        <span class="kpi-label">Total Enrolled</span>
        <p class="kpi-value">{{ number_format($summary['enrolled_students']) }}</p>
        <p class="kpi-meta">Active-term enrolled students</p>
    </div>
    <div class="metric-cell">
        <span class="kpi-label">Total Printed</span>
        <p class="kpi-value">{{ number_format($summary['printed_cards']) }}</p>
        <p class="kpi-meta num">{{ number_format($summary['unique_students_printed']) }} unique students</p>
    </div>
    <div class="metric-cell">
        <span class="kpi-label">Total Released</span>
        <p class="kpi-value">{{ number_format($summary['released_cards']) }}</p>
        <p class="kpi-meta num">{{ number_format($summary['unique_students_released']) }} unique students</p>
    </div>
    <div class="metric-cell">
        <div class="flex items-baseline justify-between gap-3">
            <div>
                <span class="kpi-label">Remaining in Office</span>
                <p class="kpi-value">{{ number_format($summary['printed_not_released']) }}</p>
            </div>
            <div class="border-l border-[#E2E6ED] pl-3.5 text-right">
                <span class="kpi-label">Without ID</span>
                <p class="kpi-value">{{ number_format($summary['students_without_printed']) }}</p>
            </div>
        </div>
        <p class="kpi-meta num">Printed &minus; Released &middot; {{ number_format($summary['students_without_released']) }} unreleased</p>
    </div>
</section>

<section class="surface overflow-hidden">
    {{-- Dataset tabs --}}
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E6EF] px-5 pt-3">
        <nav class="flex flex-wrap items-center gap-1" aria-label="Report dataset">
            @foreach($reportTabs as $type => $label)
                <a href="{{ route('reports.index', array_merge($filters, ['report_type' => $type])) }}"
                    class="border-b-2 px-3 pb-3 pt-1.5 text-[13.5px] font-medium transition-colors {{ $currentReportType === $type ? 'border-[#3F1B68] text-[#111827] font-semibold' : 'border-transparent text-[#6B7280] hover:text-[#111827]' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>
        <span class="pb-2.5 text-xs font-medium text-[#64748B] num">{{ number_format($records->total()) }} matching {{ $records->total() === 1 ? 'record' : 'records' }}</span>
    </div>

    {{-- Cohesive filter toolbar: Date, Status, and Semester --}}
    <form method="GET" class="border-b border-[#E2E6EF] bg-[#F8F9FC] px-5 py-4">
        <input type="hidden" name="report_type" value="{{ $currentReportType }}">
        @if(filled($filters['search'] ?? null))
            <input type="hidden" name="search" value="{{ $filters['search'] }}">
        @endif
        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-5 items-end">
            <label class="block">
                <span class="mb-1.5 block text-xs font-medium text-[#475569]">Date From</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-medium text-[#475569]">Date To</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-medium text-[#475569]">Status</span>
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach(\App\Enums\CardStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-medium text-[#475569]">Semester</span>
                <select name="semester">
                    <option value="">All semesters</option>
                    <option value="0" @selected((string) ($filters['semester'] ?? '') === '0')>0 &middot; Summer</option>
                    <option value="1" @selected((string) ($filters['semester'] ?? '') === '1')>1 &middot; First Semester</option>
                    <option value="2" @selected((string) ($filters['semester'] ?? '') === '2')>2 &middot; Second Semester</option>
                </select>
            </label>
            <div class="flex items-center gap-2">
                <button class="btn primary flex-1" type="submit">Apply Filters</button>
                <a class="btn secondary" href="{{ route('reports.index') }}">Reset</a>
            </div>
        </div>
    </form>

    @if(!$activeTerm)
        <div class="px-6 py-14 text-center">
            <h3 class="text-base font-semibold text-[#172033]">No active academic term</h3>
            <p class="mt-1 text-sm text-[#64748B]">An administrator must activate a term in Academic Settings before operational reports can run.</p>
        </div>
    @elseif($records->isEmpty())
        <div class="px-6 py-14 text-center">
            <h3 class="text-base font-semibold text-[#172033]">No matching records</h3>
            <p class="mt-1 text-sm text-[#64748B]">No records meet this report and filter combination in the active term.</p>
        </div>
    @else
        <p class="sr-only" id="report-table-hint">The table can be scrolled horizontally on smaller screens.</p>
        <div class="data-table-wrap" role="region" aria-label="Operational report records" aria-describedby="report-table-hint" tabindex="0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student Number</th>
                        <th>Full Name</th>
                        <th>Program</th>
                        <th>Year Level</th>
                        <th>Semester</th>
                        <th>Card / Status</th>
                        <th>Printed</th>
                        <th>Released</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap"><span class="id-chip">{{ $row['student_number'] }}</span></td>
                            <td class="font-medium text-[#101828]">{{ $row['student_name'] }}</td>
                            <td class="font-medium text-[#344054]">{{ $row['program'] ?: '-' }}</td>
                            <td class="whitespace-nowrap text-[#475467] num">{{ $row['year_level'] ?: '-' }}</td>
                            <td class="whitespace-nowrap text-[#475467] num">{{ $row['semester'] ?: '-' }}</td>
                            <td class="whitespace-nowrap">
                                @if($row['card_id'])
                                    <span class="id-chip">#{{ $row['card_id'] }}</span>
                                    <span class="badge status-{{ $row['status'] }} ml-1.5">{{ str($row['status'])->replace('_', ' ')->title() }}</span>
                                @else
                                    <span class="text-xs text-[#98A2B3]">None</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-[13px] text-[#475467] num">{{ $row['printed_at'] ?: 'Not printed' }}</td>
                            <td class="whitespace-nowrap text-[13px] text-[#475467] num">{{ $row['released_at'] ?: 'Not released' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
            <div class="pagination-bar">
                {{ $records->links() }}
            </div>
        @endif
    @endif
</section>
@endsection
