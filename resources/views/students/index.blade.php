@extends('layouts.app')
@section('title', 'Student Records')
@section('heading', 'Student Records')
@section('content')
@php
    $hasStudents = $metrics['total'] > 0;
    $advancedKeys = ['program', 'year_level', 'status', 'card_status'];
    $filtersOpen = collect($advancedKeys)->contains(fn ($key) => filled($filters[$key] ?? null));
    $formatSemester = static function ($enrollment): string {
        $code = $enrollment?->academicTerm?->code;
        $name = $enrollment?->academicTerm?->name ?? $enrollment?->semester;
        return match ($code) {
            'summer' => '0 · Summer',
            'first_semester' => '1 · First Semester',
            'second_semester' => '2 · Second Semester',
            default => $name ?: '-',
        };
    };
@endphp

<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Student Records</h1>
        <p class="page-subtitle">Official active-term student roster managed via CSV enrollment imports.</p>
    </div>
    @if($hasStudents)
        <div class="flex flex-wrap items-center gap-2">
            <a class="btn secondary" href="{{ route('imports.students.template') }}">Download CSV Template</a>
            @if(auth()->user()->hasPermission('imports.manage'))
                <a class="btn primary" href="{{ route('imports.students.create') }}">Import Students (CSV)</a>
            @endif
        </div>
    @endif
</div>

<section aria-label="Student registry summary" class="metric-strip mb-5 grid-cols-1 divide-y divide-[#E2E6ED] sm:grid-cols-3 sm:divide-y-0 sm:divide-x">
    <div class="metric-cell">
        <span class="kpi-label">Total Students</span>
        <p class="kpi-value">{{ number_format($metrics['total']) }}</p>
        <p class="kpi-meta">Official active-term records</p>
    </div>
    <div class="metric-cell">
        <span class="kpi-label">Students with ID Records</span>
        <p class="kpi-value">{{ number_format($metrics['with_cards']) }}</p>
        <p class="kpi-meta">Linked to an ID issuance record</p>
    </div>
    <div class="metric-cell">
        <span class="kpi-label">Active Students</span>
        <p class="kpi-value">{{ number_format($metrics['active']) }}</p>
        <p class="kpi-meta">Currently marked active</p>
    </div>
</section>

@if(!$hasStudents)
    <section aria-labelledby="registry-onboarding-title" class="surface mb-6 flex flex-wrap items-center justify-between gap-4 p-6">
        <div class="flex items-start gap-4">
            <span class="icon-well !h-11 !w-11 !rounded-xl" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 16V4m0 0-4 4m4-4 4 4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            </span>
            <div>
                <h2 id="registry-onboarding-title" class="text-base font-semibold text-[#172033]">Import your first student records</h2>
                <p class="mt-0.5 text-sm text-[#64748B]">Upload an official PhilCST CSV enrollment file to populate the active-term roster.</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <a class="btn secondary" href="{{ route('imports.students.template') }}">Download Template</a>
            @if(auth()->user()->hasPermission('imports.manage'))
                <a class="btn primary" href="{{ route('imports.students.create') }}">Import CSV</a>
            @endif
        </div>
    </section>
@endif

