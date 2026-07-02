# Release Notes for Secure Forms

## 1.5.0 - 2026-07-02

### Added

- `secure-forms/migrate/contact-form-extensions` console command that copies stored submissions from craft-contact-form-extensions into Secure Forms (idempotent; run before uninstalling the old plugin)

## 1.4.0 - 2026-07-02

### Added

- Hashed `toEmail` form field override for per-form notification recipients (comma separated), compatible with the `craftcms/contact-form` convention

## 1.3.0 - 2026-07-02

### Added

- Status sources in the Submissions index sidebar: "Inbox (sent)", "Spam" and "Failed" alongside "All submissions", with per-form sources grouped under a "Forms" heading and a badge on "Failed"
- `recaptchaRejectThreshold` setting (default 0.3): scores below it are definite spam — rejected outright and not stored — so the reviewable spam list only contains gray-zone submissions between the reject and score thresholds

### Changed

- Honeypot hits are now rejected without being stored (a filled-in honeypot is definitely a bot); previously they were stored as spam
- Unscored captcha failures (invalid/expired token) remain stored as spam for review, since a slow legitimate visitor can trigger them

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
