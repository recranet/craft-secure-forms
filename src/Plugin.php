<?php

namespace recranet\secureforms;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use recranet\secureforms\models\Settings;
use recranet\secureforms\services\MailService;
use recranet\secureforms\services\SpamService;
use recranet\secureforms\utilities\EmailTestUtility;
use recranet\secureforms\variables\SecureFormsVariable;
use yii\base\Event;

/**
 * Secure Forms — contact forms with spam protection, stored submissions and
 * proper error reporting.
 *
 * Design principles:
 * - Spam is not an error: real spam is stored with its score and silently
 *   accepted, never logged as an error.
 * - Misconfiguration (missing/invalid captcha keys, unreachable verification
 *   API, SMTP failures) is a real error: shown to the user, logged, and
 *   forwarded to Sentry when the SDK is installed.
 * - Submissions are always persisted before any email is attempted, so no
 *   message is ever lost to a transport failure.
 *
 * @property-read SpamService $spam
 * @property-read MailService $mail
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    /** Log category used for all plugin log messages */
    public const LOG_CATEGORY = 'secure-forms';

    public static function config(): array
    {
        return [
            'components' => [
                'spam' => SpamService::class,
                'mail' => MailService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->attachEventHandlers();
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = Craft::t('secure-forms', 'Secure Forms');

        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('secure-forms/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Control panel routes: element index + submission detail view
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['secure-forms'] = ['template' => 'secure-forms/submissions/index.twig'];
                $event->rules['secure-forms/submissions/<submissionId:\d+>'] = 'secure-forms/submissions/view';
            }
        );

        // SMTP / email test utility
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = EmailTestUtility::class;
            }
        );

        // craft.secureForms template variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('secureForms', SecureFormsVariable::class);
            }
        );
    }

    /**
     * Log a real (non-spam) error to Craft's logs, and forward it to Sentry
     * when the Sentry SDK is installed and initialized.
     */
    public static function error(string $message, ?\Throwable $exception = null): void
    {
        $logMessage = $exception ? sprintf('%s: %s', $message, $exception->getMessage()) : $message;
        Craft::error($logMessage, self::LOG_CATEGORY);

        if (function_exists('Sentry\captureException') && \Sentry\SentrySdk::getCurrentHub()->getClient() !== null) {
            if ($exception !== null) {
                \Sentry\captureException($exception);
            } else {
                \Sentry\captureMessage($message, \Sentry\Severity::error());
            }
        }
    }

    /**
     * Log an informational event (spam catches, submission lifecycle). Never
     * forwarded to Sentry — spam is not an error.
     */
    public static function info(string $message): void
    {
        Craft::info($message, self::LOG_CATEGORY);
    }
}
