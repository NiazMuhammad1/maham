<?php

// Title and body for each notification type. :params come from the notification's data.params.
return [
    'offer_received' => [
        'title' => 'Offer Received',
        'body' => 'You have received a new offer from :provider for your :service request. Compare offers now!',
    ],
    'payment_successful' => [
        'title' => 'Payment successful',
        'body' => 'Your booking for :service on :date has been confirmed.',
    ],
    'work_started' => [
        'title' => 'Work started',
        'body' => 'Service provider :provider has started working on your request.',
    ],
    'work_completed' => [
        'title' => 'Work completed',
        'body' => 'The work has been completed! Please rate your experience with :provider.',
    ],

    // Provider side (Notifications in Provider Mode)
    'provider_offer_sent' => [
        'title' => 'Offer Sent',
        'body' => 'Your offer for the :service request from :customer has been submitted. We will notify you once they make a decision.',
    ],
    'provider_booking_confirmed' => [
        'title' => 'Booking Confirmed!',
        'body' => ':customer has accepted your offer and completed the payment for :service on :date.',
    ],
    'provider_work_started' => [
        'title' => 'Work started',
        'body' => 'You have started working on the :service request for :customer.',
    ],
    'provider_work_rated' => [
        'title' => 'Work completed',
        'body' => 'The work has been marked as completed! :customer has rated your service :rating stars.',
    ],
];
