<?php

return [
    /*
     * Службовий обліковий запис Firebase (JSON, Project settings →
     * Service accounts → Generate new private key) — потрібен лише для
     * відправки, не для отримання. Без нього MobilePushSender мовчки
     * нічого не шле, так само як TelegramClient без токена чи
     * WebPushSender без VAPID-ключів. Файл лежить поза git
     * (storage/app/ — у .gitignore), шлях налаштовується через .env.
     */
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-service-account.json')),
];
