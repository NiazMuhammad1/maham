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
    'notifications_marked_read' => 'All notifications marked as read.',
    'language_updated' => 'Language updated.',

    // Account Settings
    'changes_saved' => 'Changes saved.',
    'phone_updated' => 'Phone number verified.',
    'phone_missing' => 'Please add a verified phone number to your account first.',
    'phone_same_as_current' => 'This is already your current phone number.',
    'phone_taken' => 'This phone number is used by another account.',

    // Request service
    'request_submitted' => 'Your request submitted successfully.',
    'request_date_past' => 'Please pick today or a later date.',
    'flexibility' => [
        'fixed' => 'Fixed',
        'flexible' => 'Flexible',
        'very_flexible' => 'Very flexible',
    ],
    'request_status' => [
        'open' => 'Open',
        'accepted' => 'Accepted',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
    ],

    // My Requests / Offers / Booking
    'request_cancelled' => 'Your request has been cancelled.',
    'request_cannot_be_cancelled' => 'Only open requests can be cancelled.',
    'offer_not_available' => 'This offer can no longer be accepted.',
    'booking_confirmed' => 'Booking Confirmed',
    'payment_failed' => 'Payment was not completed. Please try again.',
    'offer_status' => [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'rejected' => 'Not selected',
        'withdrawn' => 'Withdrawn',
    ],
    'booking_status' => [
        'pending_payment' => 'Pending payment',
        'confirmed' => 'Confirmed',
        'in_progress' => 'Work in progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],
    'payment_method' => [
        'card' => 'Credit/Debit Card',
        'apple_pay' => 'Apple Pay',
    ],
    'receipt' => [
        'title' => 'Payment Receipt',
        'receipt_number' => 'Receipt No.',
        'booking_reference' => 'Booking No.',
        'service' => 'Service',
        'provider' => 'Service Provider',
        'customer' => 'Customer',
        'date' => 'Date',
        'time' => 'Time',
        'location' => 'Service Location',
        'service_price' => 'Service Price',
        'vat' => 'VAT',
        'total' => 'Total Paid',
        'payment_method' => 'Payment Method',
        'paid_at' => 'Paid On',
        'print' => 'Print / Save as PDF',
        'thanks' => 'Thank you for using Maham.',
    ],

    // My Bookings / Booking Details / Rate Provider
    'booking_timeline' => [
        'confirmed' => [
            'title' => 'Booking Confirmed',
            'message' => 'Your booking for :service is confirmed for :date at :time.',
        ],
        'in_progress' => [
            'title' => 'Work InProgress',
            'message' => 'Your provider :provider has started the work. Feel free to use the in-app chat if you need to discuss any details during the service.',
        ],
        'completed' => [
            'title' => 'Completed',
            'message' => 'Your work is finished! Please rate your experience with :provider and share your feedback to help the Maham community.',
            'message_reviewed' => 'Your task is finished!',
        ],
    ],
    'review_submitted' => 'Your review submitted successfully.',
    'review_not_allowed' => 'You can rate the provider once the work is completed.',
    'review_already_submitted' => 'You have already rated this booking.',

    // Provider: Start Work / Complete Work
    'work_started' => 'Work started. The customer has been notified.',
    'work_completed' => 'Work completed. The customer has been notified.',
    'booking_cannot_be_started' => 'Only confirmed bookings can be started.',
    'booking_cannot_be_completed' => 'Only bookings in progress can be completed.',

    // Become a Service Provider
    'provider_application_not_allowed' => 'Please complete your account registration before applying as a service provider (:step).',
    'provider_application_incomplete' => 'Please complete the previous steps first (:step).',
    'provider_application_submitted' => 'Application Submitted!',
    'provider_application_under_review' => 'Your application is under review. You can edit it again only if it is rejected.',
    'provider_application_closed' => 'Your service provider application can no longer be changed.',

    // Chat
    'chat_safety_warning' => 'For your safety, keep all payments and conversations inside Maham. Deals made outside the app are not covered by the Maham guarantee.',
    'chat_photo' => 'Photo',

    // Errors
    'unauthenticated' => 'Please log in to continue.',
    'forbidden' => 'You are not allowed to perform this action.',
    'not_found' => 'The requested resource was not found.',
    'too_many_requests' => 'Too many requests. Please try again shortly.',
    'server_error' => 'Something went wrong. Please try again.',
];
