@extends('errors.layout')

@section('title', 'No access')
@section('code', '403')
@section('heading', "You don't have access to this page")
@section('message', "This page isn't part of your role or branch.")
@section('hint', 'If you think you should have access, ask your spa owner or manager.')

@section('actions')
    <button type="button" class="btn btn-secondary" onclick="history.back()">Go back</button>
    <a href="/" class="btn btn-primary">Go to homepage</a>
@endsection
