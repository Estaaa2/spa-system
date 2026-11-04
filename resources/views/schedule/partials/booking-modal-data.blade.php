{{--
    The data-* attributes openAppointmentModal() reads, for one booking ($b).
    Shared by the calendar cards and the Reschedule Requests list (sidebar & RBAC
    rework Phase 4.1), so both open the same modal with the same details.
    $b needs resolved_treatment (ScheduleController) and latestRescheduleRequest.
--}}
@php
    $mdStart  = \Carbon\Carbon::parse($b->start_time);
    $mdEnd    = \Carbon\Carbon::parse($b->end_time);
    $mdMins   = max(((int) $mdEnd->format('H') * 60 + (int) $mdEnd->format('i')) - ((int) $mdStart->format('H') * 60 + (int) $mdStart->format('i')), 1);
    $mdHours  = intdiv($mdMins, 60);
    $mdRest   = $mdMins % 60;
    $mdDur    = trim(($mdHours ? $mdHours . ' hr ' : '') . ($mdRest ? $mdRest . ' min' : '')) ?: '0 min';
    $mdResch  = $b->latestRescheduleRequest;
@endphp
data-booking-id="{{ $b->id }}"
data-customer="{{ $b->customer_name ?? 'Walk-in' }}"
data-service="{{ $b->service_type_label }}"
data-treatment="{{ $b->resolved_treatment ?? $b->treatment_label }}"
data-appointment-date="{{ \Carbon\Carbon::parse($b->appointment_date)->format('F d, Y') }}"
data-start-time="{{ $mdStart->format('g:i A') }}"
data-end-time="{{ $mdEnd->format('g:i A') }}"
data-duration="{{ $mdDur }}"
data-status="{{ \App\Models\Booking::statusLabel($b->status) }}"
data-status-key="{{ $b->status }}"
data-reschedule-id="{{ $mdResch?->id }}"
data-reschedule-status="{{ $mdResch?->status }}"
data-reschedule-date="{{ $mdResch?->requested_date?->format('F j, Y') }}"
data-reschedule-time="{{ $mdResch ? \Carbon\Carbon::parse($mdResch->requested_time)->format('g:i A') : '' }}"
data-reschedule-reason="{{ $mdResch?->reason }}"
data-reschedule-rejection="{{ $mdResch?->rejection_reason }}"
