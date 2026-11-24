@extends('layouts.app')
@section('title', 'Edit Student')
@section('heading', 'Edit Student')
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="page-title">Edit Student Identity</h1>
        <p class="page-subtitle">Correct student identity details. Enrollment records remain managed via CSV import.</p>
    </div>
    <a class="btn secondary" href="{{ route('students.show', $student) }}">Back to Student</a>
</div>

<section class="surface max-w-2xl p-6">
    <form method="POST" action="{{ route('students.update', $student) }}">
        @csrf
        @method('PUT')
        @include('students._form', ['submitLabel' => 'Save changes'])
    </form>
</section>
@endsection
