@extends('layouts.app')

@section('title', 'Roommatch – Agendar cita')

@push('css')
@vite('resources/css/pages/cita-roomie.css')
@endpush

@section('content')

<div class="cr-page" id="crPage" data-id="{{ $id }}">
  <!-- Todo el contenido se genera dinámicamente desde cita-roomie.js -->
</div>

@endsection

@push('js')
@vite('resources/js/pages/cita-roomie.js')
@endpush
