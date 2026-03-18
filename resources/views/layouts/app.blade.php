<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        @if(auth()->check() && auth()->user()->hasRole('admin'))
            @include('layouts.navigation-admin')
        @elseif(auth()->check() && auth()->user()->hasRole('hr'))
            @include('layouts.navigation-hr')
        @elseif(auth()->check() && auth()->user()->hasRole('finance'))
            @include('layouts.navigation-finance')
        @else
            @include('layouts.navigation')
        @endif

        <!-- Page Heading -->
        @isset($header)
            <header class="bg-white shadow dark:bg-gray-800">
                <div class="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <x-toast />
    </div>

<script>
    // Single unified toast function used everywhere
    function showSpaToast(message, type = 'success') {
        const isSuccess = type === 'success';

        Toastify({
            text: `
                <div style="display:flex; align-items:center; gap:12px; padding: 2px 0;">
                    <div style="
                        width: 36px;
                        height: 36px;
                        border-radius: 50%;
                        background: ${isSuccess ? '#f0fdf4' : '#fef2f2'};
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        flex-shrink: 0;
                    ">
                        <i class="${isSuccess ? 'fa-solid fa-spa' : 'fa-solid fa-circle-xmark'}"
                           style="color: ${isSuccess ? '#16a34a' : '#dc2626'}; font-size: 15px;">
                        </i>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:2px;">
                        <span style="
                            font-size: 11px;
                            font-weight: 600;
                            letter-spacing: 0.08em;
                            text-transform: uppercase;
                            color: ${isSuccess ? '#15803d' : '#b91c1c'};
                        ">${isSuccess ? 'Success' : 'Error'}</span>
                        <span style="
                            font-size: 13px;
                            color: #374151;
                            font-weight: 400;
                            line-height: 1.4;
                        ">${message}</span>
                    </div>
                </div>
            `,
            duration: 3500,
            gravity: "top",
            position: "right",
            close: false,
            escapeMarkup: false,
            style: {
                background: "#ffffff",
                border: isSuccess ? "1px solid #bbf7d0" : "1px solid #fecaca",
                borderLeft: isSuccess ? "4px solid #16a34a" : "4px solid #dc2626",
                borderRadius: "10px",
                minWidth: "300px",
                maxWidth: "360px",
                padding: "14px 18px",
                boxShadow: "0 10px 30px rgba(0,0,0,0.08), 0 2px 8px rgba(0,0,0,0.04)",
            }
        }).showToast();
    }

    // For sessionStorage-based toasts (e.g. after redirects)
    document.addEventListener('DOMContentLoaded', function () {
        const type = sessionStorage.getItem('toast_type');
        const message = sessionStorage.getItem('toast_message');
        if (type && message) {
            showSpaToast(message, type);
            sessionStorage.removeItem('toast_type');
            sessionStorage.removeItem('toast_message');
        }
    });
</script>

