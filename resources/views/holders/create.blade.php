@extends('layouts.app')
@section('title', 'Add ID Holder')
@section('heading', 'Add ID Holder')
@section('content')
<section class="panel form-panel"><div class="panel-heading"><div><p class="eyebrow">New record</p><h2>Holder information</h2></div></div><form method="POST" action="{{ route('holders.store') }}">@csrf @include('holders._form', ['submitLabel' => 'Create ID holder'])</form></section>
@endsection
