@extends('layouts.app')
@section('title', 'Academic Terms')
@section('content')
<header class="page-header">
    <div><p class="eyebrow">Administration</p><h1>Academic Setup</h1><p class="page-description">Manage official programs and the single term used by operational modules.</p></div>
</header>
@if(session('success'))<div class="auth-alert success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="auth-alert" role="alert">{{ $errors->first() }}</div>@endif

<section class="panel import-card">
    <div class="panel-heading"><h2>Programs</h2><p>Use official program codes and names.</p></div>
    <form method="POST" action="{{ route('programs.store') }}" class="mapping-grid">@csrf
        <label><span>Program code</span><input name="code" required maxlength="30" placeholder="BSIT"></label>
        <label><span>Program name</span><input name="name" required maxlength="150" placeholder="Bachelor of Science in Information Technology"></label>
        <div><button class="btn primary">Add Program</button></div>
    </form>
    @if($programs->isNotEmpty())
    <div class="table-wrap"><table><thead><tr><th>Code</th><th>Name</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @foreach($programs as $program)<tr><form method="POST" action="{{ route('programs.update', $program) }}">@csrf @method('PUT')
            <td><strong>{{ $program->code }}</strong></td><td><input name="name" value="{{ $program->name }}" required maxlength="150"></td>
            <td><select name="is_active"><option value="1" @selected($program->is_active)>Active</option><option value="0" @selected(!$program->is_active)>Inactive</option></select></td>
            <td><button class="btn secondary">Save</button></td>
        </form></tr>@endforeach
    </tbody></table></div>
    @endif
</section>

<section class="panel import-card term-setup-panel">
    <div class="panel-heading"><h2>Add academic year</h2></div>
    <form method="POST" action="{{ route('academic-years.store') }}" class="mapping-grid">@csrf
        <label><span>Name</span><input name="name" required maxlength="20" placeholder="2026-2027"></label>
        <label><span>Starts on</span><input type="date" name="starts_on"></label>
        <label><span>Ends on</span><input type="date" name="ends_on"></label>
        <div><button class="btn secondary">Add Academic Year</button></div>
    </form>
</section>

<section class="panel import-card term-setup-panel">
    <div class="panel-heading"><h2>Add academic term</h2><p>Semester codes are fixed: 0 Summer, 1 First Semester, 2 Second Semester.</p></div>
    <form method="POST" action="{{ route('academic-terms.store') }}" class="mapping-grid">@csrf
        <label><span>Academic year</span><select name="academic_year_id" required>@foreach($years as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>@endforeach</select></label>
        <label><span>Semester</span><select name="semester" required>@foreach($semesters as $semester)<option value="{{ $semester->value }}" @selected((string) old('semester') === (string) $semester->value)>{{ $semester->value }} - {{ $semester->label() }}</option>@endforeach</select></label>
        <label><span>Starts on</span><input type="date" name="starts_on" value="{{ old('starts_on') }}"></label>
        <label><span>Ends on</span><input type="date" name="ends_on" value="{{ old('ends_on') }}"></label>
        <label><span>Initial status</span><select name="status"><option value="closed">Closed</option><option value="active">Active</option></select></label>
        <div><button class="btn primary" @disabled($years->isEmpty())>Add Term</button></div>
    </form>
</section>

@foreach($years as $year)
<section class="panel term-setup-panel">
    <div class="panel-heading"><h2>{{ $year->name }}</h2></div>
    <div class="table-wrap"><table><thead><tr><th>Semester</th><th>Dates</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @forelse($year->terms as $term)<tr><form method="POST" action="{{ route('academic-terms.update', $term) }}">@csrf @method('PUT')
            <td><strong>{{ $term->name }}</strong><input type="hidden" name="name" value="{{ $term->name }}"><small>Code: {{ $term->code }}</small></td>
            <td><input aria-label="{{ $term->name }} start date" type="date" name="starts_on" value="{{ $term->starts_on?->format('Y-m-d') }}"> <input aria-label="{{ $term->name }} end date" type="date" name="ends_on" value="{{ $term->ends_on?->format('Y-m-d') }}"></td>
            <td><select aria-label="{{ $term->name }} status" name="status"><option value="active" @selected($term->status === 'active')>Active</option><option value="closed" @selected($term->status === 'closed')>Closed</option></select></td>
            <td><button class="btn secondary">Save</button></td>
        </form></tr>@empty<tr><td colspan="4" class="table-empty">No terms configured for this year.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endforeach
@endsection