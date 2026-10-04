<?php

/*
|--------------------------------------------------------------------------
| WhatsApp-Newsletter (docs/whatsapp_newsletter.md)
|--------------------------------------------------------------------------
|
| Zugangsdaten (Token, App-Secret, IDs) stehen verschlüsselt in der Tabelle
| integration_tokens und werden im Backend unter „WhatsApp-Verbindung“
| gepflegt. Hier nur Verhalten und öffentliche Angaben.
|
*/

return [
    /*
    | Welche Variante ist eingeschaltet (docs/entscheidungen/whatsapp-kostenlos-oder-bezahlt.md)?
    | off     – nichts sichtbar (Standard, beide Varianten nur vorbereitet)
    | channel – kostenloser WhatsApp-Kanal: /whatsapp zeigt den Kanal, im
    |           Backend „Für WhatsApp-Kanal kopieren“ an Beiträgen/Veranstaltungen
    | api     – WhatsApp Business (Cloud API): START/STOP, Aussendungen aus dem CMS
    */
    'mode' => in_array(env('WHATSAPP_MODE'), ['channel', 'api'], true) ? env('WHATSAPP_MODE') : 'off',

    // Variante „channel“: Einladungslink des Kanals (WhatsApp → Kanal → Teilen).
    'channel_url' => env('WHATSAPP_CHANNEL_URL'),

    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),

    // Büro-Festnetznummer, über die der Newsletter läuft (E.164 und Anzeige).
    'public_number' => env('WHATSAPP_PUBLIC_NUMBER', '+4965146298290'),
    'public_number_display' => env('WHATSAPP_PUBLIC_NUMBER_DISPLAY', '0651 4629829-0'),

    // Weg B (Formular mit Bestätigungs-Vorlage) – standardmäßig aus.
    'form_doi' => (bool) env('WHATSAPP_FORM_DOI', false),

    // Freigabe nur durch eine zweite Person.
    'four_eyes' => (bool) env('WHATSAPP_FOUR_EYES', true),

    // Versand: Empfänger je Minute (Cronjob-Worker läuft max. 50 s).
    'per_minute' => (int) env('WHATSAPP_PER_MINUTE', 60),

    // Geschätzter Preis je zugestellter Marketing-Nachricht (Anzeige vor Freigabe).
    'price_marketing_eur' => (float) env('WHATSAPP_PRICE_MARKETING_EUR', 0.13),

    // Testumgebung: nur an diese Nummern senden (kommagetrennt). Aktiv, sobald
    // auch die Mail-Testumleitung aktiv ist (test.dpsg-trier.de).
    'test_mode' => (bool) env('WHATSAPP_TEST_MODE', filled(env('MAIL_TEST_REDIRECT_TO'))),
    'test_allowlist' => env('WHATSAPP_TEST_ALLOWLIST', ''),

    'consent_version' => '2026-10',

    'keywords' => [
        'start' => ['START', 'ANMELDEN'],
        'stop' => ['STOP', 'STOPP', 'ABMELDEN', 'ENDE', 'ABBESTELLEN'],
    ],

    'retention' => [
        'pending_hours' => 48,
        'deliveries_days' => 30,
        'suppression_months' => 12,
    ],
];
