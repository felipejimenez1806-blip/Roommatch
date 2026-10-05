@extends('layouts.app')

@section('title', 'Roommatch – Agendar cita')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/cita-roomie.css') }}" />
@endpush

@section('content')

<div class="cr-page" id="crPage" data-id="{{ $id }}">
  <!-- Todo el contenido se genera dinámicamente desde cita-roomie.js -->
</div>

@endsection

@push('js')
<script src="{{ asset('Js/cita-roomie.js') }}"></script>
@endpush