{{-- ================= FORCE PASSWORD CHANGE MODAL ================= --}}
@auth
    @if(auth()->user()->password_reset_required)
    {{-- Modal Overlay --}}
    <div id="pw-modal"
         style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem;"
         x-data>
        {{-- Backdrop --}}
        <div style="position:absolute;inset:0;background:rgba(15,10,8,0.72);backdrop-filter:blur(6px);"></div>

        {{-- Card --}}
        <div style="
            position:relative;
            width:100%;
            max-width:440px;
            background:#ffffff;
            border-radius:20px;
            overflow:hidden;
            box-shadow:0 32px 80px rgba(0,0,0,0.28);
            animation:pw-rise 0.45s cubic-bezier(0.22,1,0.36,1) both;
        ">
            {{-- Top decorative band --}}
            <div style="
                height:6px;
                background:linear-gradient(90deg, #8B7355 0%, #C4A882 50%, #8B7355 100%);
                background-size:200% 100%;
                animation:pw-shimmer 3s linear infinite;
            "></div>

            {{-- Header --}}
            <div style="
                padding:32px 36px 20px;
                text-align:center;
                background:linear-gradient(160deg, #faf8f5 0%, #ffffff 100%);
                border-bottom:1px solid #f0ebe3;
            ">
                {{-- Icon --}}
                <div style="
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    width:56px;height:56px;
                    background:linear-gradient(135deg,#f5efe6,#ede3d3);
                    border-radius:50%;
                    margin-bottom:14px;
                    box-shadow:0 4px 14px rgba(139,115,85,0.18);
                ">
                    <i class="fa-solid fa-key" style="font-size:22px;color:#8B7355;"></i>
                </div>

                <h2 style="
                    font-family:'Playfair Display',serif;
                    font-size:22px;
                    font-weight:700;
                    color:#2c1f0e;
                    margin:0 0 6px;
                    letter-spacing:0.01em;
                ">Secure Your Account</h2>

                <p style="
                    font-size:13px;
                    color:#9c8b78;
                    margin:0;
                    line-height:1.6;
                ">Your account was created with a temporary password.<br>Please set a new password to continue.</p>
            </div>

            {{-- Form Body --}}
            <div style="padding:28px 36px 32px;">
                <form id="pw-form" method="POST" action="{{ route('profile.password') }}">
                    @csrf
                    @method('PUT')

                    {{-- Field: Current Password --}}
                    <div style="margin-bottom:16px;">
                        <label style="
                            display:block;
                            font-size:11px;
                            font-weight:700;
                            letter-spacing:0.08em;
                            text-transform:uppercase;
                            color:#8B7355;
                            margin-bottom:7px;
                        ">Temporary Password</label>
                        <div style="position:relative;">
                            <input
                                type="password"
                                name="current_password"
                                id="pw-current"
                                required
                                placeholder="Enter your temporary password"
                                style="
                                    width:100%;
                                    padding:11px 42px 11px 14px;
                                    border:1.5px solid #e8dfd3;
                                    border-radius:10px;
                                    font-size:13.5px;
                                    color:#2c1f0e;
                                    background:#fdfbf8;
                                    outline:none;
                                    box-sizing:border-box;
                                    transition:border-color 0.2s,box-shadow 0.2s;
                                    font-family:inherit;
                                "
                                onfocus="this.style.borderColor='#8B7355';this.style.boxShadow='0 0 0 3px rgba(139,115,85,0.12)'"
                                onblur="this.style.borderColor='#e8dfd3';this.style.boxShadow='none'"
                            />
                            <i class="fa-solid fa-lock" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#c4b49e;font-size:13px;pointer-events:none;"></i>
                        </div>
                    </div>

                    {{-- Field: New Password --}}
                    <div style="margin-bottom:16px;">
                        <label style="
                            display:block;
                            font-size:11px;
                            font-weight:700;
                            letter-spacing:0.08em;
                            text-transform:uppercase;
                            color:#8B7355;
                            margin-bottom:7px;
                        ">New Password</label>
                        <div style="position:relative;">
                            <input
                                type="password"
                                name="password"
                                id="pw-new"
                                required
                                placeholder="Minimum 8 characters"
                                style="
                                    width:100%;
                                    padding:11px 42px 11px 14px;
                                    border:1.5px solid #e8dfd3;
                                    border-radius:10px;
                                    font-size:13.5px;
                                    color:#2c1f0e;
                                    background:#fdfbf8;
                                    outline:none;
                                    box-sizing:border-box;
                                    transition:border-color 0.2s,box-shadow 0.2s;
                                    font-family:inherit;
                                "
                                onfocus="this.style.borderColor='#8B7355';this.style.boxShadow='0 0 0 3px rgba(139,115,85,0.12)'"
                                onblur="this.style.borderColor='#e8dfd3';this.style.boxShadow='none'"
                            />
                            <i class="fa-solid fa-lock" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#c4b49e;font-size:13px;pointer-events:none;"></i>
                        </div>

                        {{-- Strength bar --}}
                        <div style="display:flex;gap:4px;margin-top:8px;" id="pw-strength-bars">
                            <div class="pw-bar" style="height:3px;flex:1;border-radius:2px;background:#ede3d3;transition:background 0.3s;"></div>
                            <div class="pw-bar" style="height:3px;flex:1;border-radius:2px;background:#ede3d3;transition:background 0.3s;"></div>
                            <div class="pw-bar" style="height:3px;flex:1;border-radius:2px;background:#ede3d3;transition:background 0.3s;"></div>
                            <div class="pw-bar" style="height:3px;flex:1;border-radius:2px;background:#ede3d3;transition:background 0.3s;"></div>
                        </div>
                        <p id="pw-strength-label" style="font-size:11px;color:#b0a090;margin:4px 0 0;height:14px;"></p>
                    </div>

                    {{-- Field: Confirm Password --}}
                    <div style="margin-bottom:20px;">
                        <label style="
                            display:block;
                            font-size:11px;
                            font-weight:700;
                            letter-spacing:0.08em;
                            text-transform:uppercase;
                            color:#8B7355;
                            margin-bottom:7px;
                        ">Confirm New Password</label>
                        <div style="position:relative;">
                            <input
                                type="password"
                                name="password_confirmation"
                                id="pw-confirm"
                                required
                                placeholder="Repeat new password"
                                style="
                                    width:100%;
                                    padding:11px 42px 11px 14px;
                                    border:1.5px solid #e8dfd3;
                                    border-radius:10px;
                                    font-size:13.5px;
                                    color:#2c1f0e;
                                    background:#fdfbf8;
                                    outline:none;
                                    box-sizing:border-box;
                                    transition:border-color 0.2s,box-shadow 0.2s;
                                    font-family:inherit;
                                "
                                onfocus="this.style.borderColor='#8B7355';this.style.boxShadow='0 0 0 3px rgba(139,115,85,0.12)'"
                                onblur="this.style.borderColor='#e8dfd3';this.style.boxShadow='none'"
                            />
                            <i class="fa-solid fa-lock" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#c4b49e;font-size:13px;pointer-events:none;"></i>
                        </div>
                    </div>

                    {{-- Error message --}}
                    <div id="pw-error" style="
                        display:none;
                        background:#fff5f5;
                        border:1px solid #fecaca;
                        border-radius:8px;
                        padding:10px 14px;
                        font-size:12.5px;
                        color:#dc2626;
                        margin-bottom:18px;
                        display:none;
                        align-items:center;
                        gap:8px;
                    ">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span id="pw-error-text"></span>
                    </div>

                    {{-- Submit --}}
                    <button
                        type="submit"
                        id="pw-submit"
                        style="
                            width:100%;
                            padding:13px;
                            background:linear-gradient(135deg,#8B7355,#a08660);
                            color:#fff;
                            border:none;
                            border-radius:10px;
                            font-size:14px;
                            font-weight:600;
                            letter-spacing:0.04em;
                            cursor:pointer;
                            transition:opacity 0.2s,transform 0.15s,box-shadow 0.2s;
                            box-shadow:0 4px 16px rgba(139,115,85,0.35);
                            font-family:'Playfair Display',serif;
                        "
                        onmouseover="this.style.opacity='0.92';this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 20px rgba(139,115,85,0.4)'"
                        onmouseout="this.style.opacity='1';this.style.transform='translateY(0)';this.style.boxShadow='0 4px 16px rgba(139,115,85,0.35)'"
                    >
                        <i class="fa-solid fa-shield-halved" style="margin-right:8px;"></i>
                        Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap');

        @keyframes pw-rise {
            from { opacity:0; transform:translateY(28px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }
        @keyframes pw-shimmer {
            0%   { background-position:200% 0; }
            100% { background-position:-200% 0; }
        }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form      = document.getElementById('pw-form');
        const newPwIn   = document.getElementById('pw-new');
        const confirmIn = document.getElementById('pw-confirm');
        const currentIn = document.getElementById('pw-current');
        const errorBox  = document.getElementById('pw-error');
        const errorText = document.getElementById('pw-error-text');
        const bars      = document.querySelectorAll('.pw-bar');
        const label     = document.getElementById('pw-strength-label');

        function showError(msg) {
            errorText.textContent = msg;
            errorBox.style.display = 'flex';
        }
        function hideError() {
            errorBox.style.display = 'none';
        }

        // Password strength meter
        newPwIn.addEventListener('input', function () {
            const v = this.value;
            let score = 0;
            if (v.length >= 8)              score++;
            if (/[A-Z]/.test(v))            score++;
            if (/[0-9]/.test(v))            score++;
            if (/[^A-Za-z0-9]/.test(v))     score++;

            const colors  = ['#ef4444','#f97316','#eab308','#22c55e'];
            const labels  = ['Weak','Fair','Good','Strong'];
            const labelColors = ['#ef4444','#f97316','#ca8a04','#16a34a'];

            bars.forEach((b, i) => {
                b.style.background = i < score ? colors[score - 1] : '#ede3d3';
            });
            label.textContent  = score > 0 ? labels[score - 1] : '';
            label.style.color  = score > 0 ? labelColors[score - 1] : '#b0a090';
        });

        // Inline confirm match indicator
        confirmIn.addEventListener('input', function () {
            if (this.value && newPwIn.value) {
                this.style.borderColor = this.value === newPwIn.value ? '#22c55e' : '#ef4444';
                this.style.boxShadow   = this.value === newPwIn.value
                    ? '0 0 0 3px rgba(34,197,94,0.12)'
                    : '0 0 0 3px rgba(239,68,68,0.10)';
            }
        });

        // Form submit validation
        form.addEventListener('submit', function (e) {
            hideError();
            const current = currentIn.value.trim();
            const pw      = newPwIn.value;
            const confirm = confirmIn.value;

            if (!current) {
                e.preventDefault();
                showError('Please enter your temporary password.');
                return;
            }
            if (pw.length < 8) {
                e.preventDefault();
                showError('New password must be at least 8 characters.');
                return;
            }
            if (pw !== confirm) {
                e.preventDefault();
                showError('Passwords do not match.');
                return;
            }
        });
    });
    </script>
    @endif
@endauth

</body>

</html>
