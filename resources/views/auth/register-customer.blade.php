<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Register | Levictas Spa & Wellness</title>

    <link rel="icon" type="image/png" href="{{ asset('images/1.png') }}">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

<body class="min-h-screen antialiased bg-[#F8F5F1] dark:bg-gray-900">

<main class="min-h-screen lg:grid lg:grid-cols-[0.85fr_1.15fr]">

    <section class="relative hidden overflow-hidden lg:block">
        <img
            src="{{ asset('images/face.jpeg') }}"
            alt="Levictas Spa & Wellness"
            class="absolute inset-0 object-cover w-full h-full">

        <div class="absolute inset-0 bg-black/30"></div>

        <div class="relative z-10 flex items-end min-h-screen p-10 xl:p-14">
            <div class="max-w-md text-white">
                <p class="text-sm font-medium tracking-[0.18em] uppercase text-white/80">
                    Levictas Spa & Wellness
                </p>

                <h1 class="mt-3 text-4xl font-light leading-tight font-['Playfair_Display']">
                    Begin your wellness journey.
                </h1>

                <p class="mt-4 leading-7 text-white/85">
                    Create your account to discover spas, schedule appointments,
                    and manage your wellness experience in one place.
                </p>
            </div>
        </div>
    </section>

    <section class="flex items-center min-h-screen px-4 py-5 bg-white sm:px-6 lg:px-8 xl:px-12 dark:bg-gray-800">

        <div class="w-full max-w-3xl mx-auto">

            <div class="flex items-center justify-between mb-4">
                <a href="{{ url('/') }}"
                    aria-label="Return to home"
                    class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] text-[#6F5430] rounded-xl hover:bg-[#F8F5F1] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] dark:text-[#C4A97D] dark:hover:bg-gray-700">

                    <i class="text-xl fa-solid fa-chevron-left"></i>
                </a>

                <img
                    src="{{ asset('images/1.png') }}"
                    alt="Levictas"
                    class="object-contain w-auto h-12 rounded-md">

                <div class="min-w-[44px]"></div>
            </div>

            <div class="mb-5 text-center">
                <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                    Create your account
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Enter your information to get started with Levictas.
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('register') }}"
                id="customerRegisterForm"
                class="space-y-4">

                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                    <div>
                        <label for="first_name"
                            class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                            First Name
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            id="first_name"
                            name="first_name"
                            type="text"
                            value="{{ old('first_name') }}"
                            required
                            autofocus
                            autocomplete="given-name"
                            placeholder="Enter your first name"
                            class="w-full min-h-[44px] px-3 py-2 text-sm bg-white border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                        @error('first_name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="middle_name"
                            class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Middle Name
                            <span class="font-normal text-gray-400">(Optional)</span>
                        </label>

                        <input
                            id="middle_name"
                            name="middle_name"
                            type="text"
                            value="{{ old('middle_name') }}"
                            autocomplete="additional-name"
                            placeholder="Enter your middle name"
                            class="w-full min-h-[44px] px-3 py-2 text-sm bg-white border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                        @error('middle_name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="last_name"
                            class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Surname
                            <span class="text-red-600">*</span>
                        </label>

                        <input
                            id="last_name"
                            name="last_name"
                            type="text"
                            value="{{ old('last_name') }}"
                            required
                            autocomplete="family-name"
                            placeholder="Enter your surname"
                            class="w-full min-h-[44px] px-3 py-2 text-sm bg-white border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                        @error('last_name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="suffix"
                            class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Suffix
                            <span class="font-normal text-gray-400">(Optional)</span>
                        </label>

                        <input
                            id="suffix"
                            name="suffix"
                            type="text"
                            value="{{ old('suffix') }}"
                            maxlength="20"
                            autocomplete="honorific-suffix"
                            placeholder="Example: Jr., Sr., III"
                            class="w-full min-h-[44px] px-3 py-2 text-sm bg-white border border-gray-300 rounded-xl focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                        @error('suffix')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                </div>

                <div>
                    <label for="email"
                        class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Email Address
                        <span class="text-red-600">*</span>
                    </label>

                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class="text-gray-400 fa-solid fa-envelope"></i>
                        </div>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="email"
                            placeholder="Enter your email address"
                            class="w-full min-h-[44px] py-2 pr-3 text-sm bg-white border border-gray-300 rounded-xl pl-10 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    </div>

                    @error('email')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password"
                            class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                            <span class="text-red-600">*</span>
                        </label>

                        <div class="relative">
                            <i class="absolute text-gray-400 -translate-y-1/2 fa-solid fa-lock left-3 top-1/2"></i>

                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="Enter your password"
                                class="w-full min-h-[44px] py-2 pr-12 text-sm bg-white border border-gray-300 rounded-xl pl-10 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                            <button
                                type="button"
                                data-target="password"
                                aria-label="Show password"
                                tabindex="-1"
                                class="toggle-password absolute inset-y-0 right-0 inline-flex items-center justify-center min-w-[44px] text-[#8B7355]">

                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>

                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Password must contain at least 8 characters, including uppercase, lowercase, a number, and a special character.
                        </p>

                        @error('password')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation"
                            class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Confirm Password
                            <span class="text-red-600">*</span>
                        </label>

                        <div class="relative">

                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <i class="text-gray-400 fa-solid fa-lock"></i>
                            </div>

                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                placeholder="Confirm your password"
                                class="w-full min-h-[44px] py-2 pr-12 text-sm bg-white border border-gray-300 rounded-xl pl-10 focus:border-[#8B7355] focus:ring-1 focus:ring-[#8B7355]/30 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                            <button
                                type="button"
                                class="toggle-password absolute inset-y-0 right-0 inline-flex items-center justify-center min-w-[44px] text-[#8B7355]"
                                data-target="password_confirmation"
                                aria-label="Show password"
                                tabindex="-1">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                </div>

                <button
                    id="registerButton"
                    type="submit"
                    disabled
                    class="inline-flex items-center justify-center w-full min-h-[48px] px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-[#6F5430] to-[#8B7355] rounded-xl shadow-sm hover:from-[#5A4526] hover:to-[#7A6348] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355] disabled:cursor-not-allowed disabled:opacity-50">

                    <i class="mr-2 fa-solid fa-user-plus"></i>
                    Register
                </button>

                <div class="flex items-start justify-center gap-2">

                    <input
                        id="terms"
                        name="terms"
                        type="checkbox"
                        value="1"
                        required
                        @checked(old('terms'))
                        class="mt-0.5 h-5 w-5 rounded border-gray-300 text-[#6F5430] focus:ring-[#8B7355] dark:border-gray-600 dark:bg-gray-700">

                    <p class="text-sm leading-5 text-gray-600 dark:text-gray-400">
                        I agree to the

                        <button
                            id="openTermsModal"
                            type="button"
                            class="font-medium text-[#6F5430] underline underline-offset-2 hover:text-[#8B7355] dark:text-[#C4A97D]">
                            Terms and Conditions
                        </button>
                    </p>
                </div>

                @error('terms')
                    <p class="text-xs text-center text-red-600 dark:text-red-400">
                        {{ $message }}
                    </p>
                @enderror

                <div class="pt-3 text-center border-t border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Already have an account?

                        <a
                            href="{{ route('login') }}"
                            class="ml-1 font-medium text-[#6F5430] hover:text-[#8B7355] dark:text-[#C4A97D]">
                            Sign in
                        </a>
                    </p>
                </div>

            </form>

        </div>
    </section>
