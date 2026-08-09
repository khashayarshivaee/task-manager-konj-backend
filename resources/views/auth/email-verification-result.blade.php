<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        {{ $title }} | Konj Task Manager
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            background:
                radial-gradient(
                    circle at top,
                    rgba(255, 107, 0, 0.08),
                    transparent 32rem
                ),
                #f7f8fa;

            color: #171717;

            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .page {
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 32px 20px;
        }

        .shell {
            width: 100%;
            max-width: 470px;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;

            gap: 11px;

            margin-bottom: 20px;
        }

        .brand-mark {
            width: 44px;
            height: 44px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 14px;

            background: #ff6b00;
            color: #ffffff;

            font-size: 21px;
            font-weight: 800;

            box-shadow:
                0 10px 24px
                rgba(255, 107, 0, 0.22);
        }

        .brand-copy strong,
        .brand-copy span {
            display: block;
        }

        .brand-copy strong {
            font-size: 16px;
            line-height: 20px;
        }

        .brand-copy span {
            margin-top: 1px;

            color: #8c9199;

            font-size: 11px;
            line-height: 16px;
        }

        .card {
            padding: 42px 36px 36px;

            text-align: center;

            border: 1px solid #e8eaee;
            border-radius: 26px;

            background: rgba(
                255,
                255,
                255,
                0.96
            );

            box-shadow:
                0 20px 55px
                rgba(23, 23, 23, 0.07);
        }

        .status-icon {
            width: 68px;
            height: 68px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 25px;

            border-radius: 22px;

            font-size: 30px;
            font-weight: 700;
        }

        .status-icon.success {
            background: #edf9f1;
            color: #259654;
        }

        .status-icon.error {
            background: #fff0ed;
            color: #d84b32;
        }

        .eyebrow {
            display: inline-flex;

            padding: 7px 11px;

            border-radius: 999px;

            background: #fff3eb;
            color: #e85f00;

            font-size: 10px;
            line-height: 14px;
            font-weight: 750;
            letter-spacing: 0.09em;
        }

        h1 {
            margin: 18px 0 0;

            font-size: 30px;
            line-height: 38px;
            letter-spacing: -0.7px;
        }

        .message {
            margin: 14px auto 0;

            max-width: 350px;

            color: #696e77;

            font-size: 14px;
            line-height: 23px;
        }

        .hint {
            margin-top: 28px;
            padding: 15px 17px;

            border-radius: 14px;

            background: #f7f8fa;
            color: #858a93;

            font-size: 12px;
            line-height: 19px;
        }

        .footer {
            margin-top: 20px;

            text-align: center;

            color: #a2a6ad;

            font-size: 11px;
            line-height: 18px;
        }

        @media (max-width: 520px) {
            .page {
                padding: 22px 14px;
            }

            .card {
                padding:
                    34px 22px
                    28px;
            }

            h1 {
                font-size: 26px;
                line-height: 33px;
            }
        }
    </style>
</head>

<body>
<main class="page">
    <div class="shell">
        <div class="brand">
            <div class="brand-mark">
                K
            </div>

            <div class="brand-copy">
                <strong>
                    Konj
                </strong>

                <span>
                    Task Manager
                </span>
            </div>
        </div>

        <section class="card">
            @if ($verified)
                <div
                    class="status-icon success"
                    aria-hidden="true"
                >
                    ✓
                </div>
            @else
                <div
                    class="status-icon error"
                    aria-hidden="true"
                >
                    !
                </div>
            @endif

            <span class="eyebrow">
                EMAIL VERIFICATION
            </span>

            <h1>
                {{ $title }}
            </h1>

            <p class="message">
                {{ $message }}
            </p>

            <div class="hint">
                @if ($verified)
                    You can safely close this page and
                    return to Konj Task Manager.
                @else
                    Return to Konj Task Manager and
                    request a new verification email.
                @endif
            </div>
        </section>

        <div class="footer">
            © {{ now()->year }} Konj Task Manager
            · Work better together
        </div>
    </div>
</main>
</body>
</html>
