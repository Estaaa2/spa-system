@component('mail::message')
# Your Reschedule Has Been Approved

Hi **{{ $rescheduleRequest->requestedBy->name }}**,

Your reschedule request has been **approved**!

@component('mail::panel')
**Booking:** {{ $rescheduleRequest->booking->treatment }}
**New Date:** {{ \Carbon\Carbon::parse($rescheduleRequest->requested_date)->format('F j, Y') }}
**New Time:** {{ \Carbon\Carbon::parse($rescheduleRequest->requested_time)->format('g:i A') }}
@endcomponent

Please make sure to arrive on time. See you at the spa!

Thanks,
{{ config('app.name') }}
@endcomponent
