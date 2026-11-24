@extends('layouts.app')
@section('title', 'ID Verification and Release')
@section('content')
<header class="page-header scanner-page-header"><div><p class="eyebrow">ID Verification Station</p><h1>ID Verification and Release</h1><p class="page-description">Enter a student number or use a USB scanner. Camera scanning is optional.</p></div></header>

<div class="scanner-mode-bar"><div class="scanner-modes" role="radiogroup" aria-label="Scanner mode">
@foreach($modes as $value => $label)<button type="button" class="scanner-mode {{ $loop->first ? 'active' : '' }}" data-mode="{{ $value }}" role="radio" aria-checked="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>@endforeach
</div></div>

<div class="scanner-grid" id="scannerStation" data-verify-url="{{ route('scanner.verify') }}" data-transition-template="{{ route('scanner.transition', ['card' => '__CARD__']) }}">
<section class="scanner-workspace" aria-labelledby="scanner-workspace-title">
    <div class="scanner-panel-heading"><span class="scanner-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 8V5a2 2 0 0 1 2-2h3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M8 21H5a2 2 0 0 1-2-2v-3M7 12h10"/></svg></span><div><h2 id="scanner-workspace-title">Ready for student number</h2><p id="scannerState" role="status">Enter or scan a student number, then press Enter.</p></div></div>
    <form id="scannerForm"><label for="scanPayload">Student number or card identifier</label><div class="scanner-input-row"><input id="scanPayload" name="payload" type="text" maxlength="512" autocomplete="off" inputmode="text" placeholder="Enter student number" required autofocus><button class="btn primary" id="verifyButton" type="submit" disabled><span>Verify ID</span></button></div><small>USB scanners work like a keyboard. Verification is read-only until an authorized action is explicitly confirmed.</small></form>
    <details class="camera-scanner" id="cameraScanner">
        <summary>Camera QR scanner <span>(optional)</span></summary>
        <div class="camera-actions"><button class="btn secondary" id="startCamera" type="button">Start camera</button><button class="btn secondary" id="stopCamera" type="button" hidden>Stop camera</button><p id="cameraStatus" role="status">Camera is off. Manual entry remains available.</p></div>
        <video id="cameraPreview" width="640" height="480" aria-label="Camera preview" playsinline muted hidden></video>
        <label class="btn text qr-image-label" for="qrImageInput">Scan QR image</label><input class="sr-only" id="qrImageInput" type="file" accept="image/*">
    </details>
</section>
<section class="scanner-result" id="scannerResult" aria-live="polite" aria-labelledby="scanner-result-title"><div class="scanner-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h4M7 13h7"/></svg><h2 id="scanner-result-title">No ID verified</h2><p>Student, card, production, and release details will appear here.</p></div></section>
</div>

<section class="scan-activity compact"><div class="scan-activity-heading"><div><h2>Recent Scans</h2><p>Latest active-term verification attempts at this workstation.</p></div>@if(auth()->user()->hasPermission('audit.view'))<a class="btn text" href="{{ route('logs.index') }}">View All Scan History</a>@endif</div>
<div class="scan-table-wrap"><table class="scan-table"><thead><tr><th>Time</th><th>Match</th><th>Mode</th><th>Result</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->scanned_at?->format('g:i A') }}</td><td>@if($log->student)<strong>{{ $log->student->student_number }}</strong><small>{{ $log->student->full_name }}</small>@elseif($log->card)<strong>Card #{{ $log->card->id }}</strong>@else No match @endif</td><td>{{ ucfirst($log->station_mode) }}</td><td><span class="scan-result-badge is-{{ $log->status }}">{{ ucwords(str_replace('_', ' ', $log->result ?? $log->status)) }}</span></td></tr>@empty<tr><td colspan="4" class="table-empty">No scan activity yet.</td></tr>@endforelse</tbody></table></div>
</section>
<dialog class="release-dialog" id="releaseDialog" aria-labelledby="releaseDialogTitle"><form method="dialog" id="releaseForm">
<h2 id="releaseDialogTitle">Confirm ID release</h2><p>Confirm the claimant was checked. Optional details support later review without collecting documents or signatures.</p>
<label><span>Claimant name <small>(optional)</small></span><input id="claimantName" maxlength="150" autocomplete="off"></label>
<label><span>Relationship or authorization <small>(optional)</small></span><input id="claimantRelationship" maxlength="80" placeholder="Self, parent, authorized representative"></label>
<label><span>Remarks <small>(optional)</small></span><textarea id="releaseRemarks" maxlength="1000" rows="3"></textarea></label>
<label class="release-acknowledgement"><input id="releaseAcknowledged" type="checkbox" required> <span>I confirm the claimant's identity or authorization was checked.</span></label>
<p class="field-error" id="releaseDialogError" role="alert" hidden></p><div class="dialog-actions"><button class="btn text" type="button" id="cancelRelease">Cancel</button><button class="btn primary" type="submit">Release ID</button></div>
</form></dialog>
@endsection
@push('scripts')@vite('resources/js/qr-scanner.js')@endpush