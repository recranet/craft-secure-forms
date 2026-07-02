<?php

namespace recranet\secureforms\captchas;

/**
 * Result of a captcha token verification for a single visitor.
 */
class CaptchaVerification
{
    public function __construct(
        /** Whether the visitor passed the captcha */
        public readonly bool $success,
        /** Provider score when available (reCAPTCHA v3: 0 = bot, 1 = human) */
        public readonly ?float $score = null,
        /** Provider error codes for failed verifications */
        public readonly array $errorCodes = [],
    ) {
    }
}
