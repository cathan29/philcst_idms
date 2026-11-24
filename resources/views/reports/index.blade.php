@extends('layouts.app')
@section('title', 'Operational Reports')
@section('content')
<header class="page-header production-header">
    <div><p class="eyebrow">ID Monitoring</p><h1>Operational Reports</h1><p class="page-description">Counts and lists use the active academic term and keep physical card totals separate from unique students.</p></div>
</header>

<section class="panel production-queue">
    <div class="panel-heading">
        <div><h2>{{ $activeTerm ? $activeTerm->academicYear->name.'  -  '.$activeTerm->name : 'No active academic term' }}</h2><p>{{ number_format($records->total()) }} matching record{{ $records->total() === 1 ? '' : 's' }}</p></div>
        <div class="report-actions"><a class="btn secondary" href="{{ route('reports.print', $filters) }}" target="_blank" rel="noopener">Print view</a><a class="btn secondary" href="{{ route('reports.export', $filters) }}">Export CSV</a></div>
    </div>

    <dl class="report-summary">
        <div><dt>Enrolled students</dt><dd>{{ number_format($summary['enrolled_students']) }}</dd></div>
        <div><dt>Printed cards</dt><dd>{{ number_format($summary['printed_cards']) }}</dd><small>{{ number_format($summary['unique_students_printed']) }} unique students</small></div>
        <div><dt>Released cards</dt><dd>{{ number_format($summary['released_cards']) }}</dd><small>{{ number_format($summary['unique_students_released']) }} unique students</small></div>
        <div><dt>Office inventory</dt><dd>{{ number_format($summary['printed_not_released']) }}</dd><small>Printed, not released</small></div>
        <div><dt>Without printed card</dt><dd>{{ number_format($summary['students_without_printed']) }}</dd></div>
        <div><dt>Without released card</dt><dd>{{ number_format($summary['students_without_released']) }}</dd></div>
    </dl>

    <form method="GET" class="production-filter-form">
        <div class="production-advanced" style="display:grid">
            <label class="filter-search"><span>Search</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Student number or name"></label>
            <label><span>Report</span><select name="report_type">@foreach(['enrolled'=>'Enrolled students','printed_cards'=>'Printed cards','released_cards'=>'Released cards','printed_not_released'=>'Printed, not released','without_printed'=>'Students without printed card','without_released'=>'Students without released card'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['report_type'] ?? 'enrolled') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label><span>Status</span><select name="status"><option value="">All card statuses</option>@foreach(\App\Enums\CardStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
            <label><span>From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
            <label><span>To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            <button class="btn primary">Apply filters</button><a class="btn text" href="{{ route('reports.index') }}">Reset</a>
        </div>
    </form>

    @if(!$activeTerm)
        <div class="production-empty"><h3>No active academic term</h3><p>An administrator must activate a term in Academic Setup before operational reports can run.</p></div>
    @elseif($records->isEmpty())
        <div class="production-empty"><h3>No matching records</h3><p>No records meet this report and filter combination in the active term.</p></div>
    @else
        <p class="report-table-hint" id="report-table-hint">The table can be scrolled horizontally on smaller screens.</p>
        <div class="table-wrap" role="region" aria-label="Operational report records" aria-describedby="report-table-hint" tabindex="0"><table class="production-table">
            <thead><tr><th>Student</th><th>Program</th><th>Year Level</th><th>Semester</th><th>Card</th><th>Printed</th><th>Released</th></tr></thead>
            <tbody>@foreach($rows as $row)<tr>
                <td><strong>{{ $row['student_name'] }}</strong><small class="mono">{{ $row['student_number'] }}</small></td>
                <td>{{ $row['program'] ?: 'Not assigned' }}</td><td>{{ $row['year_level'] ?: 'Not assigned' }}</td><td>{{ $row['semester'] ?: 'Not assigned' }}</td>
                <td>@if($row['card_id'])#{{ $row['card_id'] }}<small>{{ $row['issuance_type'] }}  -  {{ str($row['status'])->replace('_',' ')->title() }}</small>@else<span>None</span>@endif</td>
                <td>{{ $row['printed_at'] ?: 'Not printed' }}</td><td>{{ $row['released_at'] ?: 'Not released' }}</td>
            </tr>@endforeach</tbody>
        </table></div>{{ $records->links() }}
    @endif
</section>
@endsection