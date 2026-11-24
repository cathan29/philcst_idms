@extends('layouts.app')
@section('title', $student->full_name)
@section('heading', $student->full_name)
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="page-title">{{ $student->full_name }}</h1>
            <span class="badge {{ $student->status === 'active' ? 'badge-success' : 'badge-neutral' }}">{{ str($student->status)->title() }}</span>
        </div>
        <p class="page-subtitle">
            <span class="mono font-semibold text-[#334155]">{{ $student->student_number }}</span>
            &middot; Source: {{ str($student->record_source)->title() }}
        </p>
    </div>
    <div class="flex items-center gap-2.5">
        <a class="btn secondary" href="{{ route('students.index') }}">Back to Student Records</a>
        @if(auth()->user()->hasPermission('students.manage'))
            <a class="btn primary" href="{{ route('students.edit', $student) }}">Edit Identity</a>
        @endif
    </div>
</div>

<div class="space-y-6">
    <section class="surface overflow-hidden">
        <div class="border-b border-[#E2E6EF] px-5 py-4">
            <h2 class="text-base font-semibold text-[#172033]">Active-Term Enrollment</h2>
        </div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Academic Year</th>
                        <th>Semester</th>
                        <th>Program</th>
                        <th>Year Level</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($student->enrollments as $enrollment)
                        <tr>
                            <td class="num font-medium">{{ $enrollment->academicYear->name }}</td>
                            <td class="num">{{ $enrollment->academicTerm?->name ?? $enrollment->semester ?? 'Not recorded' }}</td>
                            <td class="font-medium">{{ $enrollment->program->code }}</td>
                            <td class="num">{{ $enrollment->year_level ?? 'Not recorded' }}</td>
                            <td><span class="badge badge-neutral">{{ str($enrollment->status)->title() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-sm text-[#64748B]">No active-term enrollment is available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="surface overflow-hidden">
        <div class="border-b border-[#E2E6EF] px-5 py-4">
            <h2 class="text-base font-semibold text-[#172033]">Active-Term Physical Cards</h2>
        </div>
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Card</th>
                        <th>Issuance</th>
                        <th>Status</th>
                        <th>Printed</th>
                        <th>Released</th>
                        <th class="text-right">Updates</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($student->idCards as $card)
                        <tr>
                            <td class="mono font-semibold">
                                <a href="{{ route('production.show', $card) }}" class="text-[#6D28A8] hover:underline">#{{ $card->id }}</a>
                            </td>
                            <td>
                                {{ str($card->issuance_type)->title() }}
                                @if($card->replacement_sequence)
                                    <span class="badge badge-brand badge-no-dot ml-1 num">Reprint #{{ $card->replacement_sequence }}</span>
                                @endif
                            </td>
                            <td><span class="badge status-{{ $card->status->value }}">{{ str($card->status->value)->replace('_', ' ')->title() }}</span></td>
                            <td class="num text-[13px] text-[#475569]">{{ $card->printed_at?->format('M j, Y g:i A') ?? 'Not recorded' }}</td>
                            <td class="num text-[13px] text-[#475569]">{{ $card->release?->released_at?->format('M j, Y g:i A') ?? 'Not released' }}</td>
                            <td class="text-right num font-medium">{{ $card->histories->count() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-[#64748B]">No physical card is recorded for this term.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
