@extends('layouts.app')
@section('title', 'Import Enrollments')
@section('content')
@php
    $activeTerms = $terms->where('status', 'active');
    $historicalTerms = $terms->where('status', '!=', 'active');
@endphp
<header class="page-header import-page-header">
    <div><p class="eyebrow">Student Management</p><h1>Import Enrollments</h1><p class="page-description">Set the academic context, then upload an official enrollment CSV.</p></div>
    <a class="import-back-link" href="{{ route('students.index') }}">Back to Registry</a>
</header>
@include('imports.students._steps', ['current' => 1])
<section class="panel import-card term-upload-card">
    <form action="{{ route('imports.students.preview') }}" method="POST" enctype="multipart/form-data" id="csvUploadForm" class="term-upload-form">@csrf
        @if($errors->any())
            <div class="import-error-summary" role="alert"><strong>Check the information below.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section class="import-section academic-context-section" aria-labelledby="academic-context-title">
            <div class="import-section-heading"><div><p class="section-kicker">Import settings</p><h2 id="academic-context-title">Academic Context</h2><p>Choose the term and program coverage for every approved row.</p></div></div>
            <div class="academic-context-grid">
                <label class="context-field"><span>Academic Year &amp; Semester <b>*</b></span><select name="academic_term_id" id="academicTerm" required aria-describedby="academicTermHelp"><option value="">Select academic term</option>@if($activeTerms->isNotEmpty())<optgroup label="Active terms">@foreach($activeTerms as $term)<option value="{{ $term->id }}" @selected(old('academic_term_id') == $term->id)>AY {{ str($term->academicYear->name)->replace('-', '–') }} · {{ $term->name }}</option>@endforeach</optgroup>@endif @if($historicalTerms->isNotEmpty())<optgroup label="Historical terms — authorized imports">@foreach($historicalTerms as $term)<option value="{{ $term->id }}" @selected(old('academic_term_id') == $term->id)>AY {{ str($term->academicYear->name)->replace('-', '–') }} · {{ $term->name }} (Historical)</option>@endforeach</optgroup>@endif</select><small id="academicTermHelp">Historical terms are available for authorized backfile imports.</small></label>
                <label class="context-field"><span>Import Coverage <b>*</b></span><select name="coverage" id="coverage" required><option value="all" @selected(old('coverage', 'all') === 'all')>All Programs in CSV</option><option value="specific" @selected(old('coverage') === 'specific')>Single Program</option></select><small>All Programs uses each validated row's mapped program.</small></label>
                <label class="context-field" id="programField" hidden><span>Program <b>*</b></span><select name="coverage_program_id" id="coverageProgram"><option value="">Select official program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(old('coverage_program_id') == $program->id)>{{ $program->code }} · {{ $program->name }}</option>@endforeach</select><small>Rows with a different mapped program will be rejected.</small></label>
            </div>
        </section>

        <section class="import-section upload-csv-section" aria-labelledby="upload-csv-title">
            <div class="import-section-heading upload-heading"><div><p class="section-kicker">Official data file</p><h2 id="upload-csv-title">Upload CSV</h2><p>Upload official student enrollment data.</p></div><a class="template-action" href="{{ route('imports.students.template') }}">Download Template</a></div>
            <label class="csv-dropzone step-one-dropzone" for="csv" id="csvDropzone">
                <span class="upload-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 16V4m0 0-4 4m4-4 4 4M5 15a4 4 0 0 0 1 7h12a4 4 0 0 0 1-7 7 7 0 0 0-13.5-2A4.5 4.5 0 0 0 5 15Z"/></svg></span>
                <span class="dropzone-copy"><strong>Drop your CSV file here</strong><small>CSV only · Maximum {{ number_format($maxMegabytes) }} MB</small></span>
                <span class="browse-action">Browse Files</span>
                <input id="csv" type="file" name="csv" accept=".csv,text/csv" required>
            </label>
            <div class="file-validation-message" id="fileValidationMessage" role="status" hidden></div>
            <div class="selected-file step-one-selected-file" id="selectedFile" hidden>
                <span class="selected-file-icon">CSV</span>
                <div><strong id="selectedFileName"></strong><small id="selectedFileSize"></small><span class="file-status" id="selectedFileStatus">Ready to validate</span></div>
                <button type="button" id="replaceFile">Replace</button><button type="button" id="removeFile">Remove</button>
            </div>
            <aside class="import-safety"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.6 2.8 8.1 7 10 4.2-1.9 7-5.4 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-5"/></svg><div><strong>Import safety</strong><span>Preview and mapping do not change permanent records. Enrollment imports never print, release, replace, or revoke physical IDs.</span></div></aside>
        </section>

        <footer class="term-upload-actions"><a class="btn text" href="{{ route('students.index') }}">Cancel</a><button class="btn primary import-continue" type="submit" id="continueButton" disabled>Continue to Map Columns</button></footer>
    </form>
