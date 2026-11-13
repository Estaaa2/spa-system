@extends('errors.layout')

{{-- Also used before the app boots: php artisan down --render="errors::503" --}}
@section('title', 'Maintenance')
@section('code', '503')
@section('heading', "We're doing maintenance")
@section('message', 'Levictas will be back shortly. Thank you for your patience.')

@section('actions')
    <button type="button" class="btn btn-primary" onclick="location.reload()">Try again</button>
@endsection
