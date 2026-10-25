<?php

return [
    // OTP
    'otp_sms' => 'رمز التحقق الخاص بك في مهام هو :code. لا تشاركه مع أي شخص.',
    'otp_email_subject' => 'رمز التحقق من مهام',
    'otp_email_intro' => 'استخدم الرمز أدناه للتحقق من بريدك الإلكتروني.',
    'otp_email_expiry' => 'تنتهي صلاحية هذا الرمز خلال :minutes دقائق. إذا لم تطلبه، تجاهل هذه الرسالة.',
    'otp_sent_phone' => 'لقد أرسلنا لك رمز التحقق.',
    'otp_sent_email' => 'أرسلنا رمز التحقق إلى بريدك الإلكتروني.',
    'otp_verified' => 'تم التحقق من الرمز.',
    'otp_invalid' => 'رمز التحقق غير صحيح.',
    'otp_expired' => 'انتهت صلاحية رمز التحقق. يرجى طلب رمز جديد.',
    'otp_too_many_attempts' => 'محاولات خاطئة كثيرة. يرجى طلب رمز جديد.',
    'otp_resend_wait' => 'يرجى الانتظار :seconds ثانية قبل طلب رمز جديد.',
    'otp_send_failed' => 'تعذر إرسال رمز التحقق. يرجى المحاولة لاحقاً.',

    // Registration
    'phone_verified' => 'تم التحقق من رقم الجوال.',
    'phone_already_registered' => 'رقم الجوال مسجل مسبقاً. يرجى تسجيل الدخول.',
    'phone_not_registered' => 'لا يوجد حساب مرتبط بهذا الرقم.',
    'email_verified' => 'تم التحقق من البريد الإلكتروني.',
    'email_missing' => 'يرجى إضافة بريدك الإلكتروني أولاً.',
    'email_already_verified' => 'تم التحقق من بريدك الإلكتروني مسبقاً.',
    'details_saved' => 'تم حفظ البيانات.',
    'national_id_invalid' => 'يرجى إدخال رقم هوية وطنية أو رقم إقامة صحيح.',
    'username_available' => 'اسم المستخدم متاح.',
    'username_taken' => 'اسم المستخدم مستخدم مسبقاً.',
    'registration_step_not_allowed' => 'خطوة التسجيل هذه غير متاحة لحسابك.',
    'registration_incomplete' => 'يرجى إكمال الخطوات السابقة أولاً (:step).',
    'registration_completed' => 'تم إنشاء حسابك بنجاح.',
    'guest_not_allowed' => 'يرجى إنشاء حساب للمتابعة.',

    // Login / password
    'login_success' => 'تم تسجيل الدخول بنجاح.',
    'guest_login_success' => 'مرحباً! أنت تتصفح كزائر.',
    'logout_success' => 'تم تسجيل الخروج بنجاح.',
    'account_suspended' => 'تم إيقاف حسابك. يرجى التواصل مع الدعم.',
    'social_token_invalid' => 'تعذر التحقق من حسابك. يرجى المحاولة مرة أخرى.',
    'reset_token_invalid' => 'انتهت جلسة إعادة التعيين. يرجى طلب رمز جديد.',
    'password_changed' => 'تم تغيير كلمة المرور.',

    // Misc
    'device_token_saved' => 'تم تسجيل الجهاز لاستقبال الإشعارات.',
    'notifications_marked_read' => 'تم تحديد جميع الإشعارات كمقروءة.',
    'language_updated' => 'تم تحديث اللغة.',

    // Account Settings
    'changes_saved' => 'تم حفظ التغييرات.',
    'phone_updated' => 'تم التحقق من رقم الجوال.',
    'phone_missing' => 'يرجى إضافة رقم جوال موثق إلى حسابك أولاً.',
    'phone_same_as_current' => 'هذا هو رقم جوالك الحالي.',
    'phone_taken' => 'رقم الجوال مستخدم في حساب آخر.',

    // Request service
    'request_submitted' => 'تم تقديم الطلب بنجاح.',
    'request_date_past' => 'يرجى اختيار تاريخ اليوم أو تاريخ لاحق.',
    'flexibility' => [
        'fixed' => 'ثابت',
        'flexible' => 'مرن',
        'very_flexible' => 'مرن جداً',
    ],
    'request_status' => [
        'open' => 'مفتوح',
        'accepted' => 'مقبول',
        'cancelled' => 'ملغي',
        'expired' => 'منتهي',
    ],

    // My Requests / Offers / Booking
    'request_cancelled' => 'تم إلغاء طلبك.',
    'request_cannot_be_cancelled' => 'يمكن إلغاء الطلبات المفتوحة فقط.',
    'offer_not_available' => 'لم يعد بالإمكان قبول هذا العرض.',
    'booking_confirmed' => 'تم تأكيد الحجز',
    'payment_failed' => 'لم تتم عملية الدفع. يرجى المحاولة مرة أخرى.',
    'offer_status' => [
        'pending' => 'بانتظار الرد',
        'accepted' => 'مقبول',
        'rejected' => 'لم يتم اختياره',
        'withdrawn' => 'مسحوب',
    ],
    'booking_status' => [
        'pending_payment' => 'بانتظار الدفع',
        'confirmed' => 'مؤكد',
        'in_progress' => 'جاري العمل',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
    ],
    'payment_method' => [
        'card' => 'بطاقة ائتمان/خصم',
        'apple_pay' => 'Apple Pay',
    ],
    'receipt' => [
        'title' => 'إيصال الدفع',
        'receipt_number' => 'رقم الإيصال',
        'booking_reference' => 'رقم الحجز',
        'service' => 'الخدمة',
        'provider' => 'مقدم الخدمة',
        'customer' => 'العميل',
        'date' => 'التاريخ',
        'time' => 'الوقت',
        'location' => 'مكان الخدمة',
        'service_price' => 'سعر الخدمة',
        'vat' => 'ضريبة القيمة المضافة',
        'total' => 'المبلغ المدفوع',
        'payment_method' => 'طريقة الدفع',
        'paid_at' => 'تاريخ الدفع',
        'print' => 'طباعة / حفظ PDF',
        'thanks' => 'شكراً لاستخدامك مهام.',
    ],

    // My Bookings / Booking Details / Rate Provider
    'booking_timeline' => [
        'confirmed' => [
            'title' => 'تم تأكيد الحجز',
            'message' => 'تم تأكيد حجزك لخدمة :service يوم :date الساعة :time.',
        ],
        'in_progress' => [
            'title' => 'جاري العمل',
            'message' => 'بدأ مقدم الخدمة :provider العمل. يمكنك استخدام المحادثة داخل التطبيق لمناقشة أي تفاصيل أثناء الخدمة.',
        ],
        'completed' => [
            'title' => 'مكتمل',
            'message' => 'تم الانتهاء من العمل! يرجى تقييم تجربتك مع :provider ومشاركة رأيك لمساعدة مجتمع مهام.',
            'message_reviewed' => 'تم إنجاز مهمتك!',
        ],
    ],
    'review_submitted' => 'تم إرسال تقييمك بنجاح.',
    'review_not_allowed' => 'يمكنك تقييم مقدم الخدمة بعد اكتمال العمل.',
    'review_already_submitted' => 'لقد قمت بتقييم هذا الحجز مسبقاً.',

    // Provider: Start Work / Complete Work
    'work_started' => 'بدأ العمل. تم إشعار العميل.',
    'work_completed' => 'تم إنجاز العمل. تم إشعار العميل.',
    'booking_cannot_be_started' => 'يمكن بدء الحجوزات المؤكدة فقط.',
    'booking_cannot_be_completed' => 'يمكن إكمال الحجوزات الجارية فقط.',

    // Chat
    'chat_safety_warning' => 'لسلامتك، احرص على أن تكون جميع المدفوعات والمحادثات داخل تطبيق مهام. الاتفاقات خارج التطبيق لا يشملها ضمان مهام.',
    'chat_photo' => 'صورة',

    // Errors
    'unauthenticated' => 'يرجى تسجيل الدخول للمتابعة.',
    'forbidden' => 'غير مسموح لك بتنفيذ هذا الإجراء.',
    'not_found' => 'العنصر المطلوب غير موجود.',
    'too_many_requests' => 'طلبات كثيرة. يرجى المحاولة بعد قليل.',
    'server_error' => 'حدث خطأ ما. يرجى المحاولة مرة أخرى.',
];
