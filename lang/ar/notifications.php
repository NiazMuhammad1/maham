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
];
