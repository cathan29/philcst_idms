@extends('layouts.app')
@section('title', (($filters['status'] ?? null) === 'released') ? 'Released IDs' : 'Printed IDs')
@section('content')
@php
    $isReleasedView = ($filters['status'] ?? null) === 'released';
    $advancedKeys = ['program', 'year_level', 'status', 'issuance_type', 'date_from', 'date_to'];
    $advancedOpen = collect($advancedKeys)->contains(fn ($key) => filled($filters[$key] ?? null));
@endphp

<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">{{ $isReleasedView ? 'Released IDs' : 'Printed IDs (Repro Table)' }}</h1>
        <p class="page-subtitle">Track physical card production, reprints, and per-card release status for the active academic term.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if($isReleasedView)
            <a class="btn secondary" href="{{ route('production.index') }}">View All Production</a>
        @endif
        @if(auth()->user()->hasPermission('cards.manage'))
            <a class="btn primary" href="{{ route('production.create') }}">Add Production Record</a>
        @endif
    </div>
</div>

@if(session('bulk_result'))
    <div class="alert-banner success" role="status">
        <span class="num">{{ session('bulk_result.eligible') }} updated &middot; {{ session('bulk_result.ineligible') }} ineligible</span>
    </div>
@endif

<section aria-label="Production status summary" class="metric-strip mb-5 grid-cols-2 divide-x divide-y divide-[#E2E6ED] sm:grid-cols-3 lg:grid-cols-5 lg:divide-y-0">
    @foreach([
        'pending' => 'Pending Printing',
        'for_printing' => 'For Printing',
        'printed' => 'Printed',
        'ready_for_release' => 'Ready for Release',
        'on_hold' => 'On Hold',
    ] as $key => $label)
        @php
            $isSelected = ($filters['status'] ?? null) === $key;
        @endphp
        <a href="{{ route('production.index', ['status' => $key]) }}"
            class="metric-cell {{ $isSelected ? 'is-selected' : '' }}">
            <span class="kpi-label">{{ $label }}</span>
            <p class="kpi-value">{{ number_format($metrics[$key]) }}</p>
        </a>
    @endforeach
</section>

