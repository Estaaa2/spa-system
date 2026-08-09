@component('mail::message')
# Reschedule Request Not Approved

Hi **{{ $rescheduleRequest->requestedBy->name }}**,

Unfortunately, your reschedule request has been **rejected**.

@component('mail::panel')
**Reason:** {{ $rescheduleRequest->rejection_reason ?? 'No reason provided.' }}
@endcomponent

Your original appointment remains unchanged. If you have questions, please contact the spa directly.

Thanks,
{{ config('app.name') }}
@endcomponent
