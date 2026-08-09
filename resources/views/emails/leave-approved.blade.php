{{-- DESTINATION: resources/views/emails/leave-approved.blade.php (new file) --}}
{{--
    CORRECTED AGAIN — this now matches your actual reschedule-approved.blade.php
    exactly: @component('mail::message') directive syntax (not <x-mail::message>
    tags), a @component('mail::panel') block for the structured details, bold
    label lines, no CTA button, same closing "Thanks," + app name pattern.
--}}
@component('mail::message')
# Leave Request Approved

Hi **{{ $leaveRequest->user->name }}**,

Your leave request has been **approved**!

@component('mail::panel')
**Type:** {{ ucfirst($leaveRequest->leave_type) }}
**Dates:** {{ $leaveRequest->start_date->format('F j, Y') }} – {{ $leaveRequest->end_date->format('F j, Y') }}
**Reason:** {{ $leaveRequest->reason }}
@endcomponent

Your attendance for these dates has been marked as on leave automatically. If you had any appointments assigned during this period, they've been flagged for reassignment.

Thanks,
{{ config('app.name') }}
@endcomponent