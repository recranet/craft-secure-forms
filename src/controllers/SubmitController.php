<?php

namespace recranet\secureforms\controllers;

use Craft;
use craft\helpers\Json;
use craft\web\Controller;
use recranet\secureforms\captchas\CaptchaError;
use recranet\secureforms\elements\Submission;
use recranet\secureforms\models\Settings;
use recranet\secureforms\Plugin;
use yii\validators\EmailValidator;
use yii\web\Response;

/**
 * Handles public contact form submissions (POST secure-forms/submit).
 *
 * Order of operations is deliberate:
 * 1. validate input (invalid input is the visitor's problem — nothing stored)
 * 2. run the spam pipeline (config errors abort with a visible error, but the
 *    submission is still stored so nothing is lost)
 * 3. persist the submission — including spam, with its score
 * 4. send emails (transport failures are stored on the submission, logged and
 *    shown to the visitor)
 */
class SubmitController extends Controller
{
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_LIVE;

    public function actionIndex(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();

        $submission = $this->buildSubmission();

        // Hashed overrides — set via |hash in templates, so only developers
        // control them; tampering fails the hash check with a 400
        $notificationTemplate = $this->validatedParam('notificationTemplate');
        $confirmationTemplate = $this->validatedParam('confirmationTemplate');
        $confirmationSubject = $this->validatedParam('confirmationSubject');
        $toEmail = $this->validatedParam('toEmail');

        if (!$this->validateSubmission($submission)) {
            return $this->asModelFailure(
                $submission,
                Craft::t('secure-forms', 'Your message could not be sent. Please check the highlighted fields.'),
                'submission'
            );
        }

        // Spam pipeline — configuration/availability problems are real
        // errors: store the submission, tell the visitor, alert us
        try {
            $verdict = $plugin->spam->check($request);
        } catch (CaptchaError $e) {
            Plugin::error('Captcha verification failed due to a configuration or availability problem', $e);
            $submission->sendError = 'captcha: ' . $e->getMessage();
            $this->saveSubmission($submission, $settings);

            return $this->asModelFailure(
                $submission,
                Craft::t('secure-forms', 'Your message could not be verified at the moment. Please try again later.'),
                'submission'
            );
        }

        $submission->isSpam = $verdict->isSpam;
        $submission->spamScore = $verdict->score;
        $submission->spamReason = $verdict->reason;

        // Definite spam (honeypot, score below the reject threshold) is
        // rejected without being stored — the reviewable spam list only
        // keeps gray-zone submissions that might be false positives
        if ($verdict->isSpam && $verdict->reject) {
            Plugin::info(sprintf(
                'Submission from %s rejected as definite spam (not stored): %s (score: %s)',
                $submission->fromEmail,
                $verdict->reason,
                $verdict->score ?? 'n/a'
            ));

            return $this->spamResponse($submission, $settings);
        }

        // Persist before any email is attempted, so nothing is ever lost
        $this->saveSubmission($submission, $settings);

        if ($submission->isSpam) {
            Plugin::info(sprintf(
                'Submission from %s stored as spam for review: %s (score: %s)',
                $submission->fromEmail,
                $verdict->reason,
                $verdict->score ?? 'n/a'
            ));

            return $this->spamResponse($submission, $settings);
        }

        try {
            $plugin->mail->sendNotification($submission, $notificationTemplate, $toEmail);
        } catch (\Throwable $e) {
            Plugin::error('Failed to send the contact form notification email', $e);
            $submission->sendError = $e->getMessage();
            $this->resaveSubmission($submission);

            return $this->asModelFailure(
                $submission,
                Craft::t('secure-forms', 'Your message could not be sent at the moment. Please try again later.'),
                'submission'
            );
        }

        if ($settings->enableConfirmationEmail || $confirmationTemplate) {
            try {
                $plugin->mail->sendConfirmation($submission, $confirmationTemplate, $confirmationSubject);
            } catch (\Throwable $e) {
                // The notification was delivered, so the visitor's message
                // arrived — log the confirmation failure but report success
                Plugin::error('Failed to send the contact form confirmation email', $e);
                $submission->sendError = 'confirmation: ' . $e->getMessage();
                $this->resaveSubmission($submission);
            }
        }

        return $this->asModelSuccess(
            $submission,
            Craft::t('secure-forms', 'Your message has been sent.'),
            'submission'
        );
    }

