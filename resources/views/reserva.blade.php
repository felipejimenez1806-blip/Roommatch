@extends('layouts.app')

@section('title', 'Roommatch – Solicitar reserva')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/reserva.css') }}" />
@endpush

@section('content')

<div class="res-page" id="resPage" data-id="{{ $id }}">
  <!-- Todo el contenido se genera dinámicamente desde reserva.js -->
</div>

@endsection

@push('js')
<script src="{{ asset('Js/reserva.js') }}"></script>
@endpush
