@extends('layouts.app')

@section('title', 'Roommatch – Cita agendada')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/confirmacion-cita.css') }}" />
@endpush

@section('content')

<div class="conf-page" id="confPage">
  <!-- Todo el contenido se genera dinámicamente desde confirmacion-cita.js -->
</div>

@endsection

@push('js')
<script src="{{ asset('Js/confirmacion-cita.js') }}"></script>
@endpush
