@extends('errors.layout')

@section('title', 'Page expired')
@section('code', '419')
@section('heading', 'This page expired')
@section('message', 'For your security, a form stops working when its page stays open too long. Go back, refresh the page, and try again.')

@section('actions')
    <button type="button" class="btn btn-secondary" onclick="history.back()">Go back</button>
    <a href="/" class="btn btn-primary">Go to homepage</a>
@endsection
