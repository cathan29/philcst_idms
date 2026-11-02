@extends('layouts.app')
@section('title', 'Scan Logs')
@section('heading', 'Scan Logs')
@section('content')
<section class="panel">
    <form method="GET" class="filter-form full">
        <input name="search" value="{{ $search }}" placeholder="Search person or ID number">
        <select name="status"><option value="">All results</option><option value="accepted" @selected($status === 'accepted')>Accepted</option><option value="rejected" @selected($status === 'rejected')>Rejected</option></select>
        <input type="date" name="date" value="{{ $date }}">
        <button class="btn secondary">Apply filters</button>
        <a class="btn text" href="{{ route('logs.index') }}">Clear</a>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Person</th><th>ID number</th><th>Action</th><th>Result</th><th>Remarks</th><th>Date & time</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr><td><strong>{{ $log->idHolder?->full_name ?? 'Unknown ID' }}</strong><small>{{ $log->idHolder?->holder_type ? ucfirst($log->idHolder->holder_type) : 'Unregistered' }}</small></td><td class="mono">{{ $log->scanned_code }}</td><td><span class="badge neutral">{{ ucfirst($log->action) }}</span></td><td><span class="badge {{ $log->status }}">{{ ucfirst($log->status) }}</span></td><td>{{ $log->remarks }}</td><td>{{ $log->scanned_at->format('M d, Y · h:i:s A') }}</td></tr>
            @empty<tr><td colspan="6" class="empty">No scan records match the current filters.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="pagination"><span>Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span><div>@if(!$logs->onFirstPage())<a href="{{ $logs->previousPageUrl() }}">Previous</a>@endif @if($logs->hasMorePages())<a href="{{ $logs->nextPageUrl() }}">Next</a>@endif</div></div>@endif
</section>
@endsection
