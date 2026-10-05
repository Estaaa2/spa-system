<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Verify Email | Levictas Spa & Wellness</title>

    <link rel="icon" type="image/png" href="{{ asset('images/1.png') }}">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="min-h-screen antialiased bg-[#F8F5F1] dark:bg-gray-900">

<main class="min-h-screen lg:grid lg:grid-cols-2">

    <section class="relative hidden overflow-hidden lg:block">
        <img
            src="{{ asset('images/face.jpeg') }}"
            alt="Levictas Spa & Wellness"
            class="absolute inset-0 object-cover w-full h-full">

        <div class="absolute inset-0 bg-black/30"></div>

        <div class="relative z-10 flex items-end min-h-screen p-12">
            <div class="max-w-md text-white">

                <p class="text-sm font-medium tracking-[0.18em] uppercase text-white/80">
                    Levictas Spa & Wellness
                </p>

                <h1 class="mt-3 text-4xl font-light leading-tight font-['Playfair_Display']">
                    One last step.
                </h1>

                <p class="mt-4 leading-7 text-white/85">
                    Verify your email before your Levictas account is created.
                </p>

            </div>
        </div>
    </section>

    <section class="flex items-center min-h-screen px-4 py-8 bg-white sm:px-8 dark:bg-gray-800">

        <div class="w-full max-w-md mx-auto">

            <div class="text-center">

                <div class="inline-flex items-center justify-center w-16 h-16 mb-5 rounded-full bg-[#8B7355]/10 text-[#6F5430] dark:bg-[#C4A97D]/10 dark:text-[#C4A97D]">
                    <i class="text-2xl fa-solid fa-envelope-circle-check"></i>
                </div>

                <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                    Verify Your Email
                </h2>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    We sent your verification options to
                </p>

                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                    {{ $pendingRegistration->email }}
                </p>

            </div>

            <div class="p-5 mt-6 border border-gray-200 rounded-2xl bg-gray-50 dark:border-gray-700 dark:bg-gray-900/40">

                <div class="flex items-start gap-3">

                    <div class="flex items-center justify-center shrink-0 w-9 h-9 rounded-xl bg-[#8B7355]/10 text-[#6F5430] dark:bg-[#C4A97D]/10 dark:text-[#C4A97D]">
                        <i class="fa-solid fa-link"></i>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            Verify through email
                        </p>

                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                            Open the email we sent and click the
                            <strong class="font-semibold text-gray-700 dark:text-gray-300">
                                Verify Email Address
                            </strong>
                            button.
                        </p>
                    </div>

                </div>

            </div>

            <div class="flex items-center gap-3 my-5">

                <div class="flex-1 border-t border-gray-200 dark:border-gray-700"></div>

                <span class="text-xs font-medium text-gray-400 uppercase">
                    Or use OTP
                </span>

                <div class="flex-1 border-t border-gray-200 dark:border-gray-700"></div>

            </div>

            <form
                id="otpVerificationForm"
                method="POST"
                action="{{ route('pending.verification.otp') }}"
                class="space-y-4">

                @csrf

                <div>

                    <label
                        for="otp"
                        class="block mb-2 text-sm font-medium text-center text-gray-700 dark:text-gray-300">
                        Enter the 6-digit verification code
                    </label>

                    <input
                        id="otp"
                        name="otp"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        required
                        value="{{ old('otp') }}"
                        placeholder="000000"
                        class="w-full min-h-[56px] px-4 text-2xl font-semibold tracking-[0.4em] text-center bg-white border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-2 focus:ring-[#8B7355]/20 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                    @error('otp')
                        <p class="mt-2 text-xs text-center text-red-600 dark:text-red-400">
                            {{ $message }}
                        </p>
                    @enderror

                </div>

                <button
                    id="verifyOtpButton"
                    type="submit"
                    class="inline-flex items-center justify-center w-full min-h-[48px] px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-[#6F5430] to-[#8B7355] rounded-xl shadow-sm hover:from-[#5A4526] hover:to-[#7A6348] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]">

                    <i class="mr-2 fa-solid fa-shield-halved"></i>
                    Verify Code

                </button>

            </form>

            <form
                method="POST"
                action="{{ route('pending.verification.resend') }}"
                class="mt-4">

                @csrf

                <button
                    type="submit"
                    class="inline-flex items-center justify-center w-full min-h-[44px] px-4 py-2 text-sm font-medium border border-[#8B7355] text-[#6F5430] rounded-xl hover:bg-[#F8F5F1] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:text-[#C4A97D] dark:hover:bg-gray-700">

                    <i class="mr-2 fa-solid fa-paper-plane"></i>
                    Resend Verification Email

                </button>

                @error('resend')
                    <p class="mt-2 text-xs text-center text-red-600 dark:text-red-400">
                        {{ $message }}
                    </p>
                @enderror

            </form>

            <p class="mt-4 text-xs leading-5 text-center text-gray-500 dark:text-gray-400">
                The 6-digit code expires after 10 minutes. Your registration will remain pending for up to 24 hours.
            </p>

            <div class="pt-5 mt-5 text-center border-t border-gray-200 dark:border-gray-700">

                @if($pendingRegistration->registration_type === 'business')

                    <a
                        href="{{ route('register.business') }}"
                        class="inline-flex items-center min-h-[44px] text-sm text-gray-500 hover:text-[#6F5430] dark:text-gray-400 dark:hover:text-[#C4A97D]">

                        <i class="mr-2 fa-solid fa-chevron-left"></i>
                        Back to Business Registration
                    </a>

                @else

                    <a
                        href="{{ route('register') }}"
                        class="inline-flex items-center min-h-[44px] text-sm text-gray-500 hover:text-[#6F5430] dark:text-gray-400 dark:hover:text-[#C4A97D]">

                        <i class="mr-2 fa-solid fa-chevron-left"></i>
                        Back to Registration
                    </a>

                @endif

            </div>

        </div>
    </section>

