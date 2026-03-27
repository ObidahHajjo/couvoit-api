<?php

return [
    'auth' => [
        'register_success' => 'تم التسجيل بنجاح.',
        'login_success' => 'تم تسجيل الدخول بنجاح.',
        'refresh_success' => 'تم تحديث الجلسة بنجاح.',
        'logout_success' => 'تم تسجيل الخروج بنجاح',
        'forgot_password_notice' => 'إذا كان هناك حساب مرتبط بهذا البريد الإلكتروني، فقد تم إرسال رابط إعادة تعيين كلمة المرور.',
        'password_reset_success' => 'تمت إعادة تعيين كلمة المرور بنجاح.',
        'password_changed_success' => 'تم تغيير كلمة المرور بنجاح.',
    ],
    'chat' => [
        'conversation_cleared' => 'تم مسح المحادثة لهذا الحساب فقط.',
        'message_cleared' => 'تم مسح الرسالة لهذا الحساب فقط.',
        'messages_cleared' => 'تم مسح الرسائل المحددة لهذا الحساب فقط.',
    ],
    'errors' => [
        'validation' => 'البيانات المقدمة غير صالحة.',
        'forbidden' => 'ممنوع.',
        'route_not_found' => 'المسار غير موجود.',
        'model_not_found' => 'العنصر :model غير موجود.',
        'foreign_key_constraint' => 'انتهاك قيد المفتاح الخارجي.',
        'invalid_input_syntax' => 'صيغة الإدخال غير صالحة.',
        'database_query' => 'خطأ في استعلام قاعدة البيانات.',
        'server_error' => 'خطأ في الخادم',
        'missing_bearer_token' => 'رمز Bearer مفقود',
        'invalid_jwt_format' => 'تنسيق JWT غير صالح',
        'invalid_token_payload' => 'محتوى الرمز غير صالح.',
        'unauthorized' => 'غير مصرح',
        'account_inactive' => 'الحساب غير نشط',
        'token_expired' => 'انتهت صلاحية الرمز',
        'trip.date_time_in_past' => 'يجب أن يكون تاريخ ووقت الرحلة في المستقبل.'
    ],
];
