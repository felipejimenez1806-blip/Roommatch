@extends('layouts.app')

@section('title', 'Roommatch – Perfil de roomie')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/perfil-roomie.css') }}" />
@endpush

@section('content')

<div class="rp-page" id="rpPage" data-id="{{ $id }}">
  <!-- Todo el contenido se genera dinámicamente desde perfil-roomie.js -->
</div>

@endsection

@push('js')
<script src="{{ asset('Js/perfil-roomie.js') }}"></script>
@endpush
