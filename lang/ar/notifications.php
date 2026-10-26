<?php

// Title and body for each notification type. :params come from the notification's data.params.
return [
    'offer_received' => [
        'title' => 'تم استلام العرض',
        'body' => 'لقد تلقيت عرضًا جديدًا من :provider لطلب :service الخاص بك. قارن العروض الآن!',
    ],
    'payment_successful' => [
        'title' => 'تم الدفع بنجاح',
        'body' => 'تم تأكيد حجزك لـ :service يوم :date.',
    ],
    'work_started' => [
        'title' => 'بدأ العمل',
        'body' => 'بدأ مزود الخدمة :provider العمل على طلبك.',
    ],
    'work_completed' => [
        'title' => 'تم إنجاز العمل',
        'body' => 'تم إكمال العمل! يرجى تقييم تجربتك مع :provider.',
    ],

    // Provider side (Notifications in Provider Mode)
    'provider_offer_sent' => [
        'title' => 'تم إرسال العرض',
        'body' => 'تم إرسال عرضك لطلب :service من :customer. سنُعلمك فور اتخاذه القرار.',
    ],
    'provider_booking_confirmed' => [
        'title' => 'تم تأكيد الحجز!',
        'body' => 'قبل :customer عرضك وأكمل الدفع لخدمة :service يوم :date.',
    ],
    'provider_work_started' => [
        'title' => 'بدأ العمل',
        'body' => 'لقد بدأت العمل على طلب :service الخاص بـ :customer.',
    ],
    'provider_work_rated' => [
        'title' => 'تم إنجاز العمل',
        'body' => 'تم تحديد العمل كمكتمل! قام :customer بتقييم خدمتك بـ :rating نجوم.',
    ],
];
