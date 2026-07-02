# Release Notes for Secure Forms

## 1.2.0 - 2026-07-02

### Added

- Control panel nav badge showing the number of submissions whose notification email never went out (failed sends need attention; spam is excluded)
- Manual delivery from the submission detail view: retry failed sends, or deliver a reviewed spam submission (marks it as not spam, keeping the score and reason for the record)
- README section documenting the failure flow, and an agency-focused warning that the free reCAPTCHA assessment quota is shared organization-wide across all keys

## 1.1.0 - 2026-07-02

### Added

- reCAPTCHA Enterprise provider (`recaptcha-enterprise`): verifies tokens via the `createAssessment` API using a Google Cloud project ID and API key, persists the real risk score, and fails closed (visible error) on quota instead of siteverify's silent fail-open
- Documentation for the Google reCAPTCHA classic deprecation, Cloud key management (legacy secret keys, `allowedDomains`) and assessment quota/billing tiers

## 1.0.0 - 2026-07-02

### Added

- Initial release
- Contact form submission handling via the `secure-forms/submit` controller (replaces `craftcms/contact-form`, `craft-contact-form-extensions` and `craft-contact-form-recaptcha`)
- Submission storage as elements with sent/spam/failed statuses; dynamic form fields stored as JSON in a fixed schema
- Persisted spam classification per submission: `isSpam`, `spamScore` (reCAPTCHA v3 score) and `spamReason`
- Spam protection: built-in honeypot plus Google reCAPTCHA v2/v3 and Cloudflare Turnstile (experimental)
- Optional `recaptchaHideBadge` setting that hides the reCAPTCHA v3 badge (show the attribution text in your form instead)
- Captcha configuration problems (missing/invalid keys, domain not allowlisted, unreachable verification API) are reported as visible errors and logged — never silently classified as spam
- Submissions are persisted before any email is attempted; send failures are recorded on the submission so no message is ever lost
- Notification and confirmation emails rendered from site templates, with hashed per-form overrides
- CSV export that expands the JSON message fields into per-field columns
- Email / SMTP test utility in the control panel exposing full transport error detail (works with `allowAdminChanges` disabled)
- Error logging under the `secure-forms` category with automatic forwarding to Sentry when the Sentry SDK is installed
- Dutch translations for all visitor-facing messages
