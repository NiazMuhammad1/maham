<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('messages.otp_email_subject') }}</title>
</head>
<body style="margin:0;padding:24px;background:#FFF7EF;font-family:Arial,Helvetica,sans-serif;color:#1F1F1F;">
    <div style="max-width:480px;margin:0 auto;background:#FFFFFF;border-radius:12px;padding:32px;text-align:center;">
        <h1 style="margin:0 0 8px;font-size:22px;color:#1B9E4B;">{{ config('app.name') }}</h1>
        <p style="margin:0 0 24px;font-size:15px;">{{ __('messages.otp_email_intro') }}</p>
        <p style="margin:0 0 24px;font-size:32px;font-weight:bold;letter-spacing:8px;">{{ $code }}</p>
        <p style="margin:0;font-size:13px;color:#515151;">{{ __('messages.otp_email_expiry', ['minutes' => $expiresInMinutes]) }}</p>
    </div>
</body>
</html>
