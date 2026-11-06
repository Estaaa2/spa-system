{{--
    A customer nav/footer action (My Appointments, My Schedule, Profile).
    Those windows are modals that only exist on the landing page, so:
    - on / it is a button that opens the modal in place ($action);
    - on any other page it links to /?open=<name>, and welcome.js opens the
      modal once the landing page loads (landing footer Phase 1, Q1-A).
--}}
@props(['open', 'action'])
@if (request()->routeIs('landing.page'))
    <button type="button" onclick="{{ $action }}" aria-haspopup="dialog" {{ $attributes }}>{{ $slot }}</button>
@else
    <a href="{{ url('/') }}?open={{ $open }}" {{ $attributes }}>{{ $slot }}</a>
@endif
