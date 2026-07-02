<?php

namespace recranet\secureforms\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use recranet\secureforms\elements\Submission;

/**
 * Element query for submissions.
 *
 * @method Submission[] all($db = null)
 * @method Submission|null one($db = null)
 */
class SubmissionQuery extends ElementQuery
{
    public mixed $form = null;
    public mixed $fromName = null;
    public mixed $fromEmail = null;
    public mixed $subject = null;
    public mixed $isSpam = null;

    public function form(mixed $value): static
    {
        $this->form = $value;
        return $this;
    }

    public function fromName(mixed $value): static
    {
        $this->fromName = $value;
        return $this;
    }

    public function fromEmail(mixed $value): static
    {
        $this->fromEmail = $value;
        return $this;
    }

    public function subject(mixed $value): static
    {
        $this->subject = $value;
        return $this;
    }

    public function isSpam(?bool $value): static
    {
        $this->isSpam = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('secureforms_submissions');

        $this->query->select([
            'secureforms_submissions.form',
            'secureforms_submissions.subject',
            'secureforms_submissions.fromName',
            'secureforms_submissions.fromEmail',
            'secureforms_submissions.message',
            'secureforms_submissions.isSpam',
            'secureforms_submissions.spamScore',
            'secureforms_submissions.spamReason',
            'secureforms_submissions.sendError',
        ]);

        if ($this->form !== null) {
            $this->subQuery->andWhere(Db::parseParam('secureforms_submissions.form', $this->form));
        }

        if ($this->fromName !== null) {
            $this->subQuery->andWhere(Db::parseParam('secureforms_submissions.fromName', $this->fromName));
        }

        if ($this->fromEmail !== null) {
            $this->subQuery->andWhere(Db::parseParam('secureforms_submissions.fromEmail', $this->fromEmail));
        }

        if ($this->subject !== null) {
            $this->subQuery->andWhere(Db::parseParam('secureforms_submissions.subject', $this->subject));
        }

        if ($this->isSpam !== null) {
            $this->subQuery->andWhere(['secureforms_submissions.isSpam' => $this->isSpam]);
        }

        return parent::beforePrepare();
    }

    protected function statusCondition(string $status): mixed
    {
        return match ($status) {
            Submission::STATUS_SPAM => ['secureforms_submissions.isSpam' => true],
            Submission::STATUS_FAILED => [
                'and',
                ['secureforms_submissions.isSpam' => false],
                ['not', ['secureforms_submissions.sendError' => null]],
            ],
            Submission::STATUS_SENT => [
                'secureforms_submissions.isSpam' => false,
                'secureforms_submissions.sendError' => null,
            ],
            default => parent::statusCondition($status),
        };
    }
}
