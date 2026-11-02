@extends('layouts.app')
@section('title', 'Edit Student')
@section('heading', 'Edit Student')
@section('content')
<section class="panel"><form method="POST" action="{{ route('students.update', $student) }}" style="padding:1.25rem">@csrf @method('PUT') @include('students._form', ['submitLabel' => 'Save changes'])</form></section>
@endsection
