<?php

return [
    // Owner wajib memakai verifikasi dua langkah (aplikasi authenticator).
    'require_owner_2fa' => (bool) env('ERP_REQUIRE_OWNER_2FA', true),

    // Alamat resmi ERP. Di produksi, alamat lain (www., domain sementara Hostinger) dialihkan ke sini.
    'canonical_host' => env('ERP_CANONICAL_HOST', 'asyrunsyumus.com'),
];
