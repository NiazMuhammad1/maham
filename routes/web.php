<?php

use App\Http\Controllers\Api\V1\BookingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// "Download Receipt" - signed link returned as `receipt_url` in the booking API
Route::get('receipts/{booking}', [BookingController::class, 'receipt'])->middleware('signed')->name('bookings.receipt');
