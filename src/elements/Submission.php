<?php

namespace recranet\secureforms\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use craft\elements\exporters\Raw;
use craft\enums\Color;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\Site;
use recranet\secureforms\elements\db\SubmissionQuery;
use recranet\secureforms\elements\exporters\ExpandedSubmissions;

/**
 * A stored contact form submission.
 *
 * Dynamic form fields live as JSON in the `message` column; the schema stays
 * fixed. Spam classification (isSpam, spamScore, spamReason) and send
 * failures (sendError) are persisted so every submission is inspectable in
 * the control panel and nothing is silently dropped.
 */
class Submission extends Element
{
    public const TABLE = '{{%secureforms_submissions}}';

    public const STATUS_SENT = 'sent';
    public const STATUS_SPAM = 'spam';
    public const STATUS_FAILED = 'failed';

    public ?string $form = null;
    public ?string $subject = null;
    public ?string $fromName = null;
    public ?string $fromEmail = null;
    public bool $isSpam = false;
    public ?float $spamScore = null;
    public ?string $spamReason = null;
    public ?string $sendError = null;

    /** @var array Decoded dynamic form fields */
    private array $_message = [];

    public static function displayName(): string
    {
        return Craft::t('secure-forms', 'Submission');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('secure-forms', 'submission');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('secure-forms', 'Submissions');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('secure-forms', 'submissions');
    }

