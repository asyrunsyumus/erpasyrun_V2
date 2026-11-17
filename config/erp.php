<?php

return [
    // Owner wajib memakai verifikasi dua langkah (aplikasi authenticator).
    'require_owner_2fa' => (bool) env('ERP_REQUIRE_OWNER_2FA', true),
];
