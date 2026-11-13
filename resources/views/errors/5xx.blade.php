@extends('errors.layout')

{{-- Any other 5xx without its own page. --}}
@section('title', 'Something went wrong')
@section('code', isset($exception) && method_exists($exception, 'getStatusCode') ? (string) $exception->getStatusCode() : '500')
@section('heading', 'Something went wrong on our side')
@section('message', 'This was not caused by anything you did. Please try again in a moment.')

@section('actions')
    <button type="button" class="btn btn-secondary" onclick="history.back()">Go back</button>
    <a href="/" class="btn btn-primary">Go to homepage</a>
@endsection
