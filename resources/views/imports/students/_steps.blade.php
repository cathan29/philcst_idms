<nav aria-label="Import progress" class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
    @foreach([1 => 'Term & Upload', 2 => 'Map Columns', 3 => 'Review & Validate', 4 => 'Confirm Import'] as $step => $label)
        @php($complete = $step < $current)
        @php($active = $step === $current)
        <div @if($active) aria-current="step" @endif
            class="flex items-center gap-2.5 rounded-lg border px-3.5 py-2.5 text-[13px] font-medium {{ $active ? 'border-[#6D28A8] bg-[#F3ECFA] text-[#4F1D7A]' : ($complete ? 'border-[#BBF7D0] bg-[#ECFDF3] text-[#15803D]' : 'border-[#E2E6EF] bg-white text-[#64748B]') }}">
            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold num {{ $active ? 'bg-[#6D28A8] text-white' : ($complete ? 'bg-[#15803D] text-white' : 'bg-[#EEF1F6] text-[#64748B]') }}">
                {{ $complete ? '✓' : $step }}
            </span>
            <span class="truncate">{{ $label }}</span>
        </div>
    @endforeach
</nav>
