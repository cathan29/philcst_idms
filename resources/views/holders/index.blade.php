@extends('layouts.app')
@section('title', 'ID Holders')
@section('heading', 'ID Holders')
@section('content')
<section class="panel">
    <div class="panel-heading responsive">
        <form method="GET" class="filter-form"><input name="search" value="{{ $search }}" placeholder="Search name, ID, department"><button class="btn secondary">Search</button></form>
        <a class="btn primary" href="{{ route('holders.create') }}">+ Add ID holder</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Holder</th><th>ID number</th><th>Category</th><th>Department</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($holders as $holder)
                <tr>
                    <td><strong>{{ $holder->full_name }}</strong><small>{{ $holder->email ?: 'No email provided' }}</small></td>
                    <td class="mono">{{ $holder->id_number }}</td>
                    <td>{{ ucfirst($holder->holder_type) }}</td>
                    <td>{{ $holder->department ?: '—' }}</td>
                    <td><span class="badge {{ $holder->status === 'active' ? 'accepted' : 'rejected' }}">{{ ucfirst($holder->status) }}</span></td>
                    <td class="actions"><a href="{{ route('holders.edit', $holder) }}">Edit</a><form method="POST" action="{{ route('holders.destroy', $holder) }}" onsubmit="return confirm('Archive this ID holder?')">@csrf @method('DELETE')<button>Archive</button></form></td>
                </tr>
            @empty<tr><td colspan="6" class="empty">No ID holders found.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($holders->hasPages())
        <div class="pagination"><span>Page {{ $holders->currentPage() }} of {{ $holders->lastPage() }}</span><div>@if($holders->onFirstPage())<span>Previous</span>@else<a href="{{ $holders->previousPageUrl() }}">Previous</a>@endif @if($holders->hasMorePages())<a href="{{ $holders->nextPageUrl() }}">Next</a>@else<span>Next</span>@endif</div></div>
    @endif
</section>
@endsection
