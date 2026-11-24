@extends('layouts.app')
@section('title', 'Add Production Record')
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Add Production Record</h1>
        <p class="page-subtitle">Add one physical card production record for an active-term enrolled student.</p>
    </div>
    <a class="btn secondary" href="{{ route('production.index') }}">Back to Queue</a>
</div>

@if($errors->any())
    <div class="alert-banner error" role="alert">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('production.store') }}" id="idRequestForm" data-search-url="{{ route('production.students.search') }}" class="max-w-3xl space-y-5" novalidate>
    @csrf
    <input type="hidden" name="student_id" id="selectedStudentId" value="{{ old('student_id') }}">

    <section class="surface p-6" aria-labelledby="find-student-title">
        <h2 id="find-student-title" class="text-base font-semibold text-[#172033]">1. Find Student</h2>
        <p class="mt-0.5 text-sm text-[#64748B]">Search the active-term Student Registry by student number or full name.</p>

        <div class="mt-4" id="studentPicker">
            <label for="studentSearch" class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Student search</label>
            <div class="relative">
                <input id="studentSearch" type="search" autocomplete="off" placeholder="Enter at least 2 characters"
                    role="combobox" aria-autocomplete="list" aria-controls="studentSearchResults" aria-expanded="false">
                <span class="student-search-spinner" id="studentSearchSpinner" aria-hidden="true"></span>
            </div>
            <div class="mt-1.5 text-xs text-[#64748B]" id="studentSearchStatus" aria-live="polite">Enter a student number or name to begin.</div>
            <div class="student-search-results" id="studentSearchResults" role="listbox" hidden></div>
        </div>
    </section>

    <section class="surface p-6" id="selectedStudentSection" aria-labelledby="selected-student-title" hidden>
        <div class="flex items-center justify-between gap-2">
            <h2 id="selected-student-title" class="text-base font-semibold text-[#172033]">2. Selected Student</h2>
            <button type="button" class="text-xs font-semibold text-[#6D28A8] hover:underline" id="changeStudentButton">Change student</button>
        </div>
        <div class="mt-4 flex items-center gap-3.5 rounded-lg border border-[#E3D4F3] bg-[#F3ECFA]/50 p-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#6D28A8] text-sm font-semibold text-white" id="selectedStudentInitials" aria-hidden="true"></div>
            <div class="min-w-0">
                <p id="selectedStudentName" class="text-sm font-semibold text-[#172033]"></p>
                <p id="selectedStudentNumber" class="mono text-xs text-[#475569]"></p>
                <p id="selectedStudentEnrollmentSummary" class="text-xs text-[#64748B]"></p>
            </div>
        </div>
        <div class="mt-3 text-xs text-[#475569]" id="studentIdSummary"></div>
    </section>

    <section class="surface p-6" id="issuanceDetailsSection" aria-labelledby="issuance-details-title" hidden>
        <h2 id="issuance-details-title" class="text-base font-semibold text-[#172033]">3. Issuance Details</h2>
        <p class="mt-0.5 text-sm text-[#64748B]">Select the exact active-term enrollment and authorized issuance reason.</p>

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="block sm:col-span-2">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Enrollment <span class="text-[#B91C1C]">*</span></span>
                <select name="student_enrollment_id" id="enrollmentSelect" required>
                    <option value="">Select enrollment</option>
                </select>
                <span class="mt-1 block text-xs text-[#64748B]">Only the active academic term is available.</span>
            </label>

            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Issuance Reason <span class="text-[#B91C1C]">*</span></span>
                <select name="issuance_type" id="issuanceType" required>
                    <option value="">Select reason</option>
                    @foreach($issuanceTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('issuance_type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block" id="existingCardField" hidden>
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Existing Physical ID <span class="text-[#B91C1C]">*</span></span>
                <select name="existing_card_id" id="existingCardSelect">
                    <option value="">Select existing ID</option>
                </select>
                <span class="mt-1 block text-xs text-[#64748B]">Links the replacement without revoking the existing card.</span>
            </label>

            <label class="block sm:col-span-2" id="notesField">
                <span id="notesLabel" class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Internal Notes</span>
                <textarea name="internal_notes" id="internalNotes" rows="3" maxlength="1000" placeholder="Add authorization context or production notes">{{ old('internal_notes') }}</textarea>
                <span id="notesHelp" class="mt-1 block text-xs text-[#64748B]">Visible to authorized production staff only.</span>
            </label>
        </div>
        <div class="mt-4 rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-sm text-[#B45309]" id="eligibilityWarning" role="status" hidden></div>
    </section>

    <section class="surface p-6" id="reviewSection" aria-labelledby="review-title" hidden>
        <h2 id="review-title" class="text-base font-semibold text-[#172033]">4. Review and Add</h2>
        <dl class="mt-4 grid grid-cols-1 gap-3.5 border-y border-[#E2E6EF] py-4 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-[#64748B]">Student</dt><dd id="reviewStudent" class="mt-0.5 font-medium text-[#172033]">Not selected</dd></div>
            <div><dt class="text-xs text-[#64748B]">Enrollment</dt><dd id="reviewEnrollment" class="mt-0.5 font-medium text-[#172033]">Not selected</dd></div>
            <div><dt class="text-xs text-[#64748B]">Reason</dt><dd id="reviewReason" class="mt-0.5 font-medium text-[#172033]">Not selected</dd></div>
            <div><dt class="text-xs text-[#64748B]">Result</dt><dd class="mt-0.5 font-medium text-[#172033]">Pending physical card production</dd></div>
        </dl>
        <p class="mt-3 text-xs text-[#64748B]">This adds a Pending production record only. It does not mark a card printed or released.</p>
        <div class="mt-5 flex items-center justify-end gap-2.5">
            <a class="btn secondary" href="{{ route('production.index') }}">Cancel</a>
            <button class="btn primary" id="createRequestButton" type="submit" disabled>
                <span class="request-submit-spinner" aria-hidden="true"></span>
                <span>Add Pending Record</span>
            </button>
        </div>
    </section>
</form>

<script type="application/json" id="initialStudentData">@json($initialStudent)</script>
@php
    $requestOldInput = [
        'enrollment_id' => old('student_enrollment_id'),
        'issuance_type' => old('issuance_type'),
        'existing_card_id' => old('existing_card_id'),
    ];
@endphp
<script type="application/json" id="requestOldInput">@json($requestOldInput)</script>
@endsection

@push('scripts')
<script src="{{ asset('js/production-request.js') }}" defer></script>
@endpush
