@extends('layouts.app')
@section('title', 'Edit ID Holder')
@section('heading', 'Edit ID Holder')
@section('content')
<section class="panel form-panel"><div class="panel-heading"><div><p class="eyebrow">{{ $holder->id_number }}</p><h2>{{ $holder->full_name }}</h2></div></div><form method="POST" action="{{ route('holders.update', $holder) }}">@csrf @method('PUT') @include('holders._form', ['submitLabel' => 'Save changes'])</form></section>
@endsection
