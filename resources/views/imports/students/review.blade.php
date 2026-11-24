@extends('layouts.app')
@section('title', 'Review Student Import')
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Review &amp; Validate</h1>
        <p class="page-subtitle">Review actual validation results before selecting an import mode. No records have been written yet.</p>
    </div>
    <a class="btn secondary" href="{{ route('imports.students.mapping') }}">&larr; Back to Mapping</a>
</div>

@include('imports.students._steps', ['current' => 3])

<div class="mb-5 rounded-lg border border-[#E2E6EF] bg-white px-5 py-3 text-sm">
    <span class="font-semibold text-[#172033]">Selected term:</span>
    <span class="ml-1.5 text-[#475569] num">{{ $preview['term']['label'] }} &middot; {{ empty($preview['context']['coverage_program_id']) ? 'All programs' : 'Specific program' }}</span>
</div>

@if($errors->any())
    <div class="alert-banner error" role="alert">{{ $errors->first() }}</div>
@endif

<section class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
    @foreach([
        'Total Rows' => $preview['summary']['total'],
        'Valid Rows' => $preview['summary']['valid'],
        'New Students' => $preview['summary']['new'],
        'Existing' => $preview['summary']['existing'],
        'Changed' => $preview['summary']['changed'],
        'Duplicates' => $preview['summary']['duplicates'],
        'Invalid' => $preview['summary']['invalid'],
    ] as $label => $count)
        <div class="surface p-4">
            <p class="text-xs font-medium text-[#64748B]">{{ $label }}</p>
            <p class="mt-1 text-2xl font-bold text-[#172033] num">{{ number_format($count) }}</p>
        </div>
    @endforeach
</section>

<section class="surface overflow-hidden">
    <div class="border-b border-[#E2E6EF] px-5 py-4">
        <h2 class="text-base font-semibold text-[#172033]">{{ $preview['original_filename'] }}</h2>
        <p class="text-xs text-[#64748B]">Previewing the first {{ count($preview['rows']) }} parsed rows.</p>
    </div>

    <div class="data-table-wrap max-h-96 overflow-y-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Row</th>
                    <th>Student Number</th>
                    <th>Full Name</th>
                    <th>Program</th>
                    <th>Year Level</th>
                    <th>Validation Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @foreach($preview['rows'] as $row)
                    <tr>
                        <td class="num text-[#64748B]">{{ $row['number'] }}</td>
                        <td class="mono font-semibold">{{ $row['data']['student_number'] }}</td>
                        <td class="font-medium">{{ trim($row['data']['first_name'].' '.$row['data']['middle_name'].' '.$row['data']['last_name']) }}</td>
                        <td>{{ $row['data']['program'] ?: '-' }}</td>
                        <td class="num">{{ $row['data']['year_level'] ?: '-' }}</td>
                        <td>
                            <span class="badge status-{{ $row['state'] === 'invalid' ? 'review' : ($row['state'] === 'new' ? 'new' : 'existing') }}">
                                {{ str($row['state'])->title() }}
                            </span>
                        </td>
                        <td class="text-xs text-[#475569]">{{ implode('; ', $row['errors']) ?: 'Ready for import' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('imports.students.confirm') }}" class="border-t border-[#E2E6EF] bg-[#F8F9FC] p-6">
        @csrf
        <h3 class="text-base font-semibold text-[#172033]">Select Import Mode</h3>
        <p class="mt-0.5 text-sm text-[#64748B]">Existing student records are never deleted. ID card and issuance history remain unchanged.</p>

        <div class="mt-4 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-[#CBD3E1] bg-white p-4 transition-colors hover:border-[#6D28A8]">
                <input type="radio" name="mode" value="insert_only" required class="mt-0.5">
                <span class="text-sm">
                    <strong class="block font-semibold text-[#172033]">Insert new only</strong>
                    <span class="mt-0.5 block text-xs text-[#64748B]">Create missing term enrollments; do not overwrite existing identities or term records.</span>
                </span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-[#CBD3E1] bg-white p-4 transition-colors hover:border-[#6D28A8]">
                <input type="radio" name="mode" value="insert_or_update" required class="mt-0.5">
                <span class="text-sm">
                    <strong class="block font-semibold text-[#172033]">Insert and update approved records</strong>
                    <span class="mt-0.5 block text-xs text-[#64748B]">Update changed fields for matching student numbers.</span>
                </span>
            </label>
        </div>

        <div class="mt-5 flex items-center justify-end gap-2.5">
            <a class="btn secondary" href="{{ route('imports.students.mapping') }}">Back</a>
            <button class="btn primary" type="submit">Continue to Confirmation</button>
        </div>
    </form>
</section>
@endsection
