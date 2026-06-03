<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #111827;
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
        }
        .card {
            width: min(92vw, 420px);
            background: #ffffff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
        }
        h1 {
            margin: 0 0 8px;
            font-size: 24px;
        }
        p {
            margin: 0 0 20px;
            color: #4b5563;
            line-height: 1.5;
        }
        label {
            display: block;
            margin: 14px 0 6px;
            font-size: 14px;
            font-weight: 600;
        }
        input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 15px;
        }
        button {
            width: 100%;
            margin-top: 20px;
            border: 0;
            border-radius: 10px;
            padding: 12px 14px;
            background: #111827;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }
        .hint {
            margin-top: 12px;
            font-size: 13px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <main class="card">
        <h1>Reset password</h1>
        <p>Enter a new password for your account. This form submits to the API reset endpoint.</p>

        <form method="POST" action="/api/reset-password">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <label for="password">New password</label>
            <input id="password" name="password" type="password" required minlength="6">

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="6">

            <button type="submit">Reset password</button>
        </form>

        <div class="hint">If you requested this reset by mistake, you can ignore this email.</div>
    </main>
</body>
</html>