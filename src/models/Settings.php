<?php

namespace recranet\secureforms\models;

use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;

/**
 * Secure Forms settings.
 *
 * All key/email fields support environment variable references ($VAR_NAME).
 */
class Settings extends Model
{
    // Captcha providers
    public const CAPTCHA_NONE = '';
    public const CAPTCHA_RECAPTCHA_V2 = 'recaptcha-v2';
    public const CAPTCHA_RECAPTCHA_V3 = 'recaptcha-v3';
    public const CAPTCHA_RECAPTCHA_ENTERPRISE = 'recaptcha-enterprise';
    public const CAPTCHA_TURNSTILE = 'turnstile';

    // What the visitor sees when their submission is classified as spam
    public const SPAM_ACTION_SILENT = 'silent';
    public const SPAM_ACTION_ERROR = 'error';

    // Email
    /** @var string|null Recipient(s) of notification emails, comma separated. Defaults to the system email address. */
    public ?string $toEmail = null;
    /** @var string|null Prefix for the notification email subject */
    public ?string $prependSubject = null;
    /** @var string|null Sender name prefix used in the notification email */
    public ?string $prependSender = null;
    /** @var string|null Site template for the notification email body (falls back to a built-in template) */
    public ?string $notificationTemplate = null;
    /** @var bool Send a confirmation email to the submitter */
    public bool $enableConfirmationEmail = false;
    /** @var string|null Site template for the confirmation email body */
    public ?string $confirmationTemplate = null;
    /** @var string|null Subject for the confirmation email */
    public ?string $confirmationSubject = null;

    // Storage
    /** @var bool Save submissions to the database */
    public bool $saveSubmissions = true;
    /** @var bool Also save submissions classified as spam (with their spam score) */
    public bool $saveSpamSubmissions = true;

    // Spam protection
    /** @var string How to respond when a submission is classified as spam ('silent' pretends success; 'error' shows a validation error) */
    public string $spamAction = self::SPAM_ACTION_SILENT;
    /** @var bool Enable the built-in honeypot field */
    public bool $honeypotEnabled = true;
    /** @var string Name of the honeypot form field */
    public string $honeypotParam = 'fg_website';
    /** @var string Active captcha provider */
    public string $captchaProvider = self::CAPTCHA_NONE;
    /** @var string|null reCAPTCHA site key */
    public ?string $recaptchaSiteKey = null;
    /** @var string|null reCAPTCHA secret key */
    public ?string $recaptchaSecretKey = null;
    /** @var float|string Minimum reCAPTCHA v3 score (0–1) required to not be classified as spam */
    public float|string $recaptchaScoreThreshold = 0.5;
    /** @var float|string Scores below this value (0–1) are definite spam: rejected outright and not stored. Between this and the score threshold, submissions are stored as spam for review. */
    public float|string $recaptchaRejectThreshold = 0.3;
    /** @var bool Hide the reCAPTCHA v3 badge (Google requires visible attribution text in your form when enabled) */
    public bool $recaptchaHideBadge = false;
    /** @var string|null Google Cloud project ID (reCAPTCHA Enterprise only) */
    public ?string $recaptchaProjectId = null;
    /** @var string|null Google Cloud API key for assessments (reCAPTCHA Enterprise only) */
    public ?string $recaptchaApiKey = null;
    /** @var string|null Cloudflare Turnstile site key (experimental) */
    public ?string $turnstileSiteKey = null;
    /** @var string|null Cloudflare Turnstile secret key (experimental) */
    public ?string $turnstileSecretKey = null;

    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'toEmail',
                    'recaptchaSiteKey',
                    'recaptchaSecretKey',
                    'recaptchaProjectId',
                    'recaptchaApiKey',
                    'turnstileSiteKey',
                    'turnstileSecretKey',
                ],
            ],
        ];
    }

    protected function defineRules(): array
    {
        return [
            [['captchaProvider'], 'in', 'range' => [
                self::CAPTCHA_NONE,
                self::CAPTCHA_RECAPTCHA_V2,
                self::CAPTCHA_RECAPTCHA_V3,
                self::CAPTCHA_RECAPTCHA_ENTERPRISE,
                self::CAPTCHA_TURNSTILE,
            ]],
            [['spamAction'], 'in', 'range' => [self::SPAM_ACTION_SILENT, self::SPAM_ACTION_ERROR]],
            [['honeypotParam'], 'required'],
            [['recaptchaScoreThreshold', 'recaptchaRejectThreshold'], 'number', 'min' => 0, 'max' => 1],
            [['recaptchaRejectThreshold'], function(string $attribute) {
                if ($this->getRecaptchaRejectThreshold() > $this->getRecaptchaScoreThreshold()) {
                    $this->addError($attribute, \Craft::t('secure-forms', 'The reject threshold cannot be higher than the score threshold.'));
                }
            }],
        ];
    }

    /**
     * Resolved notification recipients. Falls back to the system email address.
     *
     * @return string[]
     */
    public function getToEmails(): array
    {
        $toEmail = trim((string)App::parseEnv($this->toEmail));

        if ($toEmail === '') {
            $toEmail = (string)App::parseEnv(App::mailSettings()->fromEmail);
        }

        return array_values(array_filter(array_map('trim', explode(',', $toEmail))));
    }

    public function getRecaptchaSiteKey(): string
    {
        return trim((string)App::parseEnv($this->recaptchaSiteKey));
    }

    public function getRecaptchaSecretKey(): string
    {
        return trim((string)App::parseEnv($this->recaptchaSecretKey));
    }

    public function getRecaptchaProjectId(): string
    {
        return trim((string)App::parseEnv($this->recaptchaProjectId));
    }

    public function getRecaptchaApiKey(): string
    {
        return trim((string)App::parseEnv($this->recaptchaApiKey));
    }

    public function getTurnstileSiteKey(): string
    {
        return trim((string)App::parseEnv($this->turnstileSiteKey));
    }

    public function getTurnstileSecretKey(): string
    {
        return trim((string)App::parseEnv($this->turnstileSecretKey));
    }

    public function getRecaptchaScoreThreshold(): float
    {
        return (float)$this->recaptchaScoreThreshold;
    }

    public function getRecaptchaRejectThreshold(): float
    {
        return (float)$this->recaptchaRejectThreshold;
    }
}
