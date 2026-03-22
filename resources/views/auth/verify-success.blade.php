<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verified — Levictas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #faf8f5 0%, #f0ebe3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            background: white;
            border-radius: 24px;
            padding: 52px 44px;
            text-align: center;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 24px 64px rgba(0,0,0,0.10);
            animation: rise 0.4s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .logo {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            object-fit: cover;
            margin: 0 auto 24px;
        }

        .icon-wrap {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: pop 0.5s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both;
        }

        @keyframes pop {
            from { transform: scale(0.5); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 600;
            color: #2c1f0e;
            margin-bottom: 10px;
        }

        .subtitle {
            font-size: 14px;
            color: #9c8b78;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .countdown-box {
            background: #faf8f5;
            border: 1px solid #ede8e1;
            border-radius: 12px;
            padding: 14px 20px;
            margin-bottom: 20px;
        }

        .countdown-text {
            font-size: 13px;
            color: #b0a090;
        }

        .countdown-text span {
            font-weight: 700;
            color: #8B7355;
            font-size: 15px;
        }

        .tab-note {
            font-size: 12px;
            color: #c4b8aa;
            margin-top: 6px;
        }

        .progress-bar {
            height: 5px;
            background: #f0ebe3;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #8B7355, #C4A882);
            border-radius: 3px;
            width: 100%;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 32px;
            background: linear-gradient(135deg, #8B7355, #6F5430);
            color: white;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: opacity 0.2s, transform 0.15s;
            width: 100%;
            justify-content: center;
        }

        .btn:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: #d4c9bc;
            font-size: 12px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #ede8e1;
        }
    </style>
</head>
<body>
    <div class="card">

        <img src="{{ asset('images/1.png') }}" alt="Levictas" class="logo">

        <div class="icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" style="color:#16a34a; width:40px; height:40px;">
                <path d="M20 6L9 17l-5-5"/>
            </svg>
        </div>

        <h1>Email Verified!</h1>
        <p class="subtitle">
            Your email has been successfully verified.<br>
            You're all set to access your account.
        </p>

        <div class="countdown-box">
            <div class="countdown-text">
                Redirecting in <span id="count">5</span> seconds...
            </div>
            <div class="tab-note" id="tabNote">
                This tab will close automatically if it was opened from a link.
            </div>
        </div>

        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>

        <a href="{{ $redirectUrl }}" class="btn">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
            Go to My Account
        </a>

        <div class="divider">or</div>

        <p style="font-size: 12px; color: #b0a090;">
            You can safely close this tab and return to the previous page.
        </p>
    </div>

    <script>
        const SECONDS     = 5;
        const redirectUrl = '{{ $redirectUrl }}';

        let count        = SECONDS;
        const countEl    = document.getElementById('count');
        const fillEl     = document.getElementById('progressFill');
        const tabNoteEl  = document.getElementById('tabNote');

        // Animate progress bar
        fillEl.style.transition = `width ${SECONDS}s linear`;
        setTimeout(() => { fillEl.style.width = '0%'; }, 50);

        const timer = setInterval(function () {
            count--;
            countEl.textContent = count;

            if (count <= 0) {
                clearInterval(timer);

                // Try closing the tab first (works if opened via target="_blank")
                window.close();

                // Fallback: redirect after short delay if close was blocked
                setTimeout(function () {
                    window.location.href = redirectUrl;
                }, 400);
            }
        }, 1000);

        // If window.close() works, hide the redirect note
        window.addEventListener('blur', function () {
            clearInterval(timer);
        });
    </script>
</body>
</html>
