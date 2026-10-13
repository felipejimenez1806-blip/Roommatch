@extends('layouts.app')

@section('title', 'Roommatch – Cita agendada')

@push('css')
@vite('resources/css/pages/confirmacion-cita.css')
@endpush

@section('content')

<div class="conf-page" id="confPage">
  <!-- Todo el contenido se genera dinámicamente desde confirmacion-cita.js -->
</div>

@endsection

@push('js')
@vite('resources/js/pages/confirmacion-cita.js')
@endpush
