@extends('layouts.app')
@section('title', 'Create ID Request')
@section('content')
<header class="page-header production-header request-page-header">
    <div>
        <p class="eyebrow">ID Production</p>
        <h1>Create ID Request</h1>
        <p class="page-description">Create a validated production request for an existing student enrollment.</p>
    </div>
    <a class="btn text" href="{{ route('production.index') }}">Back to Queue</a>
</header>

@if($errors->any())
    <div class="auth-alert request-global-error" role="alert">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5m0 3.5v.01M10.3 3.7 2.6 17a2 2 0 0 0 1.73 3h15.34a2 2 0 0 0 1.73-3L13.7 3.7a2 2 0 0 0-3.4 0Z"/></svg>
        <span>{{ $errors->first() }}</span>
    </div>
@endif

<form method="POST" action="{{ route('production.store') }}" class="id-request-workflow" id="idRequestForm" data-search-url="{{ route('production.students.search') }}" novalidate>
    @csrf
    <input type="hidden" name="student_id" id="selectedStudentId" value="{{ old('student_id') }}">

    <section class="request-section" aria-labelledby="find-student-title">
        <div class="request-section-heading">
            <span class="request-section-number">1</span>
            <div><h2 id="find-student-title">Find Student</h2><p>Search the Student Registry by student number or full name.</p></div>
        </div>
        <div class="student-combobox" id="studentPicker">
            <label for="studentSearch">Student search</label>
            <div class="student-search-control">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input id="studentSearch" type="search" autocomplete="off" placeholder="Enter at least 2 characters" role="combobox" aria-autocomplete="list" aria-controls="studentSearchResults" aria-expanded="false">
                <span class="student-search-spinner" id="studentSearchSpinner" aria-hidden="true"></span>
            </div>
            <div class="student-search-status" id="studentSearchStatus" aria-live="polite">Enter a student number or name to begin.</div>
            <div class="student-search-results" id="studentSearchResults" role="listbox" hidden></div>
        </div>
    </section>

    <section class="request-section request-progressive" id="selectedStudentSection" aria-labelledby="selected-student-title" hidden>
        <div class="request-section-heading">
            <span class="request-section-number">2</span>
            <div><h2 id="selected-student-title">Selected Student</h2><p>Confirm the identity and review current ID records.</p></div>
        </div>
        <div class="selected-student-card">
            <div class="selected-student-avatar" id="selectedStudentInitials" aria-hidden="true"></div>
            <div class="selected-student-identity">
                <strong id="selectedStudentName"></strong>
                <span id="selectedStudentNumber"></span>
                <small id="selectedStudentEnrollmentSummary"></small>
            </div>
            <button type="button" class="text-action" id="changeStudentButton">Change student</button>
        </div>
        <div class="student-id-summary" id="studentIdSummary"></div>
    </section>

    <section class="request-section request-progressive" id="issuanceDetailsSection" aria-labelledby="issuance-details-title" hidden>
        <div class="request-section-heading">
            <span class="request-section-number">3</span>
            <div><h2 id="issuance-details-title">Issuance Details</h2><p>Select the exact enrollment and authorized issuance reason.</p></div>
        </div>
        <div class="request-detail-grid">
            <label class="request-field request-field-wide">
                <span>Enrollment <b>*</b></span>
                <select name="student_enrollment_id" id="enrollmentSelect" required>
                    <option value="">Select enrollment</option>
                </select>
                <small>Historical terms are available when authorized.</small>
            </label>
            <label class="request-field">
                <span>Issuance Reason <b>*</b></span>
                <select name="issuance_type" id="issuanceType" required>
                    <option value="">Select reason</option>
                    @foreach($issuanceTypes as $type)
                        <option value="{{ $type->value }}" @selected(old('issuance_type') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="request-field request-progressive" id="existingCardField" hidden>
                <span>Existing Physical ID <b>*</b></span>
                <select name="existing_card_id" id="existingCardSelect">
                    <option value="">Select existing ID</option>
                </select>
                <small>This links the request without revoking the existing card.</small>
            </label>
            <label class="request-field request-field-wide" id="notesField">
                <span id="notesLabel">Internal Notes</span>
                <textarea name="internal_notes" id="internalNotes" rows="3" maxlength="1000" placeholder="Add authorization context or production notes">{{ old('internal_notes') }}</textarea>
                <small id="notesHelp">Visible to authorized production staff only.</small>
            </label>
        </div>
        <div class="request-warning" id="eligibilityWarning" role="status" hidden></div>
    </section>

    <section class="request-section request-progressive" id="reviewSection" aria-labelledby="review-title" hidden>
        <div class="request-section-heading">
            <span class="request-section-number">4</span>
            <div><h2 id="review-title">Review &amp; Create</h2><p>Verify the request before adding it to the production queue.</p></div>
        </div>
        <dl class="request-review">
            <div><dt>Student</dt><dd id="reviewStudent">—</dd></div>
            <div><dt>Enrollment</dt><dd id="reviewEnrollment">—</dd></div>
            <div><dt>Reason</dt><dd id="reviewReason">—</dd></div>
            <div><dt>Result</dt><dd>Pending production request</dd></div>
        </dl>
        <div class="request-safety-note">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.6 2.8 8.1 7 10 4.2-1.9 7-5.4 7-10V6l-7-3Z"/><path d="m9.5 12 1.7 1.7 3.6-4"/></svg>
            <p>This action creates a Pending request only. It does not print, release, revoke, or replace a physical ID.</p>
        </div>
        <div class="production-actions request-actions">
            <a class="btn text" href="{{ route('production.index') }}">Cancel</a>
            <button class="btn primary" id="createRequestButton" type="submit" disabled>
                <span class="request-submit-spinner" aria-hidden="true"></span>
                <span>Create Pending Request</span>
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