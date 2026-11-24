@extends('layouts.app')
@section('title', 'Confirm Student Import')
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Confirm Import</h1>
        <p class="page-subtitle">Verify the final impact before writing validated student enrollment records.</p>
    </div>
    <a class="btn secondary" href="{{ route('imports.students.review') }}">&larr; Back to Review</a>
</div>

@include('imports.students._steps', ['current' => 4])

<div class="mb-5 rounded-lg border border-[#E2E6EF] bg-white px-5 py-3 text-sm">
    <span class="font-semibold text-[#172033]">Selected term:</span>
    <span class="ml-1.5 text-[#475569] num">{{ $preview['term']['label'] }} &middot; {{ empty($preview['context']['coverage_program_id']) ? 'All programs' : 'Specific program' }}</span>
</div>

<section class="surface max-w-3xl overflow-hidden">
    <div class="border-b border-[#E2E6EF] px-6 py-5">
        <h2 class="text-base font-semibold text-[#172033]">Ready to import <span class="num text-[#6D28A8]">{{ number_format($preview['summary']['valid']) }}</span> valid records</h2>
        <p class="mt-0.5 text-sm text-[#64748B]">This action writes only validated rows and does not remove students absent from the file.</p>
    </div>

    <dl class="grid grid-cols-2 gap-px border-b border-[#E2E6EF] bg-[#E2E6EF] sm:grid-cols-5">
        <div class="bg-white p-4">
            <dt class="text-xs font-medium text-[#64748B]">New records</dt>
            <dd class="mt-1 text-2xl font-bold text-[#172033] num">{{ number_format($preview['summary']['new']) }}</dd>
        </div>
        <div class="bg-white p-4">
            <dt class="text-xs font-medium text-[#64748B]">To update</dt>
            <dd class="mt-1 text-2xl font-bold text-[#172033] num">{{ number_format($mode === 'insert_or_update' ? $preview['summary']['changed'] : $preview['summary']['new_enrollments']) }}</dd>
        </div>
        <div class="bg-white p-4">
            <dt class="text-xs font-medium text-[#64748B]">Unchanged</dt>
            <dd class="mt-1 text-2xl font-bold text-[#172033] num">{{ number_format($preview['summary']['unchanged'] + ($mode === 'insert_only' ? $preview['summary']['modified_enrollments'] : 0)) }}</dd>
        </div>
        <div class="bg-white p-4">
            <dt class="text-xs font-medium text-[#64748B]">Rejected</dt>
            <dd class="mt-1 text-2xl font-bold text-[#172033] num">{{ number_format($preview['summary']['invalid'] + $preview['summary']['duplicates']) }}</dd>
        </div>
        <div class="bg-white p-4 col-span-2 sm:col-span-1">
            <dt class="text-xs font-medium text-[#64748B]">Import mode</dt>
            <dd class="mt-1.5 text-sm font-semibold text-[#172033]">{{ $mode === 'insert_only' ? 'Insert new only' : 'Insert and update' }}</dd>
        </div>
    </dl>

    <form method="POST" action="{{ route('imports.students.commit') }}" id="commitImportForm" class="p-6">
        @csrf
        <input type="hidden" name="mode" value="{{ $mode }}">
        <input type="hidden" name="confirmation_token" value="{{ $token }}">

        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-[#E2E6EF] bg-[#F8F9FC] p-4 text-sm text-[#334155]">
            <input type="checkbox" required>
            <span>I reviewed the validation results and approve this import.</span>
        </label>

        <div class="mt-5 flex items-center justify-end gap-2.5 border-t border-[#E2E6EF] pt-4">
            <a class="btn secondary" href="{{ route('imports.students.review') }}">Back</a>
            <button class="btn primary" type="submit" id="commitButton">Confirm Import</button>
        </div>
    </form>
</section>
@endsection

@push('scripts')
<script>
document.getElementById('commitImportForm')?.addEventListener('submit', () => {
    const button = document.getElementById('commitButton');
    button.disabled = true;
    button.textContent = 'Importing...';
});
</script>
@endpush
