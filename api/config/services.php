<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    // Android App Links: lets the app open https://kosten.codekorb.de links (invitation, password reset)
    'android' => [
        'package_name' => env('ANDROID_PACKAGE_NAME', 'de.codekorb.kosten'),
        'sha256_cert_fingerprints' => array_values(array_filter(array_map('trim', explode(',', (string) env('ANDROID_SHA256_CERT_FINGERPRINTS', ''))))),
    ],

    // Expo push service (Android via FCM). The access token is optional ("enhanced security" in Expo).
    'expo' => [
        'push_url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
        'access_token' => env('EXPO_ACCESS_TOKEN'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
