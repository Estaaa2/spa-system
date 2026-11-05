@component('mail::message')
# Reschedule Request Not Reviewed in Time

Hi **{{ $rescheduleRequest->requestedBy->name }}**,

Your request to move your appointment to **{{ $requestedWhen }}** wasn't reviewed before your appointment, so your original booking on **{{ $originalWhen }}** at **{{ $branchName }}** stands.

@if($branchPhone)
To change it, please call the branch at **{{ $branchPhone }}**.
@else
To change it, please contact the branch directly.
@endif

Thanks,
{{ config('app.name') }}
@endcomponent
