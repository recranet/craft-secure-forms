<?php

namespace recranet\secureforms\models;

/**
 * Outcome of the spam pipeline for a single submission.
 */
class SpamVerdict
{
    public function __construct(
        public bool $isSpam = false,
        /** Captcha score when the provider reports one (reCAPTCHA v3) */
        public ?float $score = null,
        /** Why the submission was classified as spam (honeypot, captcha-failed, captcha-score) */
        public ?string $reason = null,
    ) {
    }
}
