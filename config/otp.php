<?php

return [

    /*
    | Number of digits in a generated code. The app design uses 4 input boxes.
    */
    'length' => (int) env('OTP_LENGTH', 4),

    /*
    | Seconds a code stays valid, and seconds before a new code may be requested.
    */
    'expires_seconds' => (int) env('OTP_EXPIRES_SECONDS', 300),

    'resend_seconds' => (int) env('OTP_RESEND_SECONDS', 60),

    /*
    | Wrong attempts allowed before the code is burned and a new one is required.
    */
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    /*
    | A fixed code for local development and testing so no SMS is needed.
    | It is ignored in production.
    */
    'fixed_code' => env('OTP_FIXED_CODE'),

];
