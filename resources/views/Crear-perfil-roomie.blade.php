@extends('layouts.app')

@section('title', 'Roommatch – Publicarme como roomie')

@push('css')
@vite('resources/css/pages/crear-perfil-roomie.css')
@endpush

@section('content')
<div class="cp-page" id="cprPage">
  <!-- Todo el contenido del wizard se genera dinámicamente desde crear-perfil-roomie.js -->
</div>
@endsection

@push('js')
@vite('resources/js/pages/crear-perfil-roomie.js')
@endpush
