<?php

namespace recranet\secureforms\controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\MailerHelper;
use craft\mail\transportadapters\Smtp;
use craft\web\Controller;
use recranet\secureforms\Plugin;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use yii\web\Response;

/**
 * Backs the Email / SMTP test utility.
 *
 * Builds the SMTP transport directly (instead of going through Craft's
 * mailer) so connection and authentication failures surface with their full
 * error detail instead of a silent false.
 */
class UtilitiesController extends Controller
{
    public function actionTestEmail(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('utility:secure-forms-email-test');

        $recipient = trim((string)Craft::$app->getRequest()->getRequiredBodyParam('recipient'));
        $mailSettings = App::mailSettings();
        $steps = [];

        $adapter = MailerHelper::createTransportAdapter(
            $mailSettings->transportType,
            $mailSettings->transportSettings
        );

        // Step 1: raw SMTP connection + handshake (incl. auth) when SMTP is used
        if ($adapter instanceof Smtp) {
            $config = $adapter->defineTransport();
            $host = $config['host'] ?? '';
            $port = (int)($config['port'] ?? 0);

            try {
                $transport = new EsmtpTransport($host, $port);

                if (isset($config['username'])) {
                    $transport->setUsername((string)$config['username']);
                    $transport->setPassword((string)($config['password'] ?? ''));
                }

                $transport->start();
                $transport->stop();

                $steps[] = [
                    'label' => Craft::t('secure-forms', 'SMTP connection'),
                    'success' => true,
                    'detail' => Craft::t('secure-forms', 'Connected to {host}:{port} and completed the handshake.', [
                        'host' => $host,
                        'port' => $port ?: 25,
                    ]),
                ];
            } catch (\Throwable $e) {
                $steps[] = [
                    'label' => Craft::t('secure-forms', 'SMTP connection'),
                    'success' => false,
                    'detail' => sprintf('%s: %s', get_class($e), $e->getMessage()),
                ];
                Plugin::error('SMTP connection test failed', $e);
            }
        } else {
            $steps[] = [
                'label' => Craft::t('secure-forms', 'Transport'),
                'success' => true,
                'detail' => Craft::t('secure-forms', 'Mail transport is {type} — no SMTP connection to test.', [
                    'type' => $adapter::displayName(),
                ]),
            ];
        }

        // Step 2: send an actual test email through Craft's mailer
        try {
            $sent = Craft::$app->getMailer()
                ->compose()
                ->setTo($recipient)
                ->setSubject(Craft::t('secure-forms', 'Secure Forms test email'))
                ->setTextBody(Craft::t('secure-forms', 'This is a test email sent from the Secure Forms email test utility.'))
                ->send();

            $steps[] = [
                'label' => Craft::t('secure-forms', 'Test email'),
                'success' => $sent,
                'detail' => $sent
                    ? Craft::t('secure-forms', 'Test email sent to {recipient}.', ['recipient' => $recipient])
                    : Craft::t('secure-forms', 'The mailer reported a transport error — see the SMTP connection step and the Craft logs.'),
            ];
        } catch (\Throwable $e) {
            $steps[] = [
                'label' => Craft::t('secure-forms', 'Test email'),
                'success' => false,
                'detail' => sprintf('%s: %s', get_class($e), $e->getMessage()),
            ];
            Plugin::error('Test email failed', $e);
        }

        return $this->asJson(['steps' => $steps]);
    }
}
