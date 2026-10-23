<?php

return [
    'disk' => env('SIGNING_DISK', 'local'),
    'invitation_days' => 7,
    'otp_minutes' => 10,
    'grant_minutes' => 30,
    'max_otp_attempts' => 5,
    'consent_version' => 'mvp-2026-10-v1',
    'consent_text' => 'Li o documento disponibilizado e concordo com seu conteúdo. Confirmo minha intenção de assiná-lo eletronicamente, vinculando este aceite à versão identificada pelo SHA-256 apresentado.',
    'timestamp_driver' => env('TIMESTAMP_DRIVER', 'disabled'),
];
