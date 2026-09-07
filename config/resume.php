<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resume parser driver
    |--------------------------------------------------------------------------
    | "rule" — RuleBasedStructurer, no API key required (default).
    | "llm"  — LlmStructurer via the Anthropic API; requires ANTHROPIC_API_KEY.
    |          Falls back to the rule-based parser on any API error, refusal
    |          or invalid response, so parsing never fails because of the LLM.
    */

    'driver' => env('RESUME_PARSER_DRIVER', 'rule'),

    /*
    |--------------------------------------------------------------------------
    | Parse inline (during the upload request)
    |--------------------------------------------------------------------------
    | true  — the resume is parsed before the upload request returns, so the
    |         review screen is ready instantly. Right for shared hosting, where
    |         the queue is only drained once a minute by cron. The rule-based
    |         parser takes a few seconds; the LLM driver can take 10–30 s, so
    |         keep max_execution_time ≥ 120 when combining the two.
    | false — the parse is queued and picked up by the next worker run.
    | Either way, match recomputation stays queued.
    */
    'parse_inline' => (bool) env('RESUME_PARSE_INLINE', true),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
        // Structured JSON for a resume is a few thousand tokens; this is headroom, not a target.
        'max_tokens' => 8000,
        'timeout' => 120,
        // Resumes above this are sent as-is only up to the cap and the tail is
        // logged as dropped — never silently. 60k chars ≈ 20 dense pages.
        'max_chars' => 60000,
    ],

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
