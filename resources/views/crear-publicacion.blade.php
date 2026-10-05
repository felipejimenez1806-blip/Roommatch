@extends('layouts.app')

@section('title', 'Roommatch – Crear publicación')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/crear-publicacion.css') }}" />
@endpush

@section('content')
<div class="cp-page" id="cpPage">
  <!-- Todo el contenido del wizard se genera dinámicamente desde crear-publicacion.js -->
</div>
@endsection

@push('js')
<script src="{{ asset('Js/crear-publicacion.js') }}"></script>
@endpush
