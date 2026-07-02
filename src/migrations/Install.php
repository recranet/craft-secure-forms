<?php

namespace recranet\secureforms\migrations;

use craft\db\Migration;
use recranet\secureforms\elements\Submission;

/**
 * Install migration — creates the submissions table.
 *
 * Dynamic form fields are stored as JSON in the `message` column (the schema
 * stays fixed); spam classification is persisted per submission (isSpam,
 * spamScore, spamReason) so spam is inspectable in the control panel, and
 * send failures are recorded in `sendError` so no submission is ever lost.
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists(Submission::TABLE)) {
            $this->createTable(Submission::TABLE, [
                'id' => $this->integer()->notNull(),
                'form' => $this->string(),
                'subject' => $this->string(),
                'fromName' => $this->string(),
                'fromEmail' => $this->string(),
                'message' => $this->text(),
                'isSpam' => $this->boolean()->notNull()->defaultValue(false),
                'spamScore' => $this->decimal(4, 3),
                'spamReason' => $this->string(),
                'sendError' => $this->text(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY(id)',
            ]);

            $this->createIndex(null, Submission::TABLE, ['form']);
            $this->createIndex(null, Submission::TABLE, ['isSpam']);
            $this->addForeignKey(null, Submission::TABLE, ['id'], '{{%elements}}', ['id'], 'CASCADE');
        }

        return true;
    }

    public function safeDown(): bool
    {
        // Remove the element rows first; the FK cascade removes our table rows
        $this->delete('{{%elements}}', ['type' => Submission::class]);
        $this->dropTableIfExists(Submission::TABLE);

        return true;
    }
}
