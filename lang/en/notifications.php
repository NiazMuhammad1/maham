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
];
