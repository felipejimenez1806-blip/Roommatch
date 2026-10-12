@extends('layouts.app')

@section('title', 'Roommatch – Solicitud enviada')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/confirmacion.css') }}" />
@endpush

@section('content')

<div class="conf-page" id="confPage">
  <!-- Todo el contenido se genera dinámicamente desde confirmacion.js -->
</div>

@endsection

@push('js')
@vite('resources/js/pages/confirmacion.js')
@endpush
