<?php

namespace recranet\secureforms\captchas;

use craft\helpers\Html;
use craft\helpers\Template;
use recranet\secureforms\Plugin;
use Twig\Markup;

/**
 * Google reCAPTCHA v2 ("I'm not a robot" checkbox).
 */
class RecaptchaV2 extends BaseCaptcha
{
    protected const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /** Error codes caused by configuration, not by the visitor */
    protected const CONFIG_ERROR_CODES = [
        'missing-input-secret',
        'invalid-input-secret',
        'invalid-keys',
        'sitekey-secret-mismatch',
        'bad-request',
    ];

    public function getName(): string
    {
        return 'reCAPTCHA v2';
    }

    public function getResponseParamName(): string
    {
        return 'g-recaptcha-response';
    }

    public function verify(string $token, ?string $ip): CaptchaVerification
    {
        $secretKey = $this->settings->getRecaptchaSecretKey();

        if ($secretKey === '') {
            throw new CaptchaError('reCAPTCHA secret key is not configured');
        }

        $result = $this->siteVerify(static::VERIFY_URL, [
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $ip,
        ]);

        if ($result['success'] ?? false) {
            return new CaptchaVerification(true);
        }

        $errorCodes = (array)($result['error-codes'] ?? []);
        $this->assertNoConfigError($errorCodes, static::CONFIG_ERROR_CODES);

        // Remaining error codes (invalid/expired token) are visitor problems: spam
        return new CaptchaVerification(false, null, $errorCodes);
    }

    public function render(): Markup
    {
        $siteKey = $this->settings->getRecaptchaSiteKey();

        if ($siteKey === '') {
            Plugin::error('reCAPTCHA site key is not configured — the captcha widget cannot be rendered');

            return Template::raw('<!-- secure-forms: reCAPTCHA site key missing -->');
        }

        $html = Html::tag('div', '', ['class' => 'g-recaptcha', 'data' => ['sitekey' => $siteKey]])
            . Html::jsFile('https://www.google.com/recaptcha/api.js', ['async' => true, 'defer' => true]);

        return Template::raw($html);
    }
}
