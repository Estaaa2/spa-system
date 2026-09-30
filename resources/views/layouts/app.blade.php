<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>[x-cloak] { display: none !important; }</style>

    <title>@hasSection('title')@yield('title') |@endif Levictas</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Alpine.js with Collapse Plugin -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/welcome.js'])
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">

        @if(auth()->check() && auth()->user()->hasRole('admin'))
            @include('layouts.navigation-admin')
        @else
            @include('layouts.navigation')
        @endif

        <x-toast />
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

        function applyStockInputLimit(input) {
            const max = parseFloat(input.dataset.stockLimit || '20000');
            const decimalPlaces = parseInt(input.dataset.stockDecimals || '3', 10);

            let value = input.value;

            value = value.replace(/[^0-9.]/g, '');

            const firstDot = value.indexOf('.');

            if (firstDot !== -1) {
                value =
                    value.slice(0, firstDot + 1) +
                    value.slice(firstDot + 1).replace(/\./g, '');
            }

            let [integerPart = '', decimalPart = ''] = value.split('.');

            integerPart = integerPart.slice(0, 5);

            if (decimalPlaces > 0) {
                decimalPart = decimalPart.slice(0, decimalPlaces);
            } else {
                decimalPart = '';
            }

            value = integerPart;

            if (firstDot !== -1 && decimalPlaces > 0) {
                value += '.' + decimalPart;
            }

            if (value !== '' && value !== '.') {
                const numericValue = parseFloat(value);

                if (!Number.isNaN(numericValue) && numericValue > max) {
                    value = String(max);
                    showSpaToast(
                        `Maximum allowed quantity is ${max.toLocaleString()} units.`,
                        'error'
                    );
                }
            }

            input.value = value;
        }

        document.addEventListener('input', function (event) {
            const input = event.target.closest('[data-stock-limit]');

            if (!input) {
                return;
            }

            applyStockInputLimit(input);
        });

        document.addEventListener('paste', function (event) {
            const input = event.target.closest('[data-stock-limit]');

            if (!input) {
                return;
            }

            setTimeout(function () {
                applyStockInputLimit(input);
            }, 0);
        });

        document.addEventListener('DOMContentLoaded', function () {
            const type = sessionStorage.getItem('toast_type');
            const message = sessionStorage.getItem('toast_message');

            if (type && message) {
                showSpaToast(message, type);

                sessionStorage.removeItem('toast_type');
                sessionStorage.removeItem('toast_message');

                return;
            }

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
</body>

</html>