@component('mail::message')
# Update on Your Application

Hi **{{ $applicant?->full_name }}**,

@if($position)
Thank you for taking the time to interview with **{{ $spaName }}** for the **{{ $position }}** position.
@else
Thank you for taking the time to interview with **{{ $spaName }}**.
@endif

After careful consideration, we have decided not to move forward with your application at this time.

@if(filled($applicantMessage))
@component('mail::panel')
{{ $applicantMessage }}
@endcomponent
@endif

We appreciate your interest and wish you the best in your job search.

Thanks,
{{ $spaName }}
@endcomponent
