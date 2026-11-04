<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Join as a Partner | Levictas Spa & Wellness</title>

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
                    Levictas for Business
                </p>

                <h1 class="mt-3 text-4xl font-light leading-tight font-['Playfair_Display']">
                    Grow your wellness business.
                </h1>

                <p class="mt-4 leading-7 text-white/85">
                    Create your partner account to manage your spa, branches,
                    staff, appointments, inventory, and operations.
                </p>
            </div>
        </div>
    </section>

    <section class="flex items-center min-h-screen px-4 py-5 bg-white sm:px-6 lg:px-8 xl:px-12 dark:bg-gray-800">

        <div class="w-full max-w-3xl mx-auto">

            <div class="flex items-center justify-between mb-4">
                <a href="{{ url('/') }}"
                    aria-label="Return to home"
                    class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] text-[#6F5430] rounded-xl hover:bg-[#F8F5F1] dark:text-[#C4A97D] dark:hover:bg-gray-700">

                    <i class="text-xl fa-solid fa-chevron-left"></i>
                </a>

                <img src="{{ asset('images/1.png') }}"
                    alt="Levictas"
                    class="object-contain w-auto h-12 rounded-md">

                <div class="min-w-[44px]"></div>
            </div>

            <div class="mb-5 text-center">
                <h2 class="text-3xl font-light text-[#2D3748] dark:text-white font-['Playfair_Display']">
                    Create a business account
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Create your Levictas partner account.
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('register.business.store') }}"
                class="space-y-4">

                @csrf

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- First Name -->
                <div>
                    <label for="first_name"
                        class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                        First Name
                        <span class="text-red-600 dark:text-red-400">*</span>
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

                <!-- Middle Name -->
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

                <!-- Surname -->
                <div>
                    <label for="last_name"
                        class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                        Surname
                        <span class="text-red-600 dark:text-red-400">*</span>
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

                <!-- Suffix -->
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
                        Email Address <span class="text-red-600">*</span>
                    </label>

                    <div class="relative">
                        <i class="absolute text-gray-400 -translate-y-1/2 fa-solid fa-envelope left-3 top-1/2"></i>

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
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- Password -->
                    <div>
                        <label for="password"
                            class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                            <span class="text-red-600 dark:text-red-400">*</span>
                        </label>

                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <i class="text-gray-400 fa-solid fa-lock"></i>
                            </div>

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
                                class="toggle-password absolute inset-y-0 right-0 inline-flex items-center justify-center min-w-[44px] text-[#8B7355]"
                                data-target="password"
                                aria-label="Show password"
                                tabindex="-1">

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

                    <!-- Confirm Password -->
                    <div>
                        <label for="password_confirmation"
                            class="block mb-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Confirm Password
                            <span class="text-red-600 dark:text-red-400">*</span>
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
                    class="inline-flex items-center justify-center w-full min-h-[48px] px-4 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-[#6F5430] to-[#8B7355] rounded-xl shadow-sm hover:from-[#5A4526] hover:to-[#7A6348] disabled:cursor-not-allowed disabled:opacity-50">

                    <i class="mr-2 fa-solid fa-building"></i>
                    Create Business Account
                </button>

                <div class="flex items-start justify-center gap-2">
                    <input
                        id="terms"
                        name="terms"
                        type="checkbox"
                        value="1"
                        required
                        @checked(old('terms'))
                        class="mt-0.5 h-5 w-5 rounded border-gray-300 text-[#6F5430] focus:ring-[#8B7355]">

                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        I agree to the

                        <button
                            id="openTermsModal"
                            type="button"
                            class="font-medium text-[#6F5430] underline underline-offset-2 dark:text-[#C4A97D]">
                            Terms and Conditions
                        </button>
                    </p>
                </div>

                @error('terms')
                    <p class="text-xs text-center text-red-600">{{ $message }}</p>
                @enderror

                <div class="pt-3 text-center border-t border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Already have an account?

                        <a href="{{ route('login') }}"
                            class="ml-1 font-medium text-[#6F5430] dark:text-[#C4A97D]">
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
                        Partner Terms and Conditions
                    </h2>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Please review these terms before creating your business account.
                    </p>
                </div>

                <button
                    id="closeTermsModal"
                    type="button"
                    aria-label="Close Terms and Conditions"
                    class="inline-flex items-center justify-center min-h-[44px] min-w-[44px] text-gray-500 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700">

                    <i class="fa-solid fa-xmark"></i>
                </button>

            </div>

            <div class="px-5 py-6 overflow-y-auto sm:px-6 max-h-[65vh]">
                <div class="space-y-5 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            1. Business Account Information
                        </h3>
                        <p class="mt-1">
                            The business owner is responsible for providing complete, accurate, and current
                            information regarding the spa, its branches, services, treatments, packages,
                            employees, operating hours, pricing, and other information published or maintained
                            through Levictas.
                        </p>
                    </section>
                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            2. Authorized Use and Account Responsibility
                        </h3>
                        <p class="mt-1">
                            The registered business owner is responsible for maintaining the security of the
                            business account and for controlling access granted to managers, therapists,
                            receptionists, finance personnel, human resources personnel, and other authorized
                            staff members. Account credentials must not be shared with unauthorized persons.
                        </p>
                    </section>
                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            3. Customer Bookings and Service Fulfillment
                        </h3>
                        <p class="mt-1">
                            The partner spa is responsible for honoring confirmed customer appointments,
                            maintaining accurate service availability, assigning appropriate staff, and
                            providing the services represented through the Levictas platform. Any cancellation,
                            rescheduling, refund, or no-show policy presented to customers must be applied
                            consistently.
                        </p>
                    </section>
                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            4. Platform Transaction Fee and Settlement
                        </h3>
                        <p class="mt-1">
                            Levictas charges a platform transaction fee for each successfully paid customer
                            transaction processed through the Levictas platform. By registering as a business
                            partner and accepting these Terms and Conditions, the partner authorizes Levictas
                            to deduct the applicable platform transaction fee from the gross amount collected
                            for each eligible transaction before the remaining amount is credited or settled
                            to the partner spa.
                        </p>
                        <p class="mt-2">
                            The applicable transaction fee rate will be disclosed to the partner through the
                            platform, business plan, merchant agreement, or other applicable pricing information
                            before the fee is imposed. Levictas will provide transaction records showing the
                            gross transaction amount, applicable platform fee, and resulting net amount due to
                            the partner.
                        </p>
                        <p class="mt-2">
                            Where a transaction is cancelled, refunded, reversed, disputed, or otherwise
                            adjusted, the corresponding settlement and platform fee may also be adjusted in
                            accordance with the applicable payment and refund rules.
                        </p>
                    </section>
                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            5. Payment Processing
                        </h3>

                        <p class="mt-1">
                            Payments made through Levictas may be processed by an authorized third-party payment
                            provider. The partner acknowledges that payment processing may be subject to the
                            provider's processing requirements, verification procedures, settlement schedules,
                            and applicable processing charges. Platform transaction fees charged by Levictas
                            are separate from any fees that may be imposed by a payment service provider unless
                            expressly stated otherwise.
                        </p>
                    </section>
                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            6. Pricing and Business Information
                        </h3>
                        <p class="mt-1">
                            The partner is responsible for ensuring that all prices, services, treatments,
                            packages, promotions, branch information, and availability displayed through
                            Levictas are accurate. Customers must not be intentionally presented with misleading
                            prices or service information.
                        </p>
                    </section>
                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            7. Customer Information and Privacy
                        </h3>

                        <p class="mt-1">
                            Customer information obtained through Levictas may only be accessed and used for
                            legitimate business purposes related to appointments, service delivery, customer
                            communication, payment processing, and other authorized spa operations. Customer
                            information must not be disclosed or used for unauthorized purposes.
                        </p>
                    </section>
                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            8. Inventory, Procurement, and Financial Records
                        </h3>
                        <p class="mt-1">
                            The partner is responsible for maintaining accurate records for inventory,
                            stock movements, procurement activities, supplier transactions, invoices,
                            receipts, and other operational or financial information entered into Levictas.
                            Transaction histories and audit records maintained by the system are intended
                            to support accountability and traceability.
                        </p>
                    </section>
                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            9. Prohibited Activities
                        </h3>
                        <p class="mt-1">
                            The partner must not use Levictas to conduct fraudulent transactions, manipulate
                            business or financial records, gain unauthorized access to accounts or data,
                            impersonate another person or business, interfere with platform operation, or use
                            the system for unlawful or unauthorized purposes.
                        </p>
                    </section>

                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            10. Suspension or Termination
                        </h3>
                        <p class="mt-1">
                            Levictas may restrict, suspend, or terminate access to a business account when
                            necessary to address fraudulent activity, security risks, material violations of
                            these Terms and Conditions, misuse of the platform, or other conduct that may
                            compromise customers, partners, or the operation of the system.
                        </p>
                    </section>
                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            11. Service Availability
                        </h3>
                        <p class="mt-1">
                            Levictas may periodically undergo maintenance, upgrades, or temporary service
                            interruptions. Features, integrations, and platform functionality may be modified
                            as necessary to maintain, secure, or improve the service.
                        </p>
                    </section>
                    <section>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            12. Changes to These Terms
                        </h3>
                        <p class="mt-1">
                            Levictas may revise these Terms and Conditions when necessary to reflect changes
                            to platform functionality, pricing, payment arrangements, business requirements,
                            or applicable policies. Material changes affecting partner obligations or platform
                            fees should be communicated before they take effect.
                        </p>
                    </section>
                    <div class="p-4 border border-[#C4A97D]/40 rounded-xl bg-[#F8F5F1] dark:border-[#C4A97D]/30 dark:bg-[#C4A97D]/10">
                        <p class="text-sm font-medium text-[#6F5430] dark:text-[#C4A97D]">
                            By selecting “Accept Terms and Conditions,” you acknowledge that you have read,
                            understood, and agreed to these terms, including Levictas' right to deduct the
                            applicable platform transaction fee from eligible transactions processed through
                            the platform.
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-gray-200 sm:px-6 dark:border-gray-700">
                <button
                    id="acceptTermsModal"
                    type="button"
                    class="inline-flex items-center justify-center w-full min-h-[44px] px-5 py-2 text-sm font-medium text-white bg-[#6F5430] rounded-xl hover:bg-[#5A4526]">

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

    function updateRegisterButton() {
        registerButton.disabled = !termsCheckbox.checked;
    }

    function showTermsModal() {
        termsModal.classList.remove('hidden');
        termsModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        closeTermsModal.focus();
    }

    function hideTermsModal() {
        termsModal.classList.add('hidden');
        termsModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        openTermsModal.focus();
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
