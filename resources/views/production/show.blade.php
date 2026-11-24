@extends('layouts.app')
@section('title', 'Production Record')
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <div class="flex flex-wrap items-center gap-2.5">
            <h1 class="page-title">{{ $card->student->full_name }}</h1>
            <span class="badge status-{{ $card->status->value }}">{{ str($card->status->value)->replace('_', ' ')->title() }}</span>
        </div>
        <p class="page-subtitle">
            <span class="mono font-semibold text-[#334155]">{{ $card->student->student_number }}</span>
            &middot; Card <span class="mono">#{{ $card->id }}</span>
        </p>
    </div>
    <a class="btn secondary" href="{{ route('production.index') }}">Back to Repro Table</a>
</div>

@if($errors->any())
    <div class="alert-banner error" role="alert">{{ $errors->first() }}</div>
@endif

<div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
    <article class="surface overflow-hidden lg:col-span-7">
        <div class="flex items-center justify-between border-b border-[#E2E6EF] px-5 py-4">
            <h2 class="text-base font-semibold text-[#172033]">Physical Card Record</h2>
            <span class="badge badge-brand badge-no-dot">{{ str($card->issuance_type)->title() }}</span>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2">
            @foreach([
                'Issuance type' => str($card->issuance_type)->title(),
                'Academic term' => ($card->academicTerm?->academicYear?->name ?? 'Not linked').' · '.($card->academicTerm?->name ?? $card->semester ?? 'Not recorded'),
                'Program' => $enrollment?->program?->code ?? 'Not recorded',
                'Year level' => $enrollment?->year_level ?? 'Not recorded',
                'Added' => ($card->requested_at ?? $card->created_at)->format('M j, Y g:i A'),
                'Added by' => $card->requester?->name ?? 'Legacy record',
                'Assigned staff' => $card->assignedStaff?->name ?? 'Not assigned',
                'Printing started' => $card->printing_started_at?->format('M j, Y g:i A') ?? 'Not recorded',
                'Printed' => $card->printed_at?->format('M j, Y g:i A') ?? 'Not recorded',
            ] as $dt => $dd)
                <div class="border-b border-[#EEF1F6] px-5 py-3.5">
                    <dt class="text-xs font-medium uppercase tracking-wider text-[#64748B]">{{ $dt }}</dt>
                    <dd class="mt-1 text-sm font-medium text-[#172033] num">{{ $dd }}</dd>
                </div>
            @endforeach
        </dl>

        @if($card->release && (auth()->user()->hasPermission('cards.release') || auth()->user()->hasPermission('audit.view')))
            <section aria-labelledby="release-evidence-title" class="border-t border-[#BBF7D0] bg-[#ECFDF3]/60 p-5">
                <h3 id="release-evidence-title" class="text-xs font-semibold uppercase tracking-wider text-[#15803D]">Release Transaction</h3>
                <dl class="mt-3 grid grid-cols-1 gap-3.5 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-xs text-[#64748B]">Released</dt>
                        <dd class="font-medium text-[#172033] num">{{ $card->release->released_at?->format('M j, Y g:i A') ?? 'Not recorded' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-[#64748B]">Released by</dt>
                        <dd class="font-medium text-[#172033]">{{ $card->release->releasedBy?->name ?? 'Not recorded' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-[#64748B]">Claimant</dt>
                        <dd class="font-medium text-[#172033]">{{ $card->release->claimant_name ?: 'Not provided' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-[#64748B]">Relationship / authorization</dt>
                        <dd class="font-medium text-[#172033]">{{ $card->release->claimant_relationship ?: 'Not provided' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-[#64748B]">Remarks</dt>
                        <dd class="text-[#334155]">{{ $card->release->remarks ?: 'No remarks recorded' }}</dd>
                    </div>
                </dl>
            </section>
        @endif

        @if($card->status === \App\Enums\CardStatus::OnHold)
            <div class="border-t border-[#FECACA] bg-[#FEF2F2] px-5 py-3.5 text-sm text-[#991B1B]">
                <strong class="font-semibold">On Hold:</strong> {{ $card->hold_reason }}
            </div>
        @endif

        @if(auth()->user()->hasPermission('cards.print') || auth()->user()->hasPermission('cards.manage'))
            <div class="flex flex-wrap items-center gap-2.5 border-t border-[#E2E6EF] bg-[#F8F9FC] px-5 py-4">
                @foreach($card->status->allowedTransitions() as $target)
                    @if($target !== \App\Enums\CardStatus::Released && $target !== \App\Enums\CardStatus::Revoked)
                        <form method="POST" action="{{ route('production.transition', $card) }}" class="flex flex-wrap items-center gap-2"
                            onsubmit="return confirm('{{ $target === \App\Enums\CardStatus::Printed ? 'Confirm that this physical student ID has been successfully printed?' : 'Confirm this status change?' }}')">
                            @csrf
                            <input type="hidden" name="status" value="{{ $target->value }}">
                            @if(in_array($target, [\App\Enums\CardStatus::OnHold, \App\Enums\CardStatus::Cancelled]))
                                <input name="reason" type="text" required maxlength="500" placeholder="Documented reason" class="min-h-9 w-56 text-[13px]">
                            @endif
                            <button type="submit" class="btn {{ $target === \App\Enums\CardStatus::Printed ? 'primary' : 'secondary' }}">
                                {{ $target === \App\Enums\CardStatus::Printed ? 'Confirm Printed' : str($target->value)->replace('_', ' ')->title() }}
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
        @endif
    </article>

    <article class="surface overflow-hidden lg:col-span-5">
        <div class="border-b border-[#E2E6EF] px-5 py-4">
            <h2 class="text-base font-semibold text-[#172033]">Production History</h2>
        </div>
        <ol class="divide-y divide-[#EEF1F6]">
            @forelse($card->histories->sortByDesc('occurred_at') as $history)
                <li class="px-5 py-4 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-[#172033]">{{ str($history->new_status->value)->replace('_', ' ')->title() }}</span>
                        <span class="text-xs text-[#64748B] num">{{ $history->occurred_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</span>
                    </div>
                    <p class="mt-0.5 text-xs text-[#64748B]">{{ $history->user?->name ?? 'System' }}</p>
                    @if($history->reason)
                        <p class="mt-1.5 text-xs text-[#334155]">{{ $history->reason }}</p>
                    @endif
                </li>
            @empty
                <li class="px-5 py-10 text-center text-sm text-[#64748B]">No recorded production history.</li>
            @endforelse
        </ol>
    </article>
</div>
@endsection
