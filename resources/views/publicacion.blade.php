@extends('layouts.app')

@section('title', 'Roommatch – Detalle de publicación')

@push('css')
@vite('resources/css/pages/publicacion.css')
@endpush

@section('content')

<div class="pub-page" id="pubPage" data-id="{{ $id }}">
  <!-- Todo el contenido se genera dinámicamente desde publicacion.js -->
</div>

@endsection

@push('js')
@vite('resources/js/pages/publicacion.js')
@endpush