    public static function refHandle(): ?string
    {
        return 'secureFormsSubmission';
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_SENT => ['label' => Craft::t('secure-forms', 'Sent'), 'color' => Color::Green],
            self::STATUS_SPAM => ['label' => Craft::t('secure-forms', 'Spam'), 'color' => Color::Red],
            self::STATUS_FAILED => ['label' => Craft::t('secure-forms', 'Failed'), 'color' => Color::Orange],
        ];
    }

    public function getStatus(): ?string
    {
        if ($this->isSpam) {
            return self::STATUS_SPAM;
        }

        if ($this->sendError !== null && $this->sendError !== '') {
            return self::STATUS_FAILED;
        }

        return self::STATUS_SENT;
    }

    /**
     * @return SubmissionQuery
     */
    public static function find(): ElementQueryInterface
    {
        return new SubmissionQuery(static::class);
    }

    /**
     * Dynamic form fields as an associative array (decoded from JSON).
     */
    public function getMessage(): array
    {
        return $this->_message;
    }

    /**
     * Accepts a decoded array or the raw JSON string from the database row.
     */
    public function setMessage(array|string|null $message): void
    {
        if (is_string($message)) {
            $message = Json::decodeIfJson($message);
        }

        $this->_message = is_array($message) ? $message : [];
    }

    public function canView($user): bool
    {
        return $user->can('accessPlugin-secure-forms');
    }

    public function canSave($user): bool
    {
        return $user->can('accessPlugin-secure-forms');
    }

    public function canDelete($user): bool
    {
        return $user->can('accessPlugin-secure-forms');
    }

    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl("secure-forms/submissions/$this->id");
    }

    public function getSupportedSites(): array
    {
        // Submissions are not localized
        return [Craft::$app->getSites()->getPrimarySite()->id];
    }

    public function afterSave(bool $isNew): void
    {
        $data = [
            'form' => $this->form,
            'subject' => $this->subject,
            'fromName' => $this->fromName,
            'fromEmail' => $this->fromEmail,
            'message' => Json::encode($this->_message),
            'isSpam' => $this->isSpam,
            'spamScore' => $this->spamScore,
            'spamReason' => $this->spamReason,
            'sendError' => $this->sendError,
        ];

        if ($isNew) {
            Db::insert(self::TABLE, ['id' => $this->id] + $data);
        } else {
            Db::update(self::TABLE, $data, ['id' => $this->id]);
        }

        parent::afterSave($isNew);
    }

    protected static function defineSources(string $context): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('secure-forms', 'All submissions'),
                'criteria' => [],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'key' => 'inbox',
                'label' => Craft::t('secure-forms', 'Inbox (sent)'),
                'criteria' => ['status' => self::STATUS_SENT],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'key' => 'spam',
                'label' => Craft::t('secure-forms', 'Spam'),
                'criteria' => ['status' => self::STATUS_SPAM],
                'defaultSort' => ['dateCreated', 'desc'],
            ],
            [
                'key' => 'failed',
                'label' => Craft::t('secure-forms', 'Failed'),
                'criteria' => ['status' => self::STATUS_FAILED],
                'defaultSort' => ['dateCreated', 'desc'],
                // Same alert as the CP nav item: failed sends need attention
                'badgeCount' => \recranet\secureforms\Plugin::getInstance()->getFailedSubmissionCount(),
            ],
        ];

        // One source per distinct form name
        $forms = (new \craft\db\Query())
            ->select(['form'])
            ->distinct()
            ->from(self::TABLE)
            ->where(['not', ['form' => null]])
            ->orderBy(['form' => SORT_ASC])
            ->column();

        if ($forms !== []) {
            $sources[] = ['heading' => Craft::t('secure-forms', 'Forms')];
        }

        foreach ($forms as $form) {
            $sources[] = [
                'key' => 'form:' . $form,
                'label' => $form,
                'criteria' => ['form' => $form],
                'defaultSort' => ['dateCreated', 'desc'],
            ];
        }

        return $sources;
    }

    protected static function defineActions(string $source): array
    {
        return [
            Craft::$app->getElements()->createAction([
                'type' => Delete::class,
                'confirmationMessage' => Craft::t('secure-forms', 'Are you sure you want to delete the selected submissions?'),
                'successMessage' => Craft::t('secure-forms', 'Submissions deleted.'),
            ]),
        ];
    }

    protected static function defineExporters(string $source): array
    {
        return [
            ExpandedSubmissions::class,
            Raw::class,
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'fromName' => ['label' => Craft::t('secure-forms', 'Name')],
            'fromEmail' => ['label' => Craft::t('secure-forms', 'Email')],
            'subject' => ['label' => Craft::t('secure-forms', 'Subject')],
            'form' => ['label' => Craft::t('secure-forms', 'Form')],
            'messageSummary' => ['label' => Craft::t('secure-forms', 'Message')],
            'spamScore' => ['label' => Craft::t('secure-forms', 'Spam score')],
            'spamReason' => ['label' => Craft::t('secure-forms', 'Spam reason')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['fromName', 'fromEmail', 'messageSummary', 'spamScore', 'dateCreated'];
    }

    protected static function defineSortOptions(): array
    {
        return [
            'dateCreated' => Craft::t('app', 'Date Created'),
            'fromName' => Craft::t('secure-forms', 'Name'),
            'fromEmail' => Craft::t('secure-forms', 'Email'),
            'spamScore' => Craft::t('secure-forms', 'Spam score'),
        ];
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['fromName', 'fromEmail', 'subject', 'messageText'];
    }

    /**
     * Flattened message values for the search index.
     */
    public function getMessageText(): string
    {
        return implode(' ', array_map(
            fn($value) => is_scalar($value) ? (string)$value : Json::encode($value),
            $this->_message
        ));
    }

    /**
     * Short plain-text summary of the message fields for the index table.
     */
    public function getMessageSummary(): string
    {
        return StringHelper::safeTruncate($this->getMessageText(), 80, '…');
    }

    protected function attributeHtml(string $attribute): string
    {
        return match ($attribute) {
            'messageSummary' => Html::encode($this->getMessageSummary()),
            'spamScore' => $this->spamScore !== null
                ? Craft::$app->getFormatter()->asDecimal($this->spamScore, 2)
                : '—',
            'spamReason' => $this->spamReason ? Html::encode($this->spamReason) : '—',
            default => parent::attributeHtml($attribute),
        };
    }

    protected function cpEditUrl(): ?string
    {
        return $this->getCpEditUrl();
    }
}
