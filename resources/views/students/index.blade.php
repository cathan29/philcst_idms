@extends('layouts.app')
@section('title', 'Student Registry')
@section('heading', 'Student Registry')
@section('content')
<div class="page-header">
    <div><p class="eyebrow">Student Management</p><h2>Student Registry</h2><p class="page-description">Manage, search, and monitor official student records.</p></div>
    <div class="page-actions"><a class="btn secondary" href="{{ route('imports.students.template') }}">Download CSV Template</a>@if(auth()->user()->hasPermission('imports.manage'))<a class="btn primary" href="{{ route('imports.students.create') }}">Import Students (CSV)</a>@endif</div>
</div>

<section class="registry-metrics" aria-label="Student registry summary">
    <div class="registry-metric"><span>Total Registered Students</span><strong>{{ number_format($metrics['total']) }}</strong><small>Official student records</small></div>
    <div class="registry-metric"><span>Students with ID Records</span><strong>{{ number_format($metrics['with_cards']) }}</strong><small>Linked to at least one issuance</small></div>
    <div class="registry-metric"><span>Records Requiring Review</span><strong>{{ number_format($metrics['review']) }}</strong><small>Inactive or without an ID record</small></div>
</section>

@if($metrics['total'] === 0)
    <section class="registry-onboarding">
        <div class="onboarding-icon">CSV</div>
        <div><p class="eyebrow">Get started</p><h3>Import your first student records</h3><p>Upload the school's official CSV file to populate the student registry.</p></div>
        <div class="onboarding-actions">@if(auth()->user()->hasPermission('imports.manage'))<a class="btn primary" href="{{ route('imports.students.create') }}">Import CSV</a>@endif<a class="btn secondary" href="{{ route('imports.students.template') }}">Download Template</a></div>
    </section>
@endif

<section class="panel registry-directory">
    <div class="panel-heading registry-heading"><div><h2>Student directory</h2><p>{{ number_format($students->total()) }} matching {{ $students->total() === 1 ? 'record' : 'records' }}</p></div>@if($metrics['total'] > 0 && auth()->user()->hasPermission('imports.manage'))<a class="text-action" href="{{ route('imports.students.create') }}">Import more students →</a>@endif</div>
    <form method="GET" class="registry-filters">
        <div class="registry-filter-primary">
            <label class="filter-search"><span>Search</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Student number or name" aria-label="Search by student number or name"></label>
            <label><span>Program</span><select name="program"><option value="">All programs</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program'] ?? null) == $program->id)>{{ $program->code }} · {{ $program->name }}</option>@endforeach</select></label>
            <label><span>Academic year</span><select name="academic_year"><option value="">All academic years</option>@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year'] ?? null) == $year->id)>{{ $year->name }}</option>@endforeach</select></label>
            <button class="btn secondary filter-toggle" type="button" id="filterToggle" aria-expanded="{{ collect(['year_level','section','status','card_status'])->intersect(array_keys(request()->query()))->isNotEmpty() ? 'true' : 'false' }}"><span id="filterToggleText">{{ collect(['year_level','section','status','card_status'])->intersect(array_keys(request()->query()))->isNotEmpty() ? 'Hide filters' : 'More filters' }}</span><span aria-hidden="true">⌄</span></button>
        </div>
        <div class="registry-filter-advanced" id="advancedFilters" @if(!collect(['year_level','section','status','card_status'])->intersect(array_keys(request()->query()))->isNotEmpty()) hidden @endif>
            <label><span>Year level</span><input name="year_level" value="{{ $filters['year_level'] ?? '' }}" placeholder="e.g. 1st Year"></label>
            <label><span>Section</span><input name="section" value="{{ $filters['section'] ?? '' }}" placeholder="Any section"></label>
            <label><span>Student status</span><select name="status"><option value="">All statuses</option>@foreach(['active','inactive','graduated','withdrawn'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->title() }}</option>@endforeach</select></label>
            <label><span>ID card status</span><select name="card_status"><option value="">All card statuses</option>@foreach(['pending','for_printing','printed','ready_for_release','released','on_hold','revoked'] as $status)<option value="{{ $status }}" @selected(($filters['card_status'] ?? '') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></label>
        </div>
        <div class="registry-filter-actions"><button class="btn primary" type="submit">Apply filters</button><a class="btn text" href="{{ route('students.index') }}">Reset</a></div>
    </form>
    <div class="table-wrap"><table class="registry-table"><thead><tr><th>Student</th><th>Student Number</th><th>Program</th><th>Year / Section</th><th>Academic Year</th><th>ID Status</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>
    @forelse($students as $student)
        @php($cardStatus = $student->latestCard?->status)
        <tr><td><a class="student-link" href="{{ route('students.show', $student) }}"><strong>{{ $student->full_name }}</strong><small>{{ $student->latestEnrollment?->program?->name ?? 'No program linked' }}</small></a></td><td class="mono">{{ $student->student_number }}</td><td>{{ $student->latestEnrollment?->program?->code ?? '—' }}</td><td>{{ collect([$student->latestEnrollment?->year_level, $student->latestEnrollment?->section])->filter()->implode(' / ') ?: '—' }}</td><td>{{ $student->latestEnrollment?->academicYear?->name ?? '—' }}</td><td>@if($cardStatus)<span class="status-badge status-{{ $cardStatus->value }}">{{ str($cardStatus->value)->replace('_',' ')->title() }}</span>@else<span class="status-badge status-review">No ID record</span>@endif</td><td><a class="view-action" href="{{ route('students.show', $student) }}">View details<span aria-hidden="true">→</span></a></td></tr>
    @empty
        <tr><td colspan="7"><div class="registry-empty"><div class="empty-icon">⌕</div><strong>No students match your filters</strong><p>Try clearing a filter or searching by a different name or student number.</p><a class="btn secondary" href="{{ route('students.index') }}">Reset filters</a></div></td></tr>
    @endforelse
    </tbody></table></div>
    @if($students->hasPages())<div class="pagination"><span>Showing {{ $students->firstItem() }}–{{ $students->lastItem() }} of {{ $students->total() }}</span><div>@if($students->onFirstPage())<span>Previous</span>@else<a href="{{ $students->previousPageUrl() }}">Previous</a>@endif @if($students->hasMorePages())<a href="{{ $students->nextPageUrl() }}">Next</a>@else<span>Next</span>@endif</div></div>@endif
</section>
@endsection
@push('scripts')<script>const filterToggle=document.getElementById('filterToggle');const advancedFilters=document.getElementById('advancedFilters');const filterToggleText=document.getElementById('filterToggleText');filterToggle?.addEventListener('click',()=>{const open=advancedFilters.hasAttribute('hidden');advancedFilters.toggleAttribute('hidden',!open);filterToggle.setAttribute('aria-expanded',String(open));filterToggleText.textContent=open?'Hide filters':'More filters'});</script>@endpush
