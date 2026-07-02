<?php

namespace recranet\secureforms\captchas;

use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\Template;
use recranet\secureforms\Plugin;
use Twig\Markup;

/**
 * Google reCAPTCHA v3 (invisible, score-based).
 *
 * The raw score is always returned in the verification result so it can be
 * persisted with the submission.
 */
class RecaptchaV3 extends RecaptchaV2
{
    public function getName(): string
    {
        return 'reCAPTCHA v3';
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
            $score = isset($result['score']) ? (float)$result['score'] : null;
            $passed = $score === null || $score >= $this->settings->getRecaptchaScoreThreshold();

            return new CaptchaVerification($passed, $score);
        }

        $errorCodes = (array)($result['error-codes'] ?? []);
        $this->assertNoConfigError($errorCodes, static::CONFIG_ERROR_CODES);

        return new CaptchaVerification(false, null, $errorCodes);
    }

    public function render(): Markup
    {
        $siteKey = $this->settings->getRecaptchaSiteKey();

        if ($siteKey === '') {
            Plugin::error('reCAPTCHA site key is not configured — the captcha widget cannot be rendered');

            return Template::raw('<!-- secure-forms: reCAPTCHA site key missing -->');
        }

        $inputId = 'fg-recaptcha-' . mt_rand();
        $encodedKey = Json::encode($siteKey);

        // Intercept the submit, fetch a token, then re-submit with the token set.
        // If the reCAPTCHA script was blocked the form submits without a token
        // and the server reports a visible captcha error instead of spam.
        $js = <<<JS
(function () {
    var input = document.getElementById('$inputId');
    var form = input ? input.closest('form') : null;
    if (!form) return;
    var tokenReady = false;
    form.addEventListener('submit', function (e) {
        if (tokenReady || typeof grecaptcha === 'undefined') return;
        e.preventDefault();
        grecaptcha.ready(function () {
            grecaptcha.execute($encodedKey, { action: 'submit' }).then(function (token) {
                input.value = token;
                tokenReady = true;
                if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
            });
        });
    });
})();
JS;

        $html = Html::hiddenInput($this->getResponseParamName(), '', ['id' => $inputId])
            . Html::jsFile("https://www.google.com/recaptcha/api.js?render=$siteKey", ['async' => true, 'defer' => true])
            . Html::script($js);

        return Template::raw($html);
    }
}