<section class="surface overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E6EF] px-5 py-4">
        <div>
            <h2 class="text-base font-semibold text-[#172033]">{{ $isReleasedView ? 'Released Physical Cards' : 'Production Queue' }}</h2>
            <p class="text-xs text-[#64748B]">Each row represents one physical card issuance or reprint with its own release status.</p>
        </div>
        <span class="text-xs font-medium text-[#64748B] num">{{ number_format($cards->total()) }} {{ $cards->total() === 1 ? 'record' : 'records' }}</span>
    </div>

    <form method="GET" class="border-b border-[#E2E6EF] bg-[#F8F9FC] px-5 py-3.5">
        <div class="flex flex-wrap items-center gap-2.5">
            <div class="flex-1 min-w-[240px]">
                <label class="sr-only" for="productionSearch">Search production records</label>
                <input id="productionSearch" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search student number or name">
            </div>
            <button class="btn secondary" type="button" id="productionFilterToggle" aria-controls="productionAdvanced">Filters</button>
            <button class="btn primary" type="submit">Search</button>
        </div>

        <div id="productionAdvanced" class="mt-3.5 grid grid-cols-1 gap-3.5 border-t border-[#E2E6EF] pt-3.5 sm:grid-cols-2 lg:grid-cols-6" @if(!$advancedOpen) hidden @endif>
            <label class="block">
                <span class="mb-1.5 block text-xs font-medium text-[#475569]">Program</span>
                <select name="program">
                    <option value="">All programs</option>
                    @foreach($programs as $program)
                        <option value="{{ $program->id }}" @selected(($filters['program'] ?? null) == $program->id)>{{ $program->code }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-medium text-[#475569]">Year level</span>
                <input name="year_level" type="text" value="{{ $filters['year_level'] ?? '' }}" placeholder="e.g. 1st Year">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-medium text-[#475569]">Status</span>
                <select name="status">
                    <option value="">All statuses</option>
                    @foreach(['pending','for_printing','printed','ready_for_release','released','on_hold','cancelled','revoked'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-medium text-[#475569]">Issuance type</span>
                <select name="issuance_type">
                    <option value="">All types</option>
                    @foreach(['initial','replacement','lost','damaged','reissue','other'] as $type)
                        <option value="{{ $type }}" @selected(($filters['issuance_type'] ?? '') === $type)>{{ str($type)->title() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-medium text-[#475569]">From</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-xs font-medium text-[#475569]">To</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
            </label>
            <div class="flex items-center justify-end gap-2 sm:col-span-2 lg:col-span-6">
                <a class="btn secondary" href="{{ route('production.index') }}">Reset</a>
                <button class="btn primary" type="submit">Apply Filters</button>
            </div>
        </div>
    </form>

    @if($cards->isEmpty())
        <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
            <span class="icon-well mb-3 !h-12 !w-12 !rounded-xl" aria-hidden="true">
                <svg class="!h-6 !w-6" viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/></svg>
            </span>
            <h3 class="text-base font-semibold text-[#172033]">No production records for the active term</h3>
            <p class="mt-1 max-w-sm text-sm text-[#64748B]">Add a production record for an eligible enrolled student to begin tracking printing and release.</p>
            @if(auth()->user()->hasPermission('cards.manage'))
                <a class="btn primary mt-4" href="{{ route('production.create') }}">Add Production Record</a>
            @endif
        </div>
    @else
        <form method="POST" action="{{ route('production.bulk') }}" id="bulkForm">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E6EF] bg-white px-5 py-2.5 text-sm">
                <span class="text-[#475569]"><strong id="selectedCount" class="num text-[#172033]">0</strong> selected</span>
                <div class="flex items-center gap-2">
                    <select name="status" required aria-label="Bulk status action" class="min-h-9 w-52 py-1.5 text-[13px]">
                        <option value="">Bulk status action...</option>
                        <option value="for_printing">Move to For Printing</option>
                        <option value="printed">Confirm Printed</option>
                        <option value="ready_for_release">Mark Ready for Release</option>
                    </select>
                    <button class="btn secondary btn-sm" type="submit" @disabled(!auth()->user()->hasPermission('cards.print'))>Apply</button>
                </div>
            </div>

            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-10"><input type="checkbox" id="selectAll" aria-label="Select all records"></th>
                            <th>Student</th>
                            <th>Program / Level</th>
                            <th>Card / Issuance</th>
                            <th>Production Status</th>
                            <th>Release Status</th>
                            <th>Date</th>
                            <th class="text-right"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cards as $card)
                            @php
                                $enrollment = $card->student->enrollments->firstWhere('academic_term_id', $card->academic_term_id);
                                $isReleased = $card->status === \App\Enums\CardStatus::Released || $card->release !== null;
                                $isReady = $card->status === \App\Enums\CardStatus::ReadyForRelease;
                            @endphp
                            <tr>
                                <td>
                                    <input type="checkbox" name="card_ids[]" value="{{ $card->id }}" class="card-select"
                                        data-status="{{ $card->status->value }}" aria-label="Select {{ $card->student->full_name }} card">
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#F5F0FF] text-xs font-semibold text-[#5B21B6]" aria-hidden="true">{{ strtoupper(substr($card->student->first_name, 0, 1).substr($card->student->last_name, 0, 1)) }}</span>
                                        <div class="min-w-0">
                                            <span class="block font-medium text-[#101828]">{{ $card->student->full_name }}</span>
                                            <span class="mono text-xs text-[#667085]">{{ $card->student->student_number }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap text-[#475467]">
                                    <span class="font-medium text-[#344054]">{{ $enrollment?->program?->code ?? '-' }}</span>
                                    @if($enrollment?->year_level)
                                        <span class="block text-xs text-[#667085] num">{{ $enrollment->year_level }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="id-chip">#{{ $card->id }}</span>
                                    <span class="ml-1.5 font-medium text-[#344054]">{{ str($card->issuance_type)->title() }}</span>
                                    @if($card->replacement_sequence)
                                        <span class="badge badge-brand badge-no-dot ml-1 num">Reprint #{{ $card->replacement_sequence }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    <span class="badge status-{{ $card->status->value }}">{{ str($card->status->value)->replace('_', ' ')->title() }}</span>
                                </td>
                                <td class="whitespace-nowrap">
                                    @if($isReleased)
                                        <span class="badge badge-success">Released</span>
                                        @if($card->release?->released_at)
                                            <span class="mt-0.5 block text-xs text-[#667085] num">{{ $card->release->released_at->format('M j, Y') }}</span>
                                        @endif
                                    @elseif($isReady)
                                        <span class="badge badge-warning">Ready &middot; Unclaimed</span>
                                    @else
                                        <span class="badge badge-neutral">Not released</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-[13px] text-[#475467] num">
                                    {{ $card->printed_at?->format('M j, Y') ?? ($card->requested_at?->format('M j, Y') ?? $card->created_at->format('M j, Y')) }}
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <a class="text-[13px] font-medium text-[#5B21B6] hover:text-[#4C1D95] hover:underline" href="{{ route('production.show', $card) }}">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>

        @if($cards->hasPages())
            <div class="pagination-bar">
                {{ $cards->links() }}
            </div>
        @endif
    @endif
</section>
@endsection

@push('scripts')
<script>
(() => {
    const advanced = document.getElementById('productionAdvanced');
    document.getElementById('productionFilterToggle')?.addEventListener('click', () => advanced.toggleAttribute('hidden'));
    const boxes = [...document.querySelectorAll('.card-select')];
    const count = document.getElementById('selectedCount');
    const sync = () => { if (count) count.textContent = boxes.filter(box => box.checked).length; };
    boxes.forEach(box => box.addEventListener('change', sync));
    document.getElementById('selectAll')?.addEventListener('change', event => {
        boxes.forEach(box => { box.checked = event.target.checked; });
        sync();
    });
    document.getElementById('bulkForm')?.addEventListener('submit', event => {
        const selected = boxes.filter(box => box.checked);
        const target = event.currentTarget.elements.status.value;
        const required = { for_printing: 'pending', printed: 'for_printing', ready_for_release: 'printed' }[target];
        const eligible = selected.filter(box => box.dataset.status === required).length;
        const ineligible = selected.length - eligible;
        if (!selected.length || !confirm(`Confirm production action? Selected: ${selected.length}. Eligible: ${eligible}. Ineligible: ${ineligible}. Resulting status: ${target.replaceAll('_', ' ')}.`)) {
            event.preventDefault();
        }
    });
})();
</script>
@endpush
