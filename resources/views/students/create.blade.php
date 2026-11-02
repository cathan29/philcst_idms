@extends('layouts.app')
@section('title', 'Add Student')
@section('heading', 'Add Student')
@section('content')
<section class="panel"><form method="POST" action="{{ route('students.store') }}" style="padding:1.25rem">@csrf @include('students._form', ['submitLabel' => 'Create student'])</form></section>
@endsection
