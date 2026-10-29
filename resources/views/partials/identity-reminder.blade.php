{{--
    Dashboard reminder for treatment providers who still need to act on identity
    verification (decision Q29). Shown only when there is something to do:
    not started, not approved, or verify again after a photo/name change.
    Not shown while waiting for review, and not after the person withdrew
    consent: verification is voluntary, so they are not nagged about a choice
    they made.
--}}
@use('App\Services\Identity\IdentityVerificationService', 'IVS')
@php
    $reminderUser = auth()->user();
    $reminderState = null;

    if ($reminderUser && ! $reminderUser->isIdentityVerified() && \Illuminate\Support\Facades\Gate::allows('submit-identity-verification')) {
        $reminderState = app(IVS::class)->stateFor($reminderUser);
    }

    $reminder = match ($reminderState) {
        IVS::STATE_NOT_STARTED => [
            'title' => 'Verify your identity to receive online home-service bookings',
            'body'  => 'Customers who book home service see your photo before you arrive. It takes a few minutes: a profile photo, a photo of your ID and a selfie.',
            'cta'   => 'Verify now',
        ],
        IVS::STATE_REJECTED => [
            'title' => 'Your identity verification was not approved',
            'body'  => 'See the reviewer\'s reason on your profile page and submit again.',
            'cta'   => 'See the reason',
        ],
        IVS::STATE_NEEDS_REVERIFICATION => [
            'title' => 'Verify your identity again',
            'body'  => 'Your photo or name changed after you were verified. Until you verify again, you won\'t be assigned new online home-service bookings.',
            'cta'   => 'Verify again',
        ],
        default => null,
    };
@endphp

@if ($reminder)
    <div class="flex flex-col gap-3 p-4 border shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5 border-amber-200 bg-amber-50 rounded-2xl dark:bg-amber-900/10 dark:border-amber-800" role="status">
        <div class="flex items-start gap-3">
            <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-xl bg-amber-100 dark:bg-amber-900/30">
                <i class="text-sm fa-solid fa-id-card text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ $reminder['title'] }}</p>
                <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-300">{{ $reminder['body'] }}</p>
            </div>
        </div>
        <a href="{{ route('profile.edit') }}#identity"
           class="inline-flex items-center justify-center gap-2 shrink-0 min-h-[44px] rounded-xl bg-[#8B7355] px-4 py-2 text-sm font-medium text-white hover:bg-[#7A6348]">
            {{ $reminder['cta'] }}
            <i class="text-xs fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
@endif
