@extends('errors.layout')

@section('title', 'Page not found')
@section('code', '404')
@section('heading', "We couldn't find that page")
@section('message', 'The link may be old, or the page may have moved.')

@section('actions')
    <button type="button" class="btn btn-secondary" onclick="history.back()">Go back</button>
    <a href="/" class="btn btn-primary">Go to homepage</a>
@endsection