</main>


<div
    id="termsModal"
    class="fixed inset-0 z-50 hidden overflow-y-auto overscroll-contain bg-black/50"
    aria-hidden="true">

    <div class="flex items-start justify-center min-h-full p-4 sm:items-center">

        <div
            id="termsModalPanel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="termsModalTitle"
            class="w-full max-w-2xl overflow-hidden bg-white shadow-xl rounded-2xl dark:bg-gray-800">

            <div class="flex items-start justify-between gap-4 px-5 py-5 border-b border-gray-200 sm:px-6 dark:border-gray-700">

                <div>
                    <p class="text-xs font-medium tracking-wider text-[#8B7355] uppercase dark:text-[#C4A97D]">
                        Levictas Spa & Wellness
                    </p>

                    <h2
                        id="termsModalTitle"
                        class="mt-1 text-2xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                        Terms and Conditions
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Please review these terms before creating your account.
                    </p>
                </div>

                <button
                    id="closeTermsModal"
                    type="button"
                    aria-label="Close Terms and Conditions"
                    class="inline-flex items-center justify-center shrink-0 min-h-[44px] min-w-[44px] text-gray-500 rounded-xl hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">

                    <i class="fa-solid fa-xmark"></i>
                </button>

            </div>

            <div class="px-5 py-6 overflow-y-auto sm:px-6 max-h-[65vh]">
                <div class="space-y-5 text-sm leading-6 text-gray-600 dark:text-gray-300">

                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            1. Account Registration
                        </h3>

                        <p class="mt-1">
                            You must provide accurate and complete information when creating
                            your Levictas account and keep your account credentials secure.
                        </p>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            2. Appointments and Bookings
                        </h3>

                        <p class="mt-1">
                            Appointment availability depends on the selected spa, branch,
                            service, therapist, operating hours, and existing reservations.
                        </p>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            3. Payments and Down Payments
                        </h3>

                        <p class="mt-1">
                            Where a down payment is required, applicable amounts and conditions
                            will be presented during the booking process.
                        </p>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            4. Cancellations and No-Shows
                        </h3>

                        <p class="mt-1">
                            Customers should follow the cancellation policy shown for their
                            appointment. Applicable down payments may be forfeited for late
                            cancellations or no-shows.
                        </p>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            5. Home Service Information
                        </h3>

                        <p class="mt-1">
                            Customers using home service must provide an accurate service
                            address and contact information.
                        </p>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            6. Responsible Use
                        </h3>

                        <p class="mt-1">
                            You must not misuse the platform, create fraudulent bookings,
                            impersonate another person, or attempt unauthorized access.
                        </p>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            7. Account Security
                        </h3>

                        <p class="mt-1">
                            You are responsible for protecting your password and reporting
                            suspected unauthorized account access.
                        </p>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            8. Personal Information
                        </h3>

                        <p class="mt-1">
                            Information provided through Levictas may be used for account
                            management, appointments, communication, payment processing,
                            and service delivery.
                        </p>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            9. Service Availability
                        </h3>

                        <p class="mt-1">
                            Levictas may occasionally be unavailable because of maintenance,
                            technical issues, or platform updates.
                        </p>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            10. Changes to These Terms
                        </h3>

                        <p class="mt-1">
                            These terms may be updated when necessary to reflect changes to
                            Levictas or its services.
                        </p>
                    </section>

                </div>
            </div>

            <div class="px-5 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                <button
                    id="acceptTermsModal"
                    type="button"
                    class="inline-flex items-center justify-center w-full min-h-[44px] px-5 py-2 text-sm font-medium text-white bg-[#6F5430] rounded-xl hover:bg-[#5A4526] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B7355]">

                    <i class="mr-2 fa-solid fa-check"></i>
                    Accept Terms and Conditions
                </button>
            </div>

        </div>
    </div>
