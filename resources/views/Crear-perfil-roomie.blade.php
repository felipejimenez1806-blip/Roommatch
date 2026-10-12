@extends('layouts.app')

@section('title', 'Roommatch – Publicarme como roomie')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/crear-perfil-roomie.css') }}" />
@endpush

@section('content')
<div class="cp-page" id="cprPage">
  <!-- Todo el contenido del wizard se genera dinámicamente desde crear-perfil-roomie.js -->
</div>
@endsection

@push('js')
@vite('resources/js/pages/crear-perfil-roomie.js')
@endpush
