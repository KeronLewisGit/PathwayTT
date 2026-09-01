<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resume parser driver
    |--------------------------------------------------------------------------
    | "rule" — RuleBasedStructurer, no API key required (default).
    | "llm"  — LlmStructurer via the Anthropic API; requires ANTHROPIC_API_KEY.
    |          (Implementation lands in Phase 7; the binding falls back to
    |          "rule" until then.)
    */

    'driver' => env('RESUME_PARSER_DRIVER', 'rule'),

    // Upload constraints — validated by BOTH extension and MIME type.
    'max_size_kb' => 5120, // 5 MB
    'allowed_extensions' => ['pdf', 'docx'],
    'allowed_mimes' => [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    // Uploads per user per hour (privacy + shared-host protection).
    'upload_rate_limit' => 10,

    // Storage disk for resume files. The "local" disk root is
    // storage/app/private — OUTSIDE the public webroot on Hostinger/cPanel
    // layouts, served only via the signed, policy-gated download route.
    'disk' => 'local',
];
