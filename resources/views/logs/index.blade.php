@extends('layouts.app')
@section('title', 'Scan Activity')
@section('heading', 'Scan Activity')
@section('content')
<div class="mb-6">
    <h1 class="page-title">Scan Activity</h1>
    <p class="page-subtitle">Audit trail of verification and scan events across workstations.</p>
</div>

<section class="surface overflow-hidden">
    <form method="GET" class="grid grid-cols-1 items-end gap-3.5 border-b border-[#E2E6EF] bg-[#F8F9FC] px-5 py-4 sm:grid-cols-12">
        <div class="sm:col-span-5">
            <label class="mb-1.5 block text-xs font-medium text-[#475569]" for="logSearch">Search</label>
            <input id="logSearch" name="search" type="search" value="{{ $search }}" placeholder="Search person or ID number">
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-[#475569]" for="logStatus">Result</label>
            <select id="logStatus" name="status">
                <option value="">All results</option>
                <option value="accepted" @selected($status === 'accepted')>Accepted</option>
                <option value="rejected" @selected($status === 'rejected')>Rejected</option>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-[#475569]" for="logDate">Date</label>
            <input id="logDate" type="date" name="date" value="{{ $date }}">
        </div>
        <div class="flex items-center gap-2 sm:col-span-3">
            <button class="btn primary flex-1" type="submit">Apply filters</button>
            <a class="btn secondary" href="{{ route('logs.index') }}">Clear</a>
        </div>
    </form>

    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Person</th>
                    <th>ID Number</th>
                    <th>Action</th>
                    <th>Result</th>
                    <th>Remarks</th>
                    <th>Date &amp; Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>
                            <span class="font-semibold text-[#172033]">{{ $log->idHolder?->full_name ?? $log->student?->full_name ?? 'Unknown ID' }}</span>
                            <span class="block text-xs text-[#64748B]">{{ $log->idHolder?->holder_type ? ucfirst($log->idHolder->holder_type) : ($log->student ? 'Student' : 'Unregistered') }}</span>
                        </td>
                        <td class="mono text-[13px] font-medium text-[#334155]">{{ $log->student?->student_number ?? $log->scanned_code }}</td>
                        <td><span class="badge badge-neutral">{{ ucfirst($log->action) }}</span></td>
                        <td><span class="badge {{ $log->status }}">{{ ucfirst($log->status) }}</span></td>
                        <td class="text-sm text-[#475569]">{{ $log->remarks }}</td>
                        <td class="whitespace-nowrap text-[13px] text-[#475569] num">{{ $log->scanned_at->format('M d, Y · h:i:s A') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center text-sm text-[#64748B]">No scan records match the current filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="pagination-bar">
            <span class="num">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>
            <div class="flex items-center gap-2">
                @if(!$logs->onFirstPage())
                    <a class="btn secondary btn-sm" href="{{ $logs->previousPageUrl() }}">Previous</a>
                @endif
                @if($logs->hasMorePages())
                    <a class="btn secondary btn-sm" href="{{ $logs->nextPageUrl() }}">Next</a>
                @endif
            </div>
        </div>
    @endif
</section>
@endsection