</main>

<div
    id="verificationLoadingOverlay"
    class="fixed inset-0 z-50 flex items-center justify-center invisible px-4 transition-opacity duration-300 opacity-0 bg-black/60 backdrop-blur-sm"
    aria-hidden="true">

    <div class="w-full max-w-md p-8 text-center bg-white border border-gray-200 shadow-xl rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <div class="relative flex items-center justify-center mx-auto mb-6 w-28 h-28">

            <div class="absolute inset-0 rounded-full bg-[#8B7355]/10 animate-ping"></div>

            <div class="relative flex items-center justify-center w-24 h-24 border-4 rounded-full border-[#8B7355]/15 bg-[#8B7355]/5">

                <div
                    class="w-12 h-12 border-4 rounded-full border-[#8B7355]/20 border-t-[#6F5430] animate-spin">
                </div>

            </div>

        </div>

        <p class="text-xs font-semibold tracking-widest uppercase text-[#8B7355] dark:text-[#C4A97D]">
            Verifying Email
        </p>

        <h2 class="mt-2 text-2xl font-semibold text-[#2D3748] dark:text-white font-['Playfair_Display']">
            Checking your verification code
        </h2>

        <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
            Please wait while we confirm your email and prepare your Levictas account.
        </p>

        <div class="w-full h-1.5 mt-6 overflow-hidden bg-gray-100 rounded-full dark:bg-gray-700">
            <div
                id="verificationLoadingProgress"
                class="h-full bg-[#6F5430] rounded-full transition-all duration-700"
                style="width: 15%;">
            </div>
        </div>

    </div>

</div>

<div
    id="emailVerifiedOverlay"
    class="fixed inset-0 z-[60] flex items-center justify-center invisible px-4 transition-opacity duration-300 opacity-0 bg-black/60 backdrop-blur-sm"
    aria-hidden="true">

    <div class="w-full max-w-md p-8 text-center bg-white border border-gray-200 shadow-xl rounded-2xl dark:bg-gray-800 dark:border-gray-700">

        <div class="relative flex items-center justify-center mx-auto mb-6 w-28 h-28">

            <div
                id="emailVerifiedPing"
                class="absolute inset-0 rounded-full bg-emerald-100 dark:bg-emerald-900/30 animate-ping">
            </div>

            <div class="relative flex items-center justify-center w-24 h-24 border-4 rounded-full border-emerald-100 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/30">

                <div
                    id="emailVerifiedSpinner"
                    class="w-12 h-12 border-4 rounded-full border-emerald-200 border-t-emerald-600 animate-spin dark:border-emerald-900 dark:border-t-emerald-400">
                </div>

                <i
                    id="emailVerifiedCheck"
                    class="absolute hidden text-4xl text-emerald-600 fa-solid fa-check dark:text-emerald-400">
                </i>

            </div>

        </div>

        <p
            id="emailVerifiedLabel"
            class="text-xs font-semibold tracking-widest uppercase text-emerald-600 dark:text-emerald-400">
            Finalizing Verification
        </p>

        <h2
            id="emailVerifiedTitle"
            class="mt-2 text-2xl font-semibold text-[#2D3748] dark:text-white font-['Playfair_Display']">
            Preparing your account
        </h2>

        <p
            id="emailVerifiedMessage"
            class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
            Your email has been verified. We're preparing your Levictas account.
        </p>

        <div class="w-full h-1.5 mt-6 overflow-hidden bg-gray-100 rounded-full dark:bg-gray-700">

            <div
                id="emailVerifiedProgress"
                class="h-full transition-all duration-700 rounded-full bg-emerald-600"
                style="width:15%;">
            </div>

        </div>

        <p
            id="emailVerifiedRedirect"
            class="mt-4 text-xs text-gray-400 transition-opacity duration-300 opacity-0">
            Redirecting automatically...
        </p>

    </div>

