<?php

namespace recranet\secureforms\controllers;

use Craft;
use craft\web\Controller;
use recranet\secureforms\elements\Submission;
use recranet\secureforms\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Control panel detail view for stored submissions.
 */
class SubmissionsController extends Controller
{
    public function beforeAction($action): bool
    {
        $this->requirePermission('accessPlugin-secure-forms');

        return parent::beforeAction($action);
    }

    public function actionView(int $submissionId): Response
    {
        $submission = Submission::find()->id($submissionId)->status(null)->one();

        if ($submission === null) {
            throw new NotFoundHttpException('Submission not found');
        }

        return $this->renderTemplate('secure-forms/submissions/_view.twig', [
            'submission' => $submission,
        ]);
    }

    /**
     * Manually (re)send the notification email for a stored submission.
     *
     * Covers both undelivered cases: a failed send is retried, and a
     * submission classified as spam is delivered after human review —
     * sending marks it as not spam. Re-scoring is impossible by design
     * (captcha tokens are single-use and expire within minutes), so a human
     * decision replaces the score.
     */
    public function actionResend(): Response
    {
        $this->requirePostRequest();

        $submissionId = Craft::$app->getRequest()->getRequiredBodyParam('submissionId');
        $submission = Submission::find()->id($submissionId)->status(null)->one();

        if ($submission === null) {
            throw new NotFoundHttpException('Submission not found');
        }

        try {
            Plugin::getInstance()->mail->sendNotification($submission);
        } catch (\Throwable $e) {
            Plugin::error('Manual resend of submission notification failed', $e);
            $submission->sendError = $e->getMessage();
            Craft::$app->getElements()->saveElement($submission, false);
            Craft::$app->getSession()->setError(Craft::t('secure-forms', 'The notification email could not be sent: {error}', [
                'error' => $e->getMessage(),
            ]));

            return $this->redirectToPostedUrl();
        }

        // Delivered: no longer failed, and no longer spam (human decision).
        // The spam score and reason are kept for the record.
        $submission->isSpam = false;
        $submission->sendError = null;
        Craft::$app->getElements()->saveElement($submission, false);
        Craft::$app->getSession()->setNotice(Craft::t('secure-forms', 'Notification email sent.'));

        return $this->redirectToPostedUrl();
    }
}
