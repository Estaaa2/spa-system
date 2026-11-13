@extends('errors.layout')

{{-- Any other 4xx (405, 413 upload too large, …) without its own page. --}}
@section('title', "Request couldn't be completed")
@section('code', isset($exception) && method_exists($exception, 'getStatusCode') ? (string) $exception->getStatusCode() : '400')
@section('heading', "We couldn't complete that request")
@section('message', 'Please go back and try again. If you were uploading a file, try a smaller one.')

@section('actions')
    <button type="button" class="btn btn-secondary" onclick="history.back()">Go back</button>
    <a href="/" class="btn btn-primary">Go to homepage</a>
@endsection
