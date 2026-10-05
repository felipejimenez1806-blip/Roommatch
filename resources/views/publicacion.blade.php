@extends('layouts.app')

@section('title', 'Roommatch – Detalle de publicación')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/publicacion.css') }}" />
@endpush

@section('content')

<div class="pub-page" id="pubPage" data-id="{{ $id }}">
  <!-- Todo el contenido se genera dinámicamente desde publicacion.js -->
</div>

@endsection

@push('js')
<script src="{{ asset('Js/publicacion.js') }}"></script>
@endpush
