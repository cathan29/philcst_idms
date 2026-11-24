@extends('layouts.app')
@section('title', 'Academic Settings')
@section('content')
@php
    $allTerms = $years->flatMap(fn ($year) => $year->terms->map(fn ($term) => ['year' => $year, 'term' => $term]));
    $currentActive = $allTerms->first(fn ($item) => $item['term']->status === 'active');
@endphp

<div class="mb-6">
    <h1 class="page-title">Academic Settings</h1>
    <p class="page-subtitle">Manage the globally active academic term, academic years, semester schedule, and official programs.</p>
</div>

@if($errors->any())
    <div class="alert-banner error" role="alert">{{ $errors->first() }}</div>
@endif

{{-- Global Active Academic Term Switcher --}}
<section class="surface mb-6 p-6" aria-labelledby="active-term-switcher-title">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 id="active-term-switcher-title" class="text-base font-semibold text-[#172033]">Global Active Academic Term</h2>
            <p class="mt-0.5 text-sm text-[#64748B]">All operational workspaces automatically scope to the active academic term configured here.</p>
        </div>
        @if($currentActive)
            <span class="badge badge-success num">
                Active: AY {{ $currentActive['year']->name }} &middot; {{ $currentActive['term']->name }}
            </span>
        @else
            <span class="badge badge-warning">No active term configured</span>
        @endif
    </div>

    @if($allTerms->isNotEmpty())
        <div class="mt-5 flex flex-wrap items-end gap-3 border-t border-[#E2E6EF] pt-5">
            <label class="block min-w-[260px] flex-1 max-w-md">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Switch active academic term</span>
                <select id="quickTermSwitcher" class="num">
                    @foreach($allTerms as $item)
                        <option value="{{ route('academic-terms.update', $item['term']) }}"
                            data-name="{{ $item['term']->name }}"
                            data-starts="{{ $item['term']->starts_on?->format('Y-m-d') }}"
                            data-ends="{{ $item['term']->ends_on?->format('Y-m-d') }}"
                            @selected($item['term']->status === 'active')>
                            AY {{ $item['year']->name }} &middot; {{ $item['term']->name }} ({{ $item['term']->status === 'active' ? 'Active' : 'Closed' }})
                        </option>
                    @endforeach
                </select>
            </label>
            <form method="POST" id="quickActivateForm" action="{{ route('academic-terms.update', ($currentActive ?? $allTerms->first())['term']) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="name" id="quickTermName" value="{{ ($currentActive ?? $allTerms->first())['term']->name }}">
                <input type="hidden" name="starts_on" id="quickTermStarts" value="{{ ($currentActive ?? $allTerms->first())['term']->starts_on?->format('Y-m-d') }}">
                <input type="hidden" name="ends_on" id="quickTermEnds" value="{{ ($currentActive ?? $allTerms->first())['term']->ends_on?->format('Y-m-d') }}">
                <input type="hidden" name="status" value="active">
                <button type="submit" class="btn primary">Set as Active Term</button>
            </form>
        </div>
    @endif
</section>

