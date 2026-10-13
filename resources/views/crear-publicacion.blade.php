@extends('layouts.app')

@section('title', 'Roommatch – Crear publicación')

@push('css')
@vite('resources/css/pages/crear-publicacion.css')
@endpush

@section('content')
<div class="cp-page" id="cpPage">
  <!-- Todo el contenido del wizard se genera dinámicamente desde crear-publicacion.js -->
</div>
@endsection

@push('js')
@vite('resources/js/pages/crear-publicacion.js')
@endpush
