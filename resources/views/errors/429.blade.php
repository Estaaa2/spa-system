@extends('errors.layout')

@php
    // Retry-After comes from the throttle middleware; it is a number of seconds.
    $retryAfter = isset($exception) && method_exists($exception, 'getHeaders')
        ? ($exception->getHeaders()['Retry-After'] ?? null)
        : null;
    $wait = is_numeric($retryAfter) && (int) $retryAfter > 0
        ? ((int) $retryAfter >= 90 ? ceil($retryAfter / 60) . ' minutes' : ((int) $retryAfter > 60 ? 'a minute or two' : 'a minute'))
        : 'a minute';
@endphp

@section('title', 'Too many attempts')
@section('code', '429')
@section('heading', 'Too many attempts')
@section('message', "Please wait {$wait}, then try again.")

@section('actions')
    <button type="button" class="btn btn-secondary" onclick="history.back()">Go back</button>
    <a href="/" class="btn btn-primary">Go to homepage</a>
@endsection
