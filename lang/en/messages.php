<?php

return [
    // OTP
    'otp_sms' => 'Your Maham verification code is :code. Do not share it with anyone.',
    'otp_email_subject' => 'Your Maham verification code',
    'otp_email_intro' => 'Use the code below to verify your email address.',
    'otp_email_expiry' => 'This code expires in :minutes minutes. If you did not request it, ignore this email.',
    'otp_sent_phone' => 'We just sent you an OTP.',
    'otp_sent_email' => 'We sent a verification code to your email.',
    'otp_verified' => 'Code verified.',
    'otp_invalid' => 'The verification code is incorrect.',
    'otp_expired' => 'The verification code has expired. Please request a new one.',
    'otp_too_many_attempts' => 'Too many wrong attempts. Please request a new code.',
    'otp_resend_wait' => 'Please wait :seconds seconds before requesting a new code.',
    'otp_send_failed' => 'We could not send the verification code. Please try again later.',

    // Registration
    'phone_verified' => 'Phone verified.',
    'phone_already_registered' => 'This phone number is already registered. Please log in.',
    'phone_not_registered' => 'No account found with this phone number.',
    'email_verified' => 'Email verified.',
    'email_missing' => 'Please add your email address first.',
    'email_already_verified' => 'Your email is already verified.',
    'details_saved' => 'Details saved.',
    'national_id_invalid' => 'Please enter a valid National ID or Residency (Iqama) number.',
    'username_available' => 'Username is available.',
    'username_taken' => 'This username is already taken.',
    'registration_step_not_allowed' => 'This registration step is not available for your account.',
    'registration_incomplete' => 'Please complete the previous steps first (:step).',
    'registration_completed' => 'Your account has been created successfully.',
    'guest_not_allowed' => 'Please create an account to continue.',

    // Login / password
    'login_success' => 'Logged in successfully.',
    'guest_login_success' => 'Welcome! You are browsing as a guest.',
    'logout_success' => 'Logged out successfully.',
    'account_suspended' => 'Your account is suspended. Please contact support.',
    'social_token_invalid' => 'Could not verify your account. Please try again.',
    'reset_token_invalid' => 'Your reset session has expired. Please request a new code.',
    'password_changed' => 'Password changed.',

    // Misc
    'device_token_saved' => 'Device registered for notifications.',

    // Errors
    'unauthenticated' => 'Please log in to continue.',
    'forbidden' => 'You are not allowed to perform this action.',
    'not_found' => 'The requested resource was not found.',
    'too_many_requests' => 'Too many requests. Please try again shortly.',
    'server_error' => 'Something went wrong. Please try again.',
];
