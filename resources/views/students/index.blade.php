@extends('layouts.app')
@section('title', 'Student Registry')
@section('heading', 'Student Registry')
@section('content')
@php
    $hasStudents = $metrics['total'] > 0;
    $advancedKeys = ['program', 'academic_year', 'academic_term', 'year_level', 'section', 'status', 'card_status'];
    $filtersOpen = collect($advancedKeys)->contains(fn ($key) => filled($filters[$key] ?? null));
@endphp

<header class="page-header registry-page-header">
    <div>
        <p class="eyebrow">Student Management</p>
        <h1>Student Registry</h1>
        <p class="page-description">Manage official student records and ID information.</p>
    </div>
    @if($hasStudents)
        <div class="page-actions">
            <a class="btn secondary" href="{{ route('imports.students.template') }}">Download CSV Template</a>
            @if(auth()->user()->hasPermission('imports.manage'))
                <a class="btn primary" href="{{ route('imports.students.create') }}">Import Students (CSV)</a>
            @endif
        </div>
    @endif
</header>

<section class="registry-metrics" aria-label="Student registry summary">
    <article class="registry-metric">
        <span class="registry-metric-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
        <div><span>Total Students</span><strong>{{ number_format($metrics['total']) }}</strong><small>Official student records</small></div>
    </article>
    <article class="registry-metric">
        <span class="registry-metric-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="9" r="3"/><path d="M8 16h8"/></svg></span>
        <div><span>Students with ID Records</span><strong>{{ number_format($metrics['with_cards']) }}</strong><small>Linked to an ID issuance record</small></div>
    </article>
    <article class="registry-metric">
        <span class="registry-metric-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg></span>
        <div><span>Active Students</span><strong>{{ number_format($metrics['active']) }}</strong><small>Currently marked active</small></div>
    </article>
</section>

@if(!$hasStudents)
    <section class="registry-onboarding" aria-labelledby="registry-onboarding-title">
        <span class="onboarding-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 17V5a2 2 0 0 1 2-2h8l6 6v8a2 2 0 0 1-2 2h-2"/><path d="M14 3v6h6M3 14v7m0 0 3-3m-3 3-3-3"/></svg></span>
        <div><h2 id="registry-onboarding-title">Import your first student records</h2><p>Upload an official PhilCST CSV file to populate the registry.</p></div>
        <div class="onboarding-actions">
            @if(auth()->user()->hasPermission('imports.manage'))<a class="btn primary" href="{{ route('imports.students.create') }}">Import CSV</a>@endif
            <a class="btn secondary" href="{{ route('imports.students.template') }}">Download Template</a>
        </div>
    </section>
@endif

<section class="panel registry-directory">
    <div class="panel-heading registry-heading">
        <div><h2>Student Directory</h2><p>Search and manage official records.</p></div>
        <span class="registry-record-count">{{ number_format($students->total()) }} {{ $students->total() === 1 ? 'record' : 'records' }}</span>
    </div>

    @if($hasStudents)
        <form method="GET" class="registry-filters" id="registryFilters">
            <div class="registry-filter-primary">
                <label class="filter-search"><span class="sr-only">Search students</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by student number or name" aria-label="Search by student number or name"></label>
                <button class="btn secondary filter-toggle" type="button" id="filterToggle" aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}" aria-controls="advancedFilters"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10m-7 6h4"/></svg><span id="filterToggleText">{{ $filtersOpen ? 'Hide Filters' : 'Filters' }}</span></button>
            </div>
            <div class="registry-filter-advanced" id="advancedFilters" @if(!$filtersOpen) hidden @endif>
                <label><span>Program</span><select name="program"><option value="">All programs</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program'] ?? null) == $program->id)>{{ $program->code }} · {{ $program->name }}</option>@endforeach</select></label>
                <label><span>Academic Year</span><select name="academic_year"><option value="">All academic years</option>@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year'] ?? null) == $year->id)>{{ $year->name }}</option>@endforeach</select></label>
                <label><span>Academic Term</span><select name="academic_term"><option value="">All terms</option>@foreach($academicTerms as $term)<option value="{{ $term->id }}" @selected(($filters['academic_term'] ?? null) == $term->id)>{{ $term->academicYear->name }} · {{ $term->name }}</option>@endforeach</select></label><label><span>Year Level</span><input name="year_level" value="{{ $filters['year_level'] ?? '' }}" placeholder="e.g. 1st Year"></label>
                <label><span>Section</span><input name="section" value="{{ $filters['section'] ?? '' }}" placeholder="Any section"></label>
                <label><span>Student Status</span><select name="status"><option value="">All statuses</option>@foreach(['active','inactive','graduated','withdrawn'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->title() }}</option>@endforeach</select></label>
                <label><span>ID Card Status</span><select name="card_status"><option value="">All card statuses</option>@foreach(['pending','for_printing','printed','ready_for_release','released','on_hold','revoked'] as $status)<option value="{{ $status }}" @selected(($filters['card_status'] ?? '') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></label>
                <div class="registry-filter-actions"><button class="btn primary" type="submit">Apply Filters</button><a class="btn text" href="{{ route('students.index') }}">Reset</a></div>
            </div>
        </form>

        <div class="table-wrap"><table class="registry-table"><thead><tr><th>Student</th><th>Student Number</th><th>Program</th><th>Year / Section</th><th>Academic Term</th><th>ID Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
        @forelse($students as $student)
            @php($cardStatus = $student->latestCard?->status)
            <tr><td><a class="student-link" href="{{ route('students.show', $student) }}"><strong>{{ $student->full_name }}</strong><small>{{ $student->displayEnrollment?->program?->name ?? 'No program linked' }}</small></a></td><td class="mono">{{ $student->student_number }}</td><td>{{ $student->displayEnrollment?->program?->code ?? '—' }}</td><td>{{ collect([$student->displayEnrollment?->year_level, $student->displayEnrollment?->section])->filter()->implode(' / ') ?: '—' }}</td><td>{{ $student->displayEnrollment?->academicTerm ? $student->displayEnrollment->academicTerm->academicYear->name.' · '.$student->displayEnrollment->academicTerm->name : ($student->displayEnrollment?->academicYear?->name ?? '—') }}</td><td>@if($cardStatus)<span class="status-badge status-{{ $cardStatus->value }}">{{ str($cardStatus->value)->replace('_',' ')->title() }}</span>@else<span class="status-badge status-neutral">No ID record</span>@endif</td><td><a class="view-action" href="{{ route('students.show', $student) }}">View details<span aria-hidden="true">→</span></a></td></tr>
        @empty
            <tr><td colspan="7"><div class="registry-empty"><strong>No students match your filters</strong><p>Try clearing a filter or searching by a different name or student number.</p><a class="btn secondary" href="{{ route('students.index') }}">Reset filters</a></div></td></tr>
        @endforelse
        </tbody></table></div>
        @if($students->hasPages())<div class="pagination"><span>Showing {{ $students->firstItem() }}–{{ $students->lastItem() }} of {{ $students->total() }}</span><div>@if($students->onFirstPage())<span>Previous</span>@else<a href="{{ $students->previousPageUrl() }}">Previous</a>@endif @if($students->hasMorePages())<a href="{{ $students->nextPageUrl() }}">Next</a>@else<span>Next</span>@endif</div></div>@endif
    @else
        <div class="registry-directory-empty"><p>Student search and filters will be available after the first CSV import.</p></div>
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