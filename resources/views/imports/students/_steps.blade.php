<nav class="import-steps import-steps-four" aria-label="Import progress">
@foreach([1 => 'Term & Upload', 2 => 'Map Columns', 3 => 'Review & Validate', 4 => 'Confirm Import'] as $step => $label)
    @php($complete = $step < $current)
    <div class="import-step {{ $complete ? 'complete' : ($step === $current ? 'active' : '') }}" @if($step === $current) aria-current="step" @endif><b>{{ $complete ? '✓' : $step }}</b><span>{{ $label }}</span></div>
@endforeach
</nav>