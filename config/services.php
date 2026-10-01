<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Microsoft Entra ID (M365) – Redaktions-Login
    |--------------------------------------------------------------------------
    |
    | OIDC/OAuth2 Single-Tenant, s. Pflichtenheft 5.1. `tenant` ist die
    | Tenant-ID (keine Wildcard/"common"), damit sich nur Konten des
    | Verbands-Tenants anmelden können. Scopes bewusst minimal gehalten
    | (openid, profile, email) – Verzeichnissuche läuft separat über
    | Microsoft Graph mit User.ReadBasic.All (s. 5.6).
    |
    */
    'microsoft' => [
        'client_id' => env('ENTRA_CLIENT_ID'),
        'client_secret' => env('ENTRA_CLIENT_SECRET'),
        'tenant' => env('ENTRA_TENANT_ID'),
        'redirect' => env('ENTRA_REDIRECT_URI'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Brevo (Transaktionsmails, Newsletter-DOI, Georgsbote)
    |--------------------------------------------------------------------------
    */
    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'newsletter_list_id' => env('BREVO_NEWSLETTER_LIST_ID'),
        'doi_template_id' => env('BREVO_DOI_TEMPLATE_ID'),
        'doi_redirect_url' => env('BREVO_DOI_REDIRECT_URL'),
    ],

    // Instagram API mit Instagram-Login (F-IG). Das Zugangstoken wird nicht
    // hier, sondern verschlüsselt in `integration_tokens` gespeichert und im
    // Backend unter „Integrationen“ eingetragen.
    'instagram' => [
        'graph_url' => 'https://graph.instagram.com',
        'version' => env('INSTAGRAM_GRAPH_VERSION', 'v23.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nextcloud-Kalender (F-CAL): nur diese Hosts dürfen abgerufen werden
    |--------------------------------------------------------------------------
    */
    'nextcloud' => [
        'allowed_hosts' => env('NEXTCLOUD_ALLOWED_HOSTS', ''),
    ],

];
