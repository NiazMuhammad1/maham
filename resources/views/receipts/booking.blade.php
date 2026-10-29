@php
    /** @var \App\Models\Booking $booking */
    $isArabic = app()->getLocale() === 'ar';
    $time = $booking->scheduled_time ? \Illuminate\Support\Carbon::createFromTimeString($booking->scheduled_time) : null;
    $payment = $booking->paidPayment;
    $money = fn ($amount) => $booking->currency.' '.number_format((float) $amount, 2);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isArabic ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.receipt.title') }} {{ $booking->receipt_number }}</title>
    <style>
        body { margin: 0; padding: 16px; background: #f4f4f4; font-family: Arial, Tahoma, sans-serif; color: #222; }
        .receipt { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 24px; }
        h1 { margin: 0 0 4px; font-size: 22px; color: #1aa260; text-align: center; }
        .number { text-align: center; color: #777; font-size: 13px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; background: #fff4ec; border-radius: 8px; overflow: hidden; }
        td { padding: 10px 12px; font-size: 14px; vertical-align: top; border-bottom: 1px solid #fde3d0; }
        td:last-child { text-align: end; font-weight: bold; }
        tr.total td { background: #ffc58f; font-size: 16px; }
        .thanks { text-align: center; color: #777; font-size: 13px; margin-top: 20px; }
        button { display: block; width: 100%; margin-top: 16px; padding: 12px; border: 0; border-radius: 8px; background: #1aa260; color: #fff; font-size: 15px; }
        @media print { body { background: #fff; } button { display: none; } }
    </style>
</head>
<body>
<div class="receipt">
    <h1>{{ __('messages.receipt.title') }}</h1>
    <div class="number">{{ __('messages.receipt.receipt_number') }}: {{ $booking->receipt_number }}</div>

    <table>
        <tr><td>{{ __('messages.receipt.booking_reference') }}</td><td>{{ $booking->reference }}</td></tr>
        <tr><td>{{ __('messages.receipt.service') }}</td><td>{{ $booking->service->localized('name') }}</td></tr>
        <tr><td>{{ __('messages.receipt.provider') }}</td><td>{{ $booking->provider->name }}</td></tr>
        <tr><td>{{ __('messages.receipt.customer') }}</td><td>{{ $booking->customer->name }}</td></tr>
        <tr><td>{{ __('messages.receipt.date') }}</td><td>{{ $booking->scheduled_date->locale(app()->getLocale())->translatedFormat('l d M, Y') }}</td></tr>
        @if ($time)
            <tr><td>{{ __('messages.receipt.time') }}</td><td>{{ $time->locale(app()->getLocale())->translatedFormat('h:i A') }}</td></tr>
        @endif
        <tr>
            <td>{{ __('messages.receipt.location') }}</td>
            <td>
                {{ $booking->street_address }}<br>
                {{ collect([$booking->district?->localized('name'), $booking->city?->localized('name'), $booking->postal_code])->filter()->join(' - ') }}
            </td>
        </tr>
        @if ($payment)
            <tr><td>{{ __('messages.receipt.payment_method') }}</td><td>{{ __('messages.payment_method.'.$payment->method) }}</td></tr>
            <tr><td>{{ __('messages.receipt.paid_at') }}</td><td>{{ $payment->paid_at?->locale(app()->getLocale())->translatedFormat('d M, Y h:i A') }}</td></tr>
        @endif
        <tr><td>{{ __('messages.receipt.service_price') }}</td><td>{{ $money($booking->service_price) }}</td></tr>
        @if ((float) $booking->platform_fee > 0)
            <tr><td>{{ __('messages.receipt.processing_fee') }}</td><td>{{ $money($booking->platform_fee) }}</td></tr>
        @endif
        @if ((float) $booking->vat_amount > 0)
            <tr><td>{{ __('messages.receipt.vat') }}</td><td>{{ $money($booking->vat_amount) }}</td></tr>
        @endif
        <tr class="total"><td>{{ __('messages.receipt.total') }}</td><td>{{ $money($booking->total_amount) }}</td></tr>
    </table>

    <div class="thanks">{{ __('messages.receipt.thanks') }}</div>
    <button type="button" onclick="window.print()">{{ __('messages.receipt.print') }}</button>
</div>
</body>
</html>
