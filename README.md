# Secure Forms

Contact forms for Craft CMS 5 with spam protection, stored submissions and proper error reporting. Replaces `craftcms/contact-form`, `hybridinteractive/craft-contact-form-extensions` and `recranet/craft-contact-form-recaptcha` in one plugin.

## Design principles

- **Spam is not an error.** Real spam is stored with its score and reason, silently accepted (configurable), and never logged as an error.
- **Misconfiguration is a real error.** Missing/invalid captcha keys, a domain missing from the allowlist, an unreachable verification API or an SMTP failure are shown to the visitor, logged under the `secure-forms` category, and forwarded to Sentry when the SDK is installed. They never silently classify submissions as spam.
- **Nothing is ever lost.** Submissions are persisted before any email is attempted; send failures are recorded on the submission (`failed` status) instead of dropping the message.

## Features

- Submission storage as elements with a fixed schema — dynamic form fields are stored as JSON and **expanded back into columns on CSV export**
- Persisted spam classification: `isSpam`, `spamScore` (reCAPTCHA v3 score), `spamReason`
- Spam protection: honeypot + Google reCAPTCHA v2/v3/Enterprise or Cloudflare Turnstile (experimental)
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

    // Captcha: '', recaptcha-v2, recaptcha-v3, recaptcha-enterprise,
    // turnstile (experimental). Defaults to reCAPTCHA v3 when a site key is
    // configured, off otherwise.
    'captchaProvider' => App::env('SECURE_FORMS_CAPTCHA_PROVIDER')
        ?? (App::env('RECAPTCHA_SITE_KEY') ? 'recaptcha-v3' : ''),
    'recaptchaSiteKey' => '$RECAPTCHA_SITE_KEY',
    'recaptchaSecretKey' => '$RECAPTCHA_SECRET_KEY',
    'recaptchaScoreThreshold' => App::env('RECAPTCHA_SCORE_THRESHOLD') ?? 0.5,
    'recaptchaHideBadge' => App::env('RECAPTCHA_HIDE_BADGE') ?? true,
    // Only used by the recaptcha-enterprise provider (createAssessment API)
    'recaptchaProjectId' => '$RECAPTCHA_PROJECT_ID',
    'recaptchaApiKey' => '$RECAPTCHA_API_KEY',
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
# Use the *legacy secret key* from the Google Cloud console — see the
# "Google reCAPTCHA deprecation notes" section below
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
#RECAPTCHA_SCORE_THRESHOLD=0.5
#RECAPTCHA_HIDE_BADGE=true
# Only for the recaptcha-enterprise provider (createAssessment API)
#RECAPTCHA_PROJECT_ID=
#RECAPTCHA_API_KEY=

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

### Google reCAPTCHA deprecation notes

Google deprecated classic reCAPTCHA and migrated all keys to Google Cloud projects (automated migration completed Q1 2026; keys without a Cloud project have API access locked). What this means for this plugin:

- **Keys live in a Google Cloud project** now, created either in the [Cloud console](https://console.cloud.google.com/security/recaptcha) or programmatically via the reCAPTCHA Enterprise API ([`projects.keys.create`](https://cloud.google.com/recaptcha/docs/reference/rest/v1/projects.keys/create) with `webSettings.integrationType` `SCORE` (v3) or `CHECKBOX` (v2) and the `allowedDomains` list). The key ID is what goes into `RECAPTCHA_SITE_KEY`.
- This plugin verifies tokens via the legacy [`siteverify`](https://developers.google.com/recaptcha/docs/verify) endpoint, which Google keeps supported for migrated and Enterprise-created keys. Use the **legacy secret key** as `RECAPTCHA_SECRET_KEY` — available in the Cloud console under *Integration → Use legacy key*, or via the [`retrieveLegacySecretKey`](https://cloud.google.com/recaptcha/docs/reference/rest/v1/projects.keys/retrieveLegacySecretKey) API method.
- Keep the key's `allowedDomains` in sync with the site's domains — a domain missing from the allowlist stops the widget from producing tokens, which this plugin reports as a visible error (never as silent spam).
- **Quota**: without a billing account (Essentials tier) reCAPTCHA includes 10,000 free assessments per month, **aggregated across the whole Google Cloud organization** — all sites and keys share the pool. Beyond it, `siteverify` *fails open* (returns `success: true` with a fixed `0.9` score instead of verifying), silently degrading spam protection. Linking a billing account (Standard tier) keeps the same 10,000 free but replaces the fail-open cliff with paid verification: a flat $8 covers up to 100,000 assessments/month, then $1 per 1,000. This plugin only generates assessments on actual form submits (not page views), so the quota maps 1:1 to submission attempts. See [reCAPTCHA billing](https://docs.cloud.google.com/recaptcha/docs/billing-information) and [tier comparison](https://docs.cloud.google.com/recaptcha/docs/compare-tiers).
- Google's long-term replacement for `siteverify` is the Enterprise [`createAssessment`](https://cloud.google.com/recaptcha/docs/reference/rest/v1/projects.assessments/create) API, supported by this plugin as the `recaptcha-enterprise` provider (requires `RECAPTCHA_PROJECT_ID` + `RECAPTCHA_API_KEY`). It returns the real risk score and *fails closed* on quota (HTTP 429, surfaced as a visible error) instead of silently passing. **Cloudflare Turnstile** (free, no quota) remains available as a non-Google alternative.

## Email templates

Site templates receive `submission` (the element) and `message` (the decoded dynamic fields):

```twig
<p>{{ submission.fromName }} ({{ submission.fromEmail }}) wrote:</p>
<p>{{ message.body|nl2br }}</p>
```
