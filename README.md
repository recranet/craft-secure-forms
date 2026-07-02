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

Create `config/secure-forms.php`:

```php
<?php

return [
    'toEmail' => '$SYSTEM_EMAIL',
    'prependSubject' => 'New contact form submission',
    'captchaProvider' => 'recaptcha-v3', // '', recaptcha-v2, recaptcha-v3, turnstile
    'recaptchaSiteKey' => '$RECAPTCHA_SITE_KEY',
    'recaptchaSecretKey' => '$RECAPTCHA_SECRET_KEY',
    'recaptchaScoreThreshold' => 0.5,
    'honeypotEnabled' => true,
    'honeypotParam' => 'fg_website',
    'enableConfirmationEmail' => true,
    'spamAction' => 'silent', // or 'error'
];
```

## Email templates

Site templates receive `submission` (the element) and `message` (the decoded dynamic fields):

```twig
<p>{{ submission.fromName }} ({{ submission.fromEmail }}) wrote:</p>
<p>{{ message.body|nl2br }}</p>
```
