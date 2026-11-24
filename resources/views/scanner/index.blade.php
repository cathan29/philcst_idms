@extends('layouts.app')
@section('title', 'ID Verification')
@section('content')
<div class="mb-5 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">ID Verification</h1>
        <p class="page-subtitle">Enter a student number or scan with a USB reader to inspect enrollment, printing, and release status.</p>
    </div>

    <div class="scanner-modes" role="radiogroup" aria-label="Scanner mode">
        @foreach($modes as $value => $label)
            <button type="button" class="scanner-mode {{ $loop->first ? 'active' : '' }}" data-mode="{{ $value }}" role="radio" aria-checked="{{ $loop->first ? 'true' : 'false' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>
</div>

<div id="scannerStation"
    data-verify-url="{{ route('scanner.verify') }}"
    data-transition-template="{{ route('scanner.transition', ['card' => '__CARD__']) }}"
    class="grid grid-cols-1 gap-5 lg:grid-cols-12 lg:items-start">

    {{-- Left: Manual / USB scanner input workspace (~42%) --}}
    <section class="surface flex flex-col gap-4 p-5 lg:col-span-5" aria-labelledby="scanner-workspace-title">
        <div class="border-b border-[#E2E6ED] pb-3.5">
            <h2 id="scanner-workspace-title" class="text-[15px] font-semibold text-[#111827]">Ready for student number</h2>
            <p id="scannerState" role="status" class="mt-0.5 text-xs text-[#6B7280]">Enter or scan a student number, then press Enter.</p>
        </div>

        <form id="scannerForm" class="space-y-3">
            <label for="scanPayload" class="block text-[13px] font-medium text-[#374151]">Student number or card identifier</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input id="scanPayload" name="payload" type="text" maxlength="512" autocomplete="off" inputmode="text"
                    placeholder="Enter student number" required autofocus
                    class="mono h-10 flex-1 text-sm font-medium">
                <button class="btn primary h-10 px-4 shrink-0" id="verifyButton" type="submit" disabled>
                    <span>Verify ID</span>
                </button>
            </div>
            <p class="text-xs leading-relaxed text-[#6B7280]">USB scanners work like a keyboard. Verification is read-only until an authorized action is explicitly confirmed.</p>
        </form>

        <details id="cameraScanner" class="rounded-md border border-[#E2E6ED] bg-[#F9FAFB] px-3.5 py-2.5">
            <summary class="cursor-pointer text-[13px] font-medium text-[#374151] select-none">
                Camera QR scanner <span class="font-normal text-[#6B7280]">(optional)</span>
            </summary>
            <div class="mt-3 space-y-2.5 border-t border-[#E2E6ED] pt-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button class="btn secondary btn-sm" id="startCamera" type="button">Start camera</button>
                    <button class="btn secondary btn-sm" id="stopCamera" type="button" hidden>Stop camera</button>
                    <label class="btn text btn-sm cursor-pointer" for="qrImageInput">Scan QR image</label>
                    <input class="sr-only" id="qrImageInput" type="file" accept="image/*">
                </div>
                <p id="cameraStatus" role="status" class="text-xs text-[#6B7280]">Camera is off. Manual entry remains available.</p>
                <video id="cameraPreview" width="640" height="480" aria-label="Camera preview" playsinline muted hidden
                    class="w-full rounded-md border border-[#E2E6ED] bg-slate-950"></video>
            </div>
        </details>
    </section>

    {{-- Right: Verification result workspace (~58%) --}}
    <section class="surface flex min-h-[272px] flex-col justify-center p-5 lg:col-span-7" id="scannerResult" aria-live="polite" aria-labelledby="scanner-result-title">
        <div class="mx-auto max-w-sm py-6 text-center">
            <h2 id="scanner-result-title" class="text-[15px] font-semibold text-[#111827]">No ID verified</h2>
            <p class="mt-1 text-xs leading-relaxed text-[#6B7280]">Student enrollment, printing status, release records, and reprint history will appear here once an ID is verified.</p>
        </div>
    </section>