</section>
@endsection
@push('scripts')
<script>
(() => {
    const form = document.getElementById('csvUploadForm');
    const term = document.getElementById('academicTerm');
    const coverage = document.getElementById('coverage');
    const programField = document.getElementById('programField');
    const program = document.getElementById('coverageProgram');
    const input = document.getElementById('csv');
    const dropzone = document.getElementById('csvDropzone');
    const selected = document.getElementById('selectedFile');
    const message = document.getElementById('fileValidationMessage');
    const continueButton = document.getElementById('continueButton');
    const maxBytes = {{ (int) config('idms.import_max_kilobytes', 25600) * 1024 }};
    let validFile = false;

    const validateFile = () => {
        const file = input.files?.[0];
        validFile = false;
        message.hidden = true;
        selected.hidden = true;
        dropzone.hidden = false;
        if (!file) return sync();
        const extensionOk = file.name.toLowerCase().endsWith('.csv');
        if (!extensionOk || file.size > maxBytes || file.size === 0) {
            message.textContent = !extensionOk ? 'Select a CSV file. Other file types are not supported.' : (file.size === 0 ? 'The selected CSV is empty.' : 'The selected file exceeds the {{ number_format($maxMegabytes) }} MB upload limit.');
            message.hidden = false;
            input.value = '';
            return sync();
        }
        validFile = true;
        document.getElementById('selectedFileName').textContent = file.name;
        document.getElementById('selectedFileSize').textContent = file.size < 1048576 ? `${(file.size / 1024).toFixed(1)} KB` : `${(file.size / 1048576).toFixed(2)} MB`;
        selected.hidden = false;
        dropzone.hidden = true;
        sync();
    };
    const sync = () => {
        const single = coverage.value === 'specific';
        programField.hidden = !single;
        program.disabled = !single;
        program.required = single;
        continueButton.disabled = !(term.value && validFile && (!single || program.value));
    };
    input.addEventListener('change', validateFile);
    [term, coverage, program].forEach(element => element.addEventListener('change', sync));
    document.getElementById('removeFile').addEventListener('click', () => { input.value = ''; validFile = false; validateFile(); });
    document.getElementById('replaceFile').addEventListener('click', () => input.click());
    ['dragenter', 'dragover'].forEach(name => dropzone.addEventListener(name, event => { event.preventDefault(); dropzone.classList.add('is-dragging'); }));
    ['dragleave', 'drop'].forEach(name => dropzone.addEventListener(name, event => { event.preventDefault(); dropzone.classList.remove('is-dragging'); }));
    dropzone.addEventListener('drop', event => { if (event.dataTransfer.files.length) { input.files = event.dataTransfer.files; validateFile(); } });
    form.addEventListener('submit', event => {
        sync();
        if (continueButton.disabled) { event.preventDefault(); form.reportValidity(); return; }
        continueButton.disabled = true;
        continueButton.textContent = 'Validating CSV…';
    });
    sync();
})();
</script>
@endpush