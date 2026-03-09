<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verified</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600&family=Inter:wght@400;500&display=swap" rel="stylesheet">
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
            border-radius: 20px;
            padding: 48px 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            animation: rise 0.4s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
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

        .icon-wrap svg {
            width: 40px;
            height: 40px;
            color: #16a34a;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 600;
            color: #2c1f0e;
            margin-bottom: 10px;
        }

        p {
            font-size: 14px;
            color: #9c8b78;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .countdown {
            font-size: 13px;
            color: #b0a090;
            margin-bottom: 20px;
        }

        .countdown span {
            font-weight: 600;
            color: #8B7355;
        }

        .progress-bar {
            height: 4px;
            background: #f0ebe3;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #8B7355, #C4A882);
            border-radius: 2px;
            width: 100%;
            animation: shrink 3s linear forwards;
        }

        @keyframes shrink {
            from { width: 100%; }
            to   { width: 0%; }
        }

        .btn {
            display: inline-block;
            padding: 12px 28px;
            background: linear-gradient(135deg, #8B7355, #6F5430);
            color: white;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: opacity 0.2s;
        }

        .btn:hover { opacity: 0.9; }

        .close-note {
            margin-top: 16px;
            font-size: 12px;
            color: #c4b49e;
        }
    </style>
</head>
<body>
    <div class="card">
        <!-- Green check icon -->
        <div class="icon-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" style="color:#16a34a">
                <path d="M20 6L9 17l-5-5"/>
            </svg>
        </div>

        <h1>Email Verified!</h1>
        <p>Your email has been successfully verified.<br>You can now access your account.</p>

        <div class="countdown">
            This tab will close in <span id="count">3</span> seconds...
        </div>

        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>

        <a href="{{ $redirectUrl }}" class="btn">
            Go to your account →
        </a>

        <p class="close-note">Or wait for this tab to close automatically.</p>
    </div>

    <script>
        let count = 3;
        const countEl = document.getElementById('count');

        const timer = setInterval(function () {
            count--;
            countEl.textContent = count;

            if (count <= 0) {
                clearInterval(timer);
                // Try to close the tab
                window.close();

                // If window.close() is blocked by the browser,
                // redirect to the main page instead
                setTimeout(function () {
                    window.location.href = '{{ $redirectUrl }}';
                }, 500);
            }
        }, 1000);
    </script>
</body>
</html>
