@extends('layouts.app')

@section('title', 'Roommatch – Mis publicaciones')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/mis-publicaciones.css') }}" />
@endpush

@section('content')
<div class="mp-page" id="mpPage">
  <!-- Todo el contenido se genera dinámicamente desde mis-publicaciones.js -->
</div>
@endsection

@push('js')
@vite('resources/js/pages/mis-publicaciones.js')
@endpush