</div>

<script>
const otpInput =
    document.getElementById('otp');

const otpVerificationForm =
    document.getElementById(
        'otpVerificationForm'
    );

const verifyOtpButton =
    document.getElementById(
        'verifyOtpButton'
    );

const verificationLoadingOverlay =
    document.getElementById(
        'verificationLoadingOverlay'
    );

const verificationLoadingProgress =
    document.getElementById(
        'verificationLoadingProgress'
    );

const verificationStatusUrl =
    @json(route('pending.verification.status'));

const emailVerifiedOverlay =
    document.getElementById(
        'emailVerifiedOverlay'
    );

const emailVerifiedProgress =
    document.getElementById(
        'emailVerifiedProgress'
    );

const emailVerifiedSpinner =
    document.getElementById(
        'emailVerifiedSpinner'
    );

const emailVerifiedCheck =
    document.getElementById(
        'emailVerifiedCheck'
    );

const emailVerifiedPing =
    document.getElementById(
        'emailVerifiedPing'
    );

const emailVerifiedLabel =
    document.getElementById(
        'emailVerifiedLabel'
    );

const emailVerifiedTitle =
    document.getElementById(
        'emailVerifiedTitle'
    );

const emailVerifiedMessage =
    document.getElementById(
        'emailVerifiedMessage'
    );

const emailVerifiedRedirect =
    document.getElementById(
        'emailVerifiedRedirect'
    );

let verificationDetected = false;

function showEmailVerifiedSuccess(redirectUrl) {
    if (verificationDetected) {
        return;
    }

    verificationDetected = true;

    emailVerifiedOverlay.classList.remove(
        'invisible',
        'opacity-0'
    );

    emailVerifiedOverlay.classList.add(
        'opacity-100'
    );

    emailVerifiedOverlay.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.style.overflow = 'hidden';

    requestAnimationFrame(function () {
        emailVerifiedProgress.style.width =
            '70%';
    });

    setTimeout(function () {
        emailVerifiedProgress.style.width =
            '100%';

        emailVerifiedSpinner.classList.add(
            'hidden'
        );

        emailVerifiedCheck.classList.remove(
            'hidden'
        );

        emailVerifiedPing.classList.remove(
            'animate-ping'
        );

        emailVerifiedLabel.textContent =
            'Email Verified';

        emailVerifiedTitle.textContent =
            'Your account is ready';

        emailVerifiedMessage.textContent =
            'Your email has been successfully verified. Welcome to Levictas.';

        emailVerifiedRedirect.classList.remove(
            'opacity-0'
        );
    }, 800);

    setTimeout(function () {
        window.location.href =
            redirectUrl;
    }, 2200);
}

async function checkVerificationStatus() {
    if (verificationDetected) {
        return;
    }

    try {
        const response =
            await fetch(
                verificationStatusUrl,
                {
                    headers: {
                        'Accept': 'application/json',
                    },
                    cache: 'no-store',
                }
            );

        if (!response.ok) {
            return;
        }

        const data =
            await response.json();

        if (
            data.verified &&
            data.redirect_url
        ) {
            showEmailVerifiedSuccess(
                data.redirect_url
            );
        }
    } catch (error) {
    }
}

const verificationStatusTimer =
    setInterval(
        checkVerificationStatus,
        2000
    );

checkVerificationStatus();

window.addEventListener(
    'pagehide',
    function () {
        clearInterval(
            verificationStatusTimer
        );
    }
);

if (otpInput) {
    otpInput.addEventListener(
        'input',
        function () {
            this.value = this.value
                .replace(/\D/g, '')
                .slice(0, 6);
        }
    );
}

if (
    otpVerificationForm &&
    verifyOtpButton &&
    verificationLoadingOverlay
) {
    otpVerificationForm.addEventListener(
        'submit',
        function () {
            if (!otpVerificationForm.checkValidity()) {
                return;
            }

            verifyOtpButton.disabled = true;

            verifyOtpButton.innerHTML =
                '<i class="mr-2 fa-solid fa-spinner fa-spin"></i> Verifying...';

            verificationLoadingOverlay.classList.remove(
                'invisible',
                'opacity-0'
            );

            verificationLoadingOverlay.classList.add(
                'opacity-100'
            );

            verificationLoadingOverlay.setAttribute(
                'aria-hidden',
                'false'
            );

            document.body.style.overflow =
                'hidden';

            requestAnimationFrame(function () {
                verificationLoadingProgress.style.width =
                    '75%';
            });
        }
    );
}

window.addEventListener(
    'pageshow',
    function () {
        if (!verifyOtpButton) {
            return;
        }

        verifyOtpButton.disabled = false;

        verifyOtpButton.innerHTML =
            '<i class="mr-2 fa-solid fa-shield-halved"></i> Verify Code';
    }
);
</script>

</body>
</html>
