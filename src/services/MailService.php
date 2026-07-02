<?php

namespace recranet\secureforms\services;

use Craft;
use craft\helpers\App;
use craft\web\View;
use recranet\secureforms\elements\Submission;
use recranet\secureforms\errors\MailError;
use recranet\secureforms\Plugin;
use yii\base\Component;

/**
 * Renders and sends the notification and confirmation emails.
 *
 * Craft's mailer swallows transport exceptions and returns false, so a false
 * return is treated as a hard failure here. The Secure Forms email test utility
 * exposes the underlying SMTP error detail.
 */
class MailService extends Component
{
    /**
     * Send the notification email to the configured recipient(s).
     *
     * @param string|null $templateOverride Site template path from a hashed form field
     * @throws MailError|\Throwable
     */
    public function sendNotification(Submission $submission, ?string $templateOverride = null): void
    {
        $settings = Plugin::getInstance()->getSettings();
        $toEmails = $settings->getToEmails();

        if ($toEmails === []) {
            throw new MailError('No notification recipient configured — set the "To email" plugin setting or the system email address');
        }

        $subject = trim(implode(' - ', array_filter([
            $settings->prependSubject,
            $submission->subject,
        ])));

        if ($subject === '') {
            $subject = Craft::t('secure-forms', 'New contact form submission');
        }

        $html = $this->renderBody(
            $templateOverride ?: $settings->notificationTemplate,
            'secure-forms/_emails/notification.twig',
            $submission
        );

        $message = Craft::$app->getMailer()
            ->compose()
            ->setTo($toEmails)
            ->setSubject($subject)
            ->setHtmlBody($html)
            ->setTextBody($this->toText($html));

        if ($submission->fromEmail) {
            $message->setReplyTo([$submission->fromEmail => $submission->fromName ?: $submission->fromEmail]);
        }

        // Keep the system from-address (required by most SMTP providers) but
        // optionally prefix the sender name so submissions stand out
        if ($settings->prependSender) {
            $fromEmail = App::parseEnv(App::mailSettings()->fromEmail);
            $fromName = trim(implode(' ', array_filter([$settings->prependSender, $submission->fromName])));
            $message->setFrom([$fromEmail => $fromName]);
        }

        if (!$message->send()) {
            throw new MailError('The mail transport failed to send the notification email — run the Secure Forms email test utility for details');
        }
    }

    /**
     * Send the confirmation email to the submitter.
     *
     * @param string|null $templateOverride Site template path from a hashed form field
     * @param string|null $subjectOverride Subject from a hashed form field
     * @throws MailError|\Throwable
     */
    public function sendConfirmation(Submission $submission, ?string $templateOverride = null, ?string $subjectOverride = null): void
    {
        if (!$submission->fromEmail) {
            return;
        }

        $settings = Plugin::getInstance()->getSettings();

        $subject = $subjectOverride
            ?: $settings->confirmationSubject
            ?: Craft::t('secure-forms', 'Thank you for your message');

        $html = $this->renderBody(
            $templateOverride ?: $settings->confirmationTemplate,
            'secure-forms/_emails/confirmation.twig',
            $submission
        );

        $sent = Craft::$app->getMailer()
            ->compose()
            ->setTo([$submission->fromEmail => $submission->fromName ?: $submission->fromEmail])
            ->setSubject($subject)
            ->setHtmlBody($html)
            ->setTextBody($this->toText($html))
            ->send();

        if (!$sent) {
            throw new MailError('The mail transport failed to send the confirmation email');
        }
    }

    /**
     * Render the email body from a site template, falling back to the
     * built-in plugin template.
     */
    private function renderBody(?string $siteTemplate, string $fallbackTemplate, Submission $submission): string
    {
        $view = Craft::$app->getView();
        $variables = [
            'submission' => $submission,
            'message' => $submission->getMessage(),
        ];

        if ($siteTemplate) {
            return $view->renderTemplate($siteTemplate, $variables, View::TEMPLATE_MODE_SITE);
        }

        return $view->renderTemplate($fallbackTemplate, $variables, View::TEMPLATE_MODE_CP);
    }

    /**
     * Naive plain-text version of an HTML body.
     */
    private function toText(string $html): string
    {
        $text = strip_tags(preg_replace('/<br\s*\/?>|<\/p>|<\/tr>/i', "\n", $html));

        return trim(preg_replace('/\n{3,}/', "\n\n", $text));
    }
}
