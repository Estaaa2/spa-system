{{-- DESTINATION: resources/views/emails/leave-rejected.blade.php (new file) --}}
{{-- CORRECTED AGAIN — matches your actual reschedule-rejected.blade.php exactly. --}}
@component('mail::message')
# Leave Request Not Approved

Hi **{{ $leaveRequest->user->name }}**,

Unfortunately, your leave request has been **rejected**.

@component('mail::panel')
**Reason:** {{ $leaveRequest->rejection_reason ?? 'No reason provided.' }}
@endcomponent

If you have questions, please contact your manager directly.

Thanks,
{{ config('app.name') }}
@endcomponent