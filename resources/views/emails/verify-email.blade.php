<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Verify your email | Konj Task Manager
    </title>
</head>

<body
    style="
        margin: 0;
        padding: 0;
        background: #f5f6f8;
        font-family:
            -apple-system,
            BlinkMacSystemFont,
            'Segoe UI',
            Arial,
            sans-serif;
        color: #171717;
    "
>
<div
    style="
        display: none;
        max-height: 0;
        overflow: hidden;
        opacity: 0;
    "
>
    Verify your email address to finish setting up
    your Konj Task Manager account.
</div>

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        width: 100%;
        background: #f5f6f8;
    "
>
    <tr>
        <td
            align="center"
            style="
                padding: 48px 16px;
            "
        >
            <table
                role="presentation"
                width="100%"
                cellspacing="0"
                cellpadding="0"
                border="0"
                style="
                    width: 100%;
                    max-width: 600px;
                "
            >
                <tr>
                    <td
                        style="
                            padding-bottom: 18px;
                        "
                    >
                        <table
                            role="presentation"
                            cellspacing="0"
                            cellpadding="0"
                            border="0"
                        >
                            <tr>
                                <td
                                    width="48"
                                    height="48"
                                    align="center"
                                    valign="middle"
                                    style="
                                        width: 48px;
                                        height: 48px;
                                        border-radius: 15px;
                                        background: #ff6b00;
                                        color: #ffffff;
                                        font-size: 22px;
                                        font-weight: 800;
                                    "
                                >
                                    K
                                </td>

                                <td
                                    style="
                                        padding-left: 12px;
                                    "
                                >
                                    <div
                                        style="
                                            font-size: 17px;
                                            line-height: 22px;
                                            font-weight: 750;
                                            color: #171717;
                                        "
                                    >
                                        Konj
                                    </div>

                                    <div
                                        style="
                                            margin-top: 1px;
                                            font-size: 12px;
                                            line-height: 18px;
                                            color: #8a8f98;
                                        "
                                    >
                                        Task Manager
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td
                        style="
                            background: #ffffff;
                            border: 1px solid #e9ebef;
                            border-radius: 24px;
                            padding: 42px 40px;
                            box-shadow:
                                0 14px 40px rgba(20, 24, 32, 0.06);
                        "
                    >
                        <div
                            style="
                                display: inline-block;
                                margin-bottom: 20px;
                                padding: 7px 11px;
                                border-radius: 999px;
                                background: #fff3eb;
                                color: #e85f00;
                                font-size: 11px;
                                line-height: 14px;
                                font-weight: 750;
                                letter-spacing: 0.08em;
                            "
                        >
                            EMAIL VERIFICATION
                        </div>

                        <h1
                            style="
                                margin: 0;
                                font-size: 30px;
                                line-height: 38px;
                                letter-spacing: -0.7px;
                                color: #171717;
                            "
                        >
                            Verify your email
                        </h1>

                        <p
                            style="
                                margin: 22px 0 0;
                                font-size: 15px;
                                line-height: 24px;
                                color: #5f6570;
                            "
                        >
                            Hi {{ $userName }},
                        </p>

                        <p
                            style="
                                margin: 10px 0 0;
                                font-size: 15px;
                                line-height: 24px;
                                color: #5f6570;
                            "
                        >
                            Thanks for creating your Konj Task Manager
                            account. Verify your email address to finish
                            setting up your account and access your
                            workspace.
                        </p>

                        <table
                            role="presentation"
                            cellspacing="0"
                            cellpadding="0"
                            border="0"
                            style="
                                margin-top: 30px;
                            "
                        >
                            <tr>
                                <td
                                    align="center"
                                    bgcolor="#ff6b00"
                                    style="
                                        border-radius: 14px;
                                    "
                                >
                                    <a
                                        href="{{ $verificationUrl }}"
                                        style="
                                            display: inline-block;
                                            padding: 15px 24px;
                                            color: #ffffff;
                                            font-size: 15px;
                                            line-height: 20px;
                                            font-weight: 750;
                                            text-decoration: none;
                                            border-radius: 14px;
                                        "
                                    >
                                        Verify email
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <div
                            style="
                                height: 1px;
                                margin: 34px 0 25px;
                                background: #eef0f3;
                            "
                        ></div>

                        <p
                            style="
                                margin: 0;
                                font-size: 13px;
                                line-height: 21px;
                                color: #8a8f98;
                            "
                        >
                            If you didn't create a Konj Task Manager
                            account, you can safely ignore this email.
                        </p>

                        <p
                            style="
                                margin: 18px 0 7px;
                                font-size: 12px;
                                line-height: 19px;
                                color: #a0a5ad;
                            "
                        >
                            If the button doesn't work, copy and paste
                            this link into your browser:
                        </p>

                        <p
                            style="
                                margin: 0;
                                word-break: break-all;
                                font-size: 11px;
                                line-height: 18px;
                                color: #8a8f98;
                            "
                        >
                            {{ $verificationUrl }}
                        </p>
                    </td>
                </tr>

                <tr>
                    <td
                        align="center"
                        style="
                            padding: 22px 16px 0;
                            font-size: 11px;
                            line-height: 18px;
                            color: #a4a8af;
                        "
                    >
                        © {{ now()->year }} Konj Task Manager
                        <br>
                        Work better together
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