</div>

{{-- Recent Scans --}}
<section class="surface mt-5 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E6ED] px-5 py-3.5">
        <div>
            <h2 class="text-[15px] font-semibold text-[#111827]">Recent Scans</h2>
            <p class="text-xs text-[#6B7280]">Latest active-term verification attempts at this workstation.</p>
        </div>
        @if(auth()->user()->hasPermission('audit.view'))
            <a class="text-xs font-medium text-[#3F1B68] hover:underline" href="{{ route('logs.index') }}">View All Scan History</a>
        @endif
    </div>
    <div class="data-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Student / Match</th>
                    <th>Mode</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    @php
                        $res = $log->result ?? $log->status;
                        $resultBadge = match ($res) {
                            'released', 'accepted' => 'badge-success',
                            'printed', 'ready_for_release' => 'badge-info',
                            'duplicate', 'no_card', 'pending', 'for_printing' => 'badge-warning',
                            default => 'badge-danger',
                        };
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap text-[13px] text-[#6B7280] num">{{ $log->scanned_at?->format('g:i A') }}</td>
                        <td>
                            @if($log->student)
                                <span class="id-chip">{{ $log->student->student_number }}</span>
                                <span class="ml-2 font-medium text-[#101828]">{{ $log->student->full_name }}</span>
                            @elseif($log->card)
                                <span class="id-chip">Card #{{ $log->card->id }}</span>
                            @else
                                <span class="text-[#667085]">No match</span>
                            @endif
                        </td>
                        <td class="text-sm text-[#4B5563]">{{ ucfirst($log->station_mode) }}</td>
                        <td>
                            <span class="badge {{ $resultBadge }}">{{ ucwords(str_replace('_', ' ', $res)) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-10 text-center text-sm text-[#6B7280]">No scan activity yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<dialog class="release-dialog" id="releaseDialog" aria-labelledby="releaseDialogTitle">
    <form method="dialog" id="releaseForm" class="space-y-4">
        <div class="border-b border-[#E2E6ED] pb-3">
            <h2 id="releaseDialogTitle" class="text-[15px] font-semibold text-[#111827]">Confirm ID release</h2>
            <p class="mt-0.5 text-xs text-[#6B7280]">Confirm the claimant was checked. Optional details support later review without collecting documents or signatures.</p>
        </div>
        <label class="block">
            <span class="mb-1.5 block text-[13px] font-medium text-[#374151]">Claimant name <span class="font-normal text-[#9CA3AF]">(optional)</span></span>
            <input id="claimantName" type="text" maxlength="150" autocomplete="off">
        </label>
        <label class="block">
            <span class="mb-1.5 block text-[13px] font-medium text-[#374151]">Relationship or authorization <span class="font-normal text-[#9CA3AF]">(optional)</span></span>
            <input id="claimantRelationship" type="text" maxlength="80" placeholder="Self, parent, authorized representative">
        </label>
        <label class="block">
            <span class="mb-1.5 block text-[13px] font-medium text-[#374151]">Remarks <span class="font-normal text-[#9CA3AF]">(optional)</span></span>
            <textarea id="releaseRemarks" maxlength="1000" rows="2"></textarea>
        </label>
        <label class="flex cursor-pointer items-start gap-2.5 rounded-md border border-[#E2E6ED] bg-[#F9FAFB] p-3 text-[13px] text-[#374151]">
            <input id="releaseAcknowledged" type="checkbox" required class="mt-0.5">
            <span>I confirm the claimant's identity or authorization was checked.</span>
        </label>
        <p class="text-xs font-medium text-[#991B1B]" id="releaseDialogError" role="alert" hidden></p>
        <div class="flex items-center justify-end gap-2 border-t border-[#E2E6ED] pt-3.5">
            <button class="btn secondary" type="button" id="cancelRelease">Cancel</button>
            <button class="btn primary" type="submit">Release ID</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
@vite('resources/js/qr-scanner.js')
@endpush