</div>

<script>
(() => {
    document.querySelectorAll('.toggle-password').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.getElementById(button.dataset.target);
            const icon = button.querySelector('i');

            if (!target || !icon) return;

            if (target.type === 'password') {
                target.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                button.setAttribute('aria-label', 'Hide password');
            } else {
                target.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                button.setAttribute('aria-label', 'Show password');
            }
        });
    });

    const termsModal = document.getElementById('termsModal');
    const termsModalPanel = document.getElementById('termsModalPanel');
    const openTermsModal = document.getElementById('openTermsModal');
    const closeTermsModal = document.getElementById('closeTermsModal');
    const acceptTermsModal = document.getElementById('acceptTermsModal');
    const termsCheckbox = document.getElementById('terms');
    const registerButton = document.getElementById('registerButton');

    let lastFocusedElement = null;

    function updateRegisterButton() {
        registerButton.disabled = !termsCheckbox.checked;
    }

    function showTermsModal() {
        lastFocusedElement = document.activeElement;

        termsModal.classList.remove('hidden');
        termsModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        closeTermsModal.focus();
    }

    function hideTermsModal() {
        termsModal.classList.add('hidden');
        termsModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        if (lastFocusedElement) {
            lastFocusedElement.focus();
        }
    }

    openTermsModal.addEventListener('click', showTermsModal);
    closeTermsModal.addEventListener('click', hideTermsModal);

    acceptTermsModal.addEventListener('click', function () {
        termsCheckbox.checked = true;
        updateRegisterButton();
        hideTermsModal();
    });

    termsCheckbox.addEventListener('change', updateRegisterButton);

    termsModal.addEventListener('click', function (event) {
        if (!termsModalPanel.contains(event.target)) {
            hideTermsModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (
            event.key === 'Escape' &&
            !termsModal.classList.contains('hidden')
        ) {
            hideTermsModal();
        }
    });

    updateRegisterButton();
})();
</script>

</body>
</html>