<section class="surface overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E6EF] px-5 py-4">
        <div>
            <h2 class="text-base font-semibold text-[#172033]">Student Directory</h2>
            <p class="text-xs text-[#64748B]">Semester codes: 0 = Summer, 1 = 1st Sem, 2 = 2nd Sem</p>
        </div>
        <span class="text-xs font-medium text-[#64748B] num">Showing {{ number_format($students->count()) }} of {{ number_format($students->total()) }}</span>
    </div>

    @if($hasStudents)
        <form method="GET" id="registryFilters" class="border-b border-[#E2E6EF] bg-[#F8F9FC] px-5 py-3.5">
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="flex-1 min-w-[240px]">
                    <label class="sr-only" for="studentSearchInput">Search students</label>
                    <input id="studentSearchInput" name="search" type="search" value="{{ $filters['search'] ?? '' }}"
                        placeholder="Search by student number or name" aria-label="Search by student number or name">
                </div>
                <button class="btn secondary" type="button" id="filterToggle" aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}" aria-controls="advancedFilters">
                    <span id="filterToggleText">{{ $filtersOpen ? 'Hide Filters' : 'Filters' }}</span>
                </button>
                <button class="btn primary" type="submit">Search</button>
            </div>

            <div id="advancedFilters" class="mt-3.5 grid grid-cols-1 gap-3.5 border-t border-[#E2E6EF] pt-3.5 sm:grid-cols-2 lg:grid-cols-5" @if(!$filtersOpen) hidden @endif>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-medium text-[#475569]">Program</span>
                    <select name="program">
                        <option value="">All programs</option>
                        @foreach($programs as $program)
                            <option value="{{ $program->id }}" @selected(($filters['program'] ?? null) == $program->id)>{{ $program->code }} &middot; {{ $program->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-medium text-[#475569]">Year Level</span>
                    <input name="year_level" type="text" value="{{ $filters['year_level'] ?? '' }}" placeholder="e.g. 1st Year">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-medium text-[#475569]">Student Status</span>
                    <select name="status">
                        <option value="">All statuses</option>
                        @foreach(['active','inactive','graduated','withdrawn'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->title() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-medium text-[#475569]">ID Card Status</span>
                    <select name="card_status">
                        <option value="">All card statuses</option>
                        @foreach(['pending','for_printing','printed','ready_for_release','released','on_hold','revoked'] as $status)
                            <option value="{{ $status }}" @selected(($filters['card_status'] ?? '') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button class="btn primary flex-1" type="submit">Apply Filters</button>
                    <a class="btn secondary" href="{{ route('students.index') }}">Reset</a>
                </div>
            </div>
        </form>

        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student Number</th>
                        <th>Full Name</th>
                        <th>Program</th>
                        <th>Year Level</th>
                        <th>Academic Year</th>
                        <th>Semester</th>
                        <th>ID Status</th>
                        <th class="text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($students as $student)
                        @php
                            $enrollment = $student->displayEnrollment;
                            $cardStatus = $student->latestCard?->status;
                            $initials = strtoupper(substr($student->first_name, 0, 1).substr($student->last_name, 0, 1));
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap"><span class="id-chip">{{ $student->student_number }}</span></td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#F5F0FF] text-xs font-semibold text-[#5B21B6]" aria-hidden="true">{{ $initials }}</span>
                                    <div class="min-w-0">
                                        <a href="{{ route('students.show', $student) }}" class="font-medium text-[#101828] hover:text-[#5B21B6] hover:underline">
                                            {{ $student->full_name }}
                                        </a>
                                        @if($enrollment?->program?->name)
                                            <span class="block truncate text-xs text-[#667085]">{{ $enrollment->program->name }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="font-medium text-[#344054]">{{ $enrollment?->program?->code ?? '-' }}</td>
                            <td class="whitespace-nowrap text-[#475467] num">{{ $enrollment?->year_level ?? '-' }}</td>
                            <td class="whitespace-nowrap text-[#475467] num">{{ $enrollment?->academicTerm?->academicYear?->name ?? $enrollment?->academicYear?->name ?? '-' }}</td>
                            <td class="whitespace-nowrap text-[#475467] num">{{ $formatSemester($enrollment) }}</td>
                            <td>
                                @if($cardStatus)
                                    <span class="badge status-{{ $cardStatus->value }}">{{ str($cardStatus->value)->replace('_',' ')->title() }}</span>
                                @else
                                    <span class="badge status-neutral">No ID record</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a class="text-[13px] font-medium text-[#5B21B6] hover:text-[#4C1D95] hover:underline" href="{{ route('students.show', $student) }}">View details</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center">
                                <p class="text-sm font-semibold text-[#172033]">No students match your filters</p>
                                <p class="mt-1 text-xs text-[#64748B]">Try clearing a filter or searching by a different name or student number.</p>
                                <a class="btn secondary btn-sm mt-4" href="{{ route('students.index') }}">Reset filters</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($students->hasPages())
            <div class="pagination-bar">
                <span class="num">Showing {{ $students->firstItem() }}&ndash;{{ $students->lastItem() }} of {{ number_format($students->total()) }}</span>
                <div class="flex items-center gap-2">
                    @if($students->onFirstPage())
                        <span class="btn secondary btn-sm opacity-45 pointer-events-none">Previous</span>
                    @else
                        <a class="btn secondary btn-sm" href="{{ $students->previousPageUrl() }}">Previous</a>
                    @endif
                    @if($students->hasMorePages())
                        <a class="btn secondary btn-sm" href="{{ $students->nextPageUrl() }}">Next</a>
                    @else
                        <span class="btn secondary btn-sm opacity-45 pointer-events-none">Next</span>
                    @endif
                </div>
            </div>
        @endif
    @else
        <div class="px-5 py-12 text-center text-sm text-[#64748B]">
            <p>Student search and filters will be available after the first CSV import.</p>
        </div>
    @endif
</section>
@endsection

@push('scripts')
<script>
(() => {
    const toggle = document.getElementById('filterToggle');
    const panel = document.getElementById('advancedFilters');
    const label = document.getElementById('filterToggleText');
    toggle?.addEventListener('click', () => {
        const open = panel.hasAttribute('hidden');
        panel.toggleAttribute('hidden', !open);
        toggle.setAttribute('aria-expanded', String(open));
        label.textContent = open ? 'Hide Filters' : 'Filters';
    });
})();
</script>
@endpush
