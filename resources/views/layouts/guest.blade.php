<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @props(['title' => null])
        <title>{{ $title ? $title . ' | Levictas' : 'Levictas' }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <!-- Toastify -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
        <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            @keyframes fadeInDown {
                from { opacity: 0; transform: translateY(-12px); }
                to   { opacity: 1; transform: translateY(0); }
            }

            .animate-fade-in {
                animation: fadeInDown 0.3s ease forwards;
            }
        </style>

    </head>

    <body class="font-sans antialiased text-gray-900">
    <div class="relative min-h-screen">

        <!-- Background Image -->
        <div class="absolute inset-0">
            <img
                src="{{ asset('images/heads.png') }}"
                alt="Spa Background"
                class="object-cover w-full h-full"
            >
            <!-- Dark Overlay -->
            <div class="absolute inset-0 bg-black/50"></div>
        </div>

        <!-- Content -->
        <div class="relative z-10 flex flex-col items-center justify-center min-h-screen px-4 pt-4 pb-8 sm:px-6 sm:pt-6 sm:pb-10">

            <!-- Logo -->
            <div class="mt-2">
                <a href="/">
                    <x-application-logo class="w-16 h-16 text-white fill-current sm:w-20 sm:h-20" />
                </a>
            </div>

            <!-- Card Container -->
            <div
                class="w-full max-w-md overflow-hidden shadow-3xl bg-white/100 backdrop-blur-md rounded-2xl sm:max-w-xl md:max-w-3xl lg:max-w-4xl"
            >
                {{ $slot }}
            </div>

        </div>
    </div>

    <script>
        function escapeSpaToastText(value) {
            const div = document.createElement('div');
            div.textContent = value ?? '';
            return div.innerHTML;
        }

        function showSpaToast(message, type = 'success') {
            const isSuccess = type === 'success';
            const safeMessage = escapeSpaToastText(message);

            Toastify({
                text: `
                    <div style="display:flex; align-items:center; gap:12px; padding:2px 0;">
                        <div style="
                            width:36px;
                            height:36px;
                            border-radius:50%;
                            background:${isSuccess ? '#f0fdf4' : '#fef2f2'};
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            flex-shrink:0;
                        ">
                            <i class="${isSuccess ? 'fa-solid fa-spa' : 'fa-solid fa-circle-xmark'}"
                               style="
                                   color:${isSuccess ? '#16a34a' : '#dc2626'};
                                   font-size:15px;
                               ">
                            </i>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:2px;">
                            <span style="
                                font-size:11px;
                                font-weight:600;
                                letter-spacing:0.08em;
                                text-transform:uppercase;
                                color:${isSuccess ? '#15803d' : '#b91c1c'};
                            ">
                                ${isSuccess ? 'Success' : 'Error'}
                            </span>

                            <span style="
                                font-size:13px;
                                color:#374151;
                                font-weight:400;
                                line-height:1.4;
                            ">
                                ${safeMessage}
                            </span>
                        </div>
                    </div>
                `,
                duration: 4000,
                gravity: 'top',
                position: 'right',
                close: false,
                escapeMarkup: false,
                style: {
                    background: '#ffffff',
                    border: isSuccess ? '1px solid #bbf7d0' : '1px solid #fecaca',
                    borderLeft: isSuccess ? '4px solid #16a34a' : '4px solid #dc2626',
                    borderRadius: '10px',
                    minWidth: '300px',
                    maxWidth: '380px',
                    padding: '14px 18px',
                    boxShadow: '0 10px 30px rgba(0,0,0,0.08), 0 2px 8px rgba(0,0,0,0.04)',
                }
            }).showToast();
        }

        document.addEventListener('DOMContentLoaded', function () {
            @if(session('success'))
                showSpaToast(@json(session('success')), 'success');

            @elseif(session('error'))
                showSpaToast(@json(session('error')), 'error');

            @else
                @php
                    $toastValidationError = null;

                    foreach ($errors->getBags() as $errorBag) {
                        if ($errorBag->any()) {
                            $toastValidationError = $errorBag->first();
                            break;
                        }
                    }
                @endphp

                @if($toastValidationError)
                    showSpaToast(@json($toastValidationError), 'error');
                @endif
            @endif
        });
    </script>

    @stack('toasts')
</body>

</html>