    private function buildSubmission(): Submission
    {
        $request = Craft::$app->getRequest();

        $submission = new Submission();
        $submission->fromName = trim((string)$request->getBodyParam('fromName')) ?: null;
        $submission->fromEmail = trim((string)$request->getBodyParam('fromEmail')) ?: null;
        $submission->subject = trim((string)$request->getBodyParam('subject')) ?: null;
        $submission->form = trim((string)$request->getBodyParam('formName')) ?: 'contact';

        $message = $request->getBodyParam('message');

        if (is_string($message)) {
            $message = ['body' => $message];
        }

        if (!is_array($message)) {
            $message = [];
        }

        // Allow the form name to be passed as message[formName] (legacy convention)
        if (isset($message['formName'])) {
            $submission->form = trim((string)$message['formName']) ?: $submission->form;
            unset($message['formName']);
        }

        $submission->setMessage(array_map(
            fn($value) => is_string($value) ? trim($value) : $value,
            $message
        ));

        return $submission;
    }

    private function validateSubmission(Submission $submission): bool
    {
        if (!$submission->fromName) {
            $submission->addError('fromName', Craft::t('secure-forms', 'A name is required.'));
        }

        if (!$submission->fromEmail || !(new EmailValidator())->validate($submission->fromEmail)) {
            $submission->addError('fromEmail', Craft::t('secure-forms', 'A valid email address is required.'));
        }

        $hasContent = array_filter(
            $submission->getMessage(),
            fn($value) => is_array($value) ? $value !== [] : trim((string)$value) !== ''
        );

        if ($hasContent === [] && !$submission->subject) {
            $submission->addError('message', Craft::t('secure-forms', 'A message is required.'));
        }

        return !$submission->hasErrors();
    }

    /**
     * The response for a spam-classified submission, per the spamAction
     * setting: silent fake success by default (don't teach bots what gets
     * detected), or an explicit error.
     */
    private function spamResponse(Submission $submission, Settings $settings): ?Response
    {
        if ($settings->spamAction === Settings::SPAM_ACTION_ERROR) {
            $submission->addError('spam', Craft::t('secure-forms', 'Your submission was flagged as spam.'));

            return $this->asModelFailure(
                $submission,
                Craft::t('secure-forms', 'Your submission was flagged as spam.'),
                'submission'
            );
        }

        return $this->asModelSuccess(
            $submission,
            Craft::t('secure-forms', 'Your message has been sent.'),
            'submission'
        );
    }

    /**
     * Read a hashed body param; tampering fails Craft's hash validation.
     */
    private function validatedParam(string $name): ?string
    {
        $value = Craft::$app->getRequest()->getValidatedBodyParam($name);

        return $value !== null && $value !== '' ? (string)$value : null;
    }

    /**
     * Persist the submission. Storage failures are logged as real errors but
     * never block the email from being sent.
     */
    private function saveSubmission(Submission $submission, Settings $settings): void
    {
        if (!$settings->saveSubmissions || ($submission->isSpam && !$settings->saveSpamSubmissions)) {
            return;
        }

        try {
            if (!Craft::$app->getElements()->saveElement($submission, false)) {
                Plugin::error('Failed to save contact form submission: ' . Json::encode($submission->getErrors()));
            }
        } catch (\Throwable $e) {
            Plugin::error('Failed to save contact form submission', $e);
        }
    }

    /**
     * Update an already-saved submission (e.g. to record a send error).
     */
    private function resaveSubmission(Submission $submission): void
    {
        if (!$submission->id) {
            return;
        }

        try {
            Craft::$app->getElements()->saveElement($submission, false);
        } catch (\Throwable $e) {
            Plugin::error('Failed to update contact form submission', $e);
        }
    }
}
