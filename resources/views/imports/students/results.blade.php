@extends('layouts.app')
@section('title', 'Import Results')
@section('content')
@php($hasIssues = ($batch->rejected_rows + $batch->duplicate_rows) > 0)

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Import Results</h1>
        <p class="page-subtitle">Processing summary for {{ $batch->original_filename }}.</p>
    </div>
    <a class="btn primary" href="{{ route('students.index') }}">View Student Registry</a>
</div>

<div class="mb-5 rounded-lg border border-[#E2E6EF] bg-white px-5 py-3 text-sm">
    <span class="font-semibold text-[#172033]">Imported term:</span>
    <span class="ml-1.5 text-[#475569] num">{{ $batch->academicTerm->academicYear->name }} &middot; {{ $batch->academicTerm->name }} &middot; {{ $batch->coverageProgram?->code ?? 'All programs' }}</span>
</div>

<section class="surface max-w-3xl overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E6EF] px-6 py-5">
        <div>
            <h2 class="text-base font-semibold text-[#172033]">{{ $hasIssues ? 'Import completed with issues' : 'Import completed' }}</h2>
            <p class="mt-0.5 text-sm text-[#64748B]">{{ $hasIssues ? 'Valid rows were processed. Download the error report to inspect rejected rows.' : 'All validated rows were processed successfully.' }}</p>
        </div>
        <span class="badge {{ $hasIssues ? 'badge-warning' : 'badge-success' }}">{{ $hasIssues ? 'Completed with issues' : 'Completed' }}</span>
    </div>

    <dl class="grid grid-cols-2 gap-px border-b border-[#E2E6EF] bg-[#E2E6EF] sm:grid-cols-5">
        <div class="bg-white p-4">
            <dt class="text-xs font-medium text-[#64748B]">Inserted</dt>
            <dd class="mt-1 text-2xl font-bold text-[#172033] num">{{ number_format($batch->imported_rows) }}</dd>
        </div>
        <div class="bg-white p-4">
            <dt class="text-xs font-medium text-[#64748B]">Updated</dt>
            <dd class="mt-1 text-2xl font-bold text-[#172033] num">{{ number_format($batch->updated_rows) }}</dd>
        </div>
        <div class="bg-white p-4">
            <dt class="text-xs font-medium text-[#64748B]">Unchanged</dt>
            <dd class="mt-1 text-2xl font-bold text-[#172033] num">{{ number_format($batch->unchanged_rows) }}</dd>
        </div>
        <div class="bg-white p-4">
            <dt class="text-xs font-medium text-[#64748B]">Skipped</dt>
            <dd class="mt-1 text-2xl font-bold text-[#172033] num">{{ number_format($batch->duplicate_rows) }}</dd>
        </div>
        <div class="bg-white p-4">
            <dt class="text-xs font-medium text-[#64748B]">Rejected</dt>
            <dd class="mt-1 text-2xl font-bold text-[#172033] num">{{ number_format($batch->rejected_rows) }}</dd>
        </div>
    </dl>

    <div class="flex flex-wrap items-center justify-end gap-2.5 bg-[#F8F9FC] px-6 py-4">
        @if($hasIssues)
            <a class="btn secondary" href="{{ route('imports.students.errors', $batch) }}">Download Error Report</a>
        @endif
        <a class="btn secondary" href="{{ route('imports.students.create') }}">Import Another File</a>
        <a class="btn primary" href="{{ route('students.index') }}">View Student Registry</a>
    </div>
</section>
@endsection
