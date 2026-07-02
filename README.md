# Secure Forms

Contact forms for Craft CMS 5 with spam protection, stored submissions and proper error reporting. Replaces `craftcms/contact-form`, `hybridinteractive/craft-contact-form-extensions` and `recranet/craft-contact-form-recaptcha` in one plugin.

## Design principles

- **Spam is not an error.** Real spam is stored with its score and reason, silently accepted (configurable), and never logged as an error.
- **Misconfiguration is a real error.** Missing/invalid captcha keys, a domain missing from the allowlist, an unreachable verification API or an SMTP failure are shown to the visitor, logged under the `secure-forms` category, and forwarded to Sentry when the SDK is installed. They never silently classify submissions as spam.
- **Nothing is ever lost.** Submissions are persisted before any email is attempted; send failures are recorded on the submission (`failed` status) instead of dropping the message.

## Features

- Submission storage as elements with a fixed schema — dynamic form fields are stored as JSON and **expanded back into columns on CSV export**
- Persisted spam classification: `isSpam`, `spamScore` (reCAPTCHA v3 score), `spamReason`
- Spam protection: honeypot + Google reCAPTCHA v2/v3 or Cloudflare Turnstile (experimental)
- Notification + optional confirmation emails, rendered from site templates
- **Email / SMTP test utility** in the control panel (works with `allowAdminChanges` disabled) that surfaces full SMTP transport errors
- Control panel section with per-form sources, statuses (sent / spam / failed) and search

## Installation

```bash
composer require recranet/craft-secure-forms
php craft plugin/install secure-forms
```

## Usage

```twig
<form method="post" accept-charset="UTF-8">
	{{ csrfInput() }}
	{{ actionInput('secure-forms/submit') }}
	{{ redirectInput(craft.app.request.pathInfo ~ '?submitted=true') }}
	{{ hiddenInput('formName', 'contact') }}

	{# optional template overrides (hashed, tamper-proof) #}
	{{ hiddenInput('notificationTemplate', '_emails/notifications/contact'|hash) }}
	{{ hiddenInput('confirmationTemplate', '_emails/confirmations/contact'|hash) }}
	{{ hiddenInput('confirmationSubject', 'Thanks!'|hash) }}

	<input type="text" name="fromName" required>
	<input type="email" name="fromEmail" required>
	<textarea name="message[body]" required></textarea>
	{# any other message[...] fields are stored as dynamic fields #}

	{{ craft.secureForms.honeypot() }}
	{{ craft.secureForms.captcha() }}

	<button type="submit">Send</button>
</form>
```

Error handling in the template:

```twig
{% set submission = craft.app.urlManager.getRouteParams().submission ?? null %}
{% if submission %}
	{{ ul(submission.getErrorSummary(true)) }}
{% endif %}
```

## Configuration

All settings are read from the environment with sensible defaults, so one config file works across dev/staging/production. Create `config/secure-forms.php`:

```php
<?php

use craft\helpers\App;

return [
    // Email
    'toEmail' => App::env('SECURE_FORMS_TO_EMAIL') ?: null, // null = system email address
    'prependSubject' => App::env('SECURE_FORMS_PREPEND_SUBJECT') ?: 'New contact form submission',
    'notificationTemplate' => App::env('SECURE_FORMS_NOTIFICATION_TEMPLATE') ?: '_emails/notifications/contact',
    'enableConfirmationEmail' => App::env('SECURE_FORMS_CONFIRMATION_ENABLED') ?? true,
    'confirmationTemplate' => App::env('SECURE_FORMS_CONFIRMATION_TEMPLATE') ?: '_emails/confirmations/contact',

    // Storage
    'saveSubmissions' => App::env('SECURE_FORMS_SAVE_SUBMISSIONS') ?? true,
    'saveSpamSubmissions' => App::env('SECURE_FORMS_SAVE_SPAM_SUBMISSIONS') ?? true,

    // Spam protection — 'silent' pretends success so bots learn nothing
    'spamAction' => App::env('SECURE_FORMS_SPAM_ACTION') ?: 'silent', // or 'error'
    'honeypotEnabled' => App::env('SECURE_FORMS_HONEYPOT_ENABLED') ?? true,
    'honeypotParam' => App::env('SECURE_FORMS_HONEYPOT_PARAM') ?: 'honeyPotProtection',

    // Captcha: '', recaptcha-v2, recaptcha-v3, turnstile (experimental).
    // Defaults to reCAPTCHA v3 when a site key is configured, off otherwise.
    'captchaProvider' => App::env('SECURE_FORMS_CAPTCHA_PROVIDER')
        ?? (App::env('RECAPTCHA_SITE_KEY') ? 'recaptcha-v3' : ''),
    'recaptchaSiteKey' => '$RECAPTCHA_SITE_KEY',
    'recaptchaSecretKey' => '$RECAPTCHA_SECRET_KEY',
    'recaptchaScoreThreshold' => App::env('RECAPTCHA_SCORE_THRESHOLD') ?? 0.5,
    'recaptchaHideBadge' => App::env('RECAPTCHA_HIDE_BADGE') ?? true,
    'turnstileSiteKey' => '$TURNSTILE_SITE_KEY',
    'turnstileSecretKey' => '$TURNSTILE_SECRET_KEY',
];
```

When `recaptchaHideBadge` is enabled, Google requires the reCAPTCHA attribution to be visible in the form, e.g.:

```html
Protected by Google reCAPTCHA. The Google
<a href="https://policies.google.com/privacy">Privacy Policy</a> and
<a href="https://policies.google.com/terms">Terms of Service</a> apply.
```

### Environment variables

Captcha keys live in `.env`:

```bash
# RECAPTCHA (leave empty to disable the captcha in local dev)
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
#RECAPTCHA_SCORE_THRESHOLD=0.5
#RECAPTCHA_HIDE_BADGE=true

# TURNSTILE (Cloudflare, experimental alternative to reCAPTCHA)
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
```

All other settings can optionally be overridden per environment:

```bash
#SECURE_FORMS_TO_EMAIL=
#SECURE_FORMS_PREPEND_SUBJECT="New contact form submission"
#SECURE_FORMS_NOTIFICATION_TEMPLATE=_emails/notifications/contact
#SECURE_FORMS_CONFIRMATION_ENABLED=true
#SECURE_FORMS_CONFIRMATION_TEMPLATE=_emails/confirmations/contact
#SECURE_FORMS_SAVE_SUBMISSIONS=true
#SECURE_FORMS_SAVE_SPAM_SUBMISSIONS=true
#SECURE_FORMS_SPAM_ACTION=silent
#SECURE_FORMS_HONEYPOT_ENABLED=true
#SECURE_FORMS_HONEYPOT_PARAM=honeyPotProtection
#SECURE_FORMS_CAPTCHA_PROVIDER=recaptcha-v3
```

## Email templates

Site templates receive `submission` (the element) and `message` (the decoded dynamic fields):

```twig
<p>{{ submission.fromName }} ({{ submission.fromEmail }}) wrote:</p>
<p>{{ message.body|nl2br }}</p>
```
