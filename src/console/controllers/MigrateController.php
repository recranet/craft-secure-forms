<?php

namespace recranet\secureforms\console\controllers;

use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use recranet\secureforms\elements\Submission;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Migrates stored submissions from replaced contact form plugins.
 */
class MigrateController extends Controller
{
    public $defaultAction = 'contact-form-extensions';

    /**
     * Copies submissions from the craft-contact-form-extensions table
     * ({{%contactform_submissions}}) into Secure Forms.
     *
     * Run this BEFORE uninstalling the old plugin — its uninstall drops the
     * source table. Safe to re-run: already-migrated submissions (matched on
     * form, sender email and creation date) are skipped.
     *
     * Migrated submissions get the `sent` status; the old plugin never
     * stored spam or send failures.
     */
    public function actionContactFormExtensions(): int
    {
        $oldTable = '{{%contactform_submissions}}';

        if (!Craft::$app->getDb()->tableExists($oldTable)) {
            $this->stderr("Source table {$oldTable} does not exist — nothing to migrate. (Was contact-form-extensions already uninstalled? Its uninstall drops the table, so restore it from a backup first.)\n", Console::FG_YELLOW);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $query = (new Query())
            ->from($oldTable)
            ->orderBy(['id' => SORT_ASC]);

        $total = $query->count();
        $this->stdout("Found {$total} submissions in {$oldTable}\n");

        $migrated = $skipped = $failed = 0;
        $elements = Craft::$app->getElements();

        foreach (Db::each($query) as $row) {
            // Idempotency: skip rows that were already migrated. Compared on
            // the raw column values (both tables store UTC), avoiding any
            // timezone interpretation.
            $exists = (new Query())
                ->from(Submission::TABLE)
                ->where([
                    'form' => $row['form'],
                    'fromEmail' => $row['fromEmail'],
                    'dateCreated' => $row['dateCreated'],
                ])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // The old plugin stored the dynamic fields JSON-encoded; fall
            // back to a body field if a row holds a plain string
            $message = Json::decodeIfJson((string)$row['message']);

            if (!is_array($message)) {
                $message = ['body' => (string)$row['message']];
            }

            $submission = new Submission();
            $submission->form = $row['form'];
            $submission->subject = $row['subject'];
            $submission->fromName = $row['fromName'];
            $submission->fromEmail = $row['fromEmail'];
            $submission->setMessage($message);
            $submission->dateCreated = DateTimeHelper::toDateTime($row['dateCreated']) ?: null;
            $submission->dateUpdated = DateTimeHelper::toDateTime($row['dateUpdated']) ?: null;

            try {
                if ($elements->saveElement($submission, false)) {
                    $migrated++;
                } else {
                    $failed++;
                    $this->stderr("Failed to save old submission #{$row['id']}: " . Json::encode($submission->getErrors()) . "\n", Console::FG_RED);
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->stderr("Failed to save old submission #{$row['id']}: {$e->getMessage()}\n", Console::FG_RED);
            }
        }

        $this->stdout("Migrated: {$migrated}, skipped (already migrated): {$skipped}, failed: {$failed}\n", $failed ? Console::FG_YELLOW : Console::FG_GREEN);

        if ($migrated > 0) {
            $this->stdout("The source table was left untouched — uninstalling contact-form-extensions will drop it.\n");
        }

        return $failed ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
