<?php

namespace recranet\secureforms\services;

use recranet\secureforms\captchas\CaptchaError;
use recranet\secureforms\captchas\CaptchaInterface;
use recranet\secureforms\captchas\RecaptchaEnterprise;
use recranet\secureforms\captchas\RecaptchaV2;
use recranet\secureforms\captchas\RecaptchaV3;
use recranet\secureforms\captchas\Turnstile;
use recranet\secureforms\models\Settings;
use recranet\secureforms\models\SpamVerdict;
use recranet\secureforms\Plugin;
use yii\base\Component;
use yii\web\Request;

/**
 * Runs the spam pipeline: honeypot first, then the configured captcha.
 *
 * Only genuine visitor failures produce a spam verdict. Configuration and
 * availability problems throw a CaptchaError so they surface as real errors.
 */
class SpamService extends Component
{
    /**
     * The active captcha provider, or null when none is configured.
     */
    public function getCaptcha(): ?CaptchaInterface
    {
        $settings = Plugin::getInstance()->getSettings();

        return match ($settings->captchaProvider) {
            Settings::CAPTCHA_RECAPTCHA_V2 => new RecaptchaV2($settings),
            Settings::CAPTCHA_RECAPTCHA_V3 => new RecaptchaV3($settings),
            Settings::CAPTCHA_RECAPTCHA_ENTERPRISE => new RecaptchaEnterprise($settings),
            Settings::CAPTCHA_TURNSTILE => new Turnstile($settings),
            default => null,
        };
    }

    /**
     * Classify the current request.
     *
     * @throws CaptchaError when the captcha cannot be verified due to
     * configuration or availability problems
     */
    public function check(Request $request): SpamVerdict
    {
        $settings = Plugin::getInstance()->getSettings();

        // Honeypot: a filled-in hidden field is a bot, no captcha needed
        if ($settings->honeypotEnabled && trim((string)$request->getBodyParam($settings->honeypotParam)) !== '') {
            return new SpamVerdict(isSpam: true, reason: 'honeypot');
        }

        $captcha = $this->getCaptcha();

        if ($captcha === null) {
            return new SpamVerdict();
        }

        $token = trim((string)$request->getBodyParam($captcha->getResponseParamName()));

        // A missing token means the widget never produced one — typically a
        // blocked script, a broken domain allowlist or a direct bot POST. The
        // submission is stored and the visitor sees an error, so nothing is
        // lost either way.
        if ($token === '') {
            throw new CaptchaError(sprintf(
                '%s token missing from the request — the widget did not run (check the domain allowlist and site key, or the visitor blocks the script)',
                $captcha->getName()
            ));
        }

        $verification = $captcha->verify($token, $request->getUserIP());

        if ($verification->success) {
            return new SpamVerdict(isSpam: false, score: $verification->score);
        }

        $reason = $verification->score !== null
            ? sprintf('captcha-score (%s below threshold)', $verification->score)
            : sprintf('captcha-failed (%s)', implode(', ', $verification->errorCodes) ?: 'invalid token');

        return new SpamVerdict(isSpam: true, score: $verification->score, reason: $reason);
    }
}
