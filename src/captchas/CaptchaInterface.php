<?php

namespace recranet\secureforms\captchas;

use Twig\Markup;

/**
 * A captcha provider that can render its widget and verify the token it
 * produces.
 */
interface CaptchaInterface
{
    /** Human-readable provider name for logs and spam reasons */
    public function getName(): string;

    /** Name of the POST parameter carrying the captcha token */
    public function getResponseParamName(): string;

    /**
     * Verify a visitor token.
     *
     * @throws CaptchaError when verification is impossible due to
     * configuration or availability problems (never classify as spam)
     */
    public function verify(string $token, ?string $ip): CaptchaVerification;

    /** Render the widget markup (including any required scripts) */
    public function render(): Markup;
}
