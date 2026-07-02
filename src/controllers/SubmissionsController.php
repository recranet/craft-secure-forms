<?php

namespace recranet\secureforms\controllers;

use craft\web\Controller;
use recranet\secureforms\elements\Submission;
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
}