{{-- Add Academic Year & Add Academic Term --}}
<div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
    <section class="surface p-6 lg:col-span-5">
        <h2 class="text-base font-semibold text-[#172033]">Add Academic Year</h2>
        <p class="mt-0.5 text-sm text-[#64748B]">Create an academic year before adding its semesters.</p>
        <form method="POST" action="{{ route('academic-years.store') }}" class="mt-5 space-y-4">
            @csrf
            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Academic Year</span>
                <input name="name" type="text" required maxlength="20" placeholder="2026-2027" class="num">
            </label>
            <div class="grid grid-cols-2 gap-3.5">
                <label class="block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Starts on</span>
                    <input type="date" name="starts_on">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Ends on</span>
                    <input type="date" name="ends_on">
                </label>
            </div>
            <div class="pt-1">
                <button class="btn secondary" type="submit">Add Academic Year</button>
            </div>
        </form>
    </section>

    <section class="surface p-6 lg:col-span-7">
        <h2 class="text-base font-semibold text-[#172033]">Add Academic Term</h2>
        <p class="mt-0.5 text-sm text-[#64748B]">Semester codes are fixed: 0 = Summer, 1 = First Semester, 2 = Second Semester.</p>
        <form method="POST" action="{{ route('academic-terms.store') }}" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
            @csrf
            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Academic Year</span>
                <select name="academic_year_id" required>
                    @foreach($years as $year)
                        <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Semester</span>
                <select name="semester" required>
                    @foreach($semesters as $semester)
                        <option value="{{ $semester->value }}" @selected((string) old('semester') === (string) $semester->value)>{{ $semester->value }} &middot; {{ $semester->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Starts on</span>
                <input type="date" name="starts_on" value="{{ old('starts_on') }}">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Ends on</span>
                <input type="date" name="ends_on" value="{{ old('ends_on') }}">
            </label>
            <label class="block">
                <span class="mb-1.5 block text-[13.5px] font-medium text-[#334155]">Initial status</span>
                <select name="status">
                    <option value="closed">Closed</option>
                    <option value="active">Active</option>
                </select>
            </label>
            <div class="flex items-end">
                <button class="btn primary" type="submit" @disabled($years->isEmpty())>Add Term</button>
            </div>
        </form>
    </section>
</div>

{{-- Configured Academic Terms by Year --}}
<div class="mb-6 space-y-5">
    @foreach($years as $year)
        <section class="surface overflow-hidden">
            <div class="flex items-center justify-between border-b border-[#E2E6EF] px-5 py-4">
                <h2 class="text-base font-semibold text-[#172033] num">AY {{ $year->name }}</h2>
                <span class="text-xs font-medium text-[#64748B] num">{{ $year->terms->count() }} {{ $year->terms->count() === 1 ? 'term' : 'terms' }}</span>
            </div>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Semester</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($year->terms as $term)
                            @php($formId = 'term-form-'.$term->id)
                            <tr>
                                <td>
                                    <span class="font-semibold text-[#172033]">{{ $term->name }}</span>
                                    <span class="ml-2 mono text-xs text-[#64748B]">{{ $term->code }}</span>
                                    <input type="hidden" form="{{ $formId }}" name="name" value="{{ $term->name }}">
                                </td>
                                <td>
                                    <input form="{{ $formId }}" aria-label="{{ $term->name }} start date" type="date" name="starts_on"
                                        value="{{ $term->starts_on?->format('Y-m-d') }}" class="min-h-9 max-w-[170px] text-[13px] num">
                                </td>
                                <td>
                                    <input form="{{ $formId }}" aria-label="{{ $term->name }} end date" type="date" name="ends_on"
                                        value="{{ $term->ends_on?->format('Y-m-d') }}" class="min-h-9 max-w-[170px] text-[13px] num">
                                </td>
                                <td>
                                    <select form="{{ $formId }}" aria-label="{{ $term->name }} status" name="status" class="min-h-9 max-w-[130px] text-[13px]">
                                        <option value="active" @selected($term->status === 'active')>Active</option>
                                        <option value="closed" @selected($term->status === 'closed')>Closed</option>
                                    </select>
                                </td>
                                <td class="text-right">
                                    <form id="{{ $formId }}" method="POST" action="{{ route('academic-terms.update', $term) }}" class="inline">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="btn secondary btn-sm">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-sm text-[#64748B]">No terms configured for this year.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>

{{-- Programs --}}
<section class="surface overflow-hidden">
    <div class="border-b border-[#E2E6EF] px-5 py-4">
        <h2 class="text-base font-semibold text-[#172033]">Official Programs</h2>
        <p class="text-xs text-[#64748B]">Program codes and names validated during CSV enrollment imports.</p>
    </div>

    <form method="POST" action="{{ route('programs.store') }}" class="grid grid-cols-1 items-end gap-3.5 border-b border-[#E2E6EF] bg-[#F8F9FC] px-5 py-4 sm:grid-cols-12">
        @csrf
        <label class="block sm:col-span-3">
            <span class="mb-1.5 block text-xs font-medium text-[#475569]">Program code</span>
            <input name="code" type="text" required maxlength="30" placeholder="BSIT">
        </label>
        <label class="block sm:col-span-7">
            <span class="mb-1.5 block text-xs font-medium text-[#475569]">Program name</span>
            <input name="name" type="text" required maxlength="150" placeholder="Bachelor of Science in Information Technology">
        </label>
        <div class="sm:col-span-2">
            <button class="btn primary w-full" type="submit">Add Program</button>
        </div>
    </form>

    @if($programs->isNotEmpty())
        <div class="data-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($programs as $program)
                        @php($progFormId = 'program-form-'.$program->id)
                        <tr>
                            <td class="mono font-semibold text-[#172033]">{{ $program->code }}</td>
                            <td>
                                <input form="{{ $progFormId }}" aria-label="{{ $program->code }} program name" name="name" type="text" value="{{ $program->name }}" required maxlength="150" class="min-h-9 text-[13px]">
                            </td>
                            <td>
                                <select form="{{ $progFormId }}" aria-label="{{ $program->code }} program status" name="is_active" class="min-h-9 max-w-[130px] text-[13px]">
                                    <option value="1" @selected($program->is_active)>Active</option>
                                    <option value="0" @selected(!$program->is_active)>Inactive</option>
                                </select>
                            </td>
                            <td class="text-right">
                                <form id="{{ $progFormId }}" method="POST" action="{{ route('programs.update', $program) }}" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <button type="submit" class="btn secondary btn-sm">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection

@push('scripts')
<script>
(() => {
    const switcher = document.getElementById('quickTermSwitcher');
    const form = document.getElementById('quickActivateForm');
    if (!switcher || !form) return;
    switcher.addEventListener('change', () => {
        const option = switcher.options[switcher.selectedIndex];
        form.action = option.value;
        document.getElementById('quickTermName').value = option.dataset.name || '';
        document.getElementById('quickTermStarts').value = option.dataset.starts || '';
        document.getElementById('quickTermEnds').value = option.dataset.ends || '';
    });
})();
</script>
@endpush
