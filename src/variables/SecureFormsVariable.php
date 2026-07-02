<?php

namespace recranet\secureforms\variables;

use craft\helpers\Html;
use craft\helpers\Template;
use recranet\secureforms\Plugin;
use Twig\Markup;

/**
 * The craft.secureForms template variable.
 */
class SecureFormsVariable
{
    /**
     * Render the configured captcha widget (or nothing when none is active).
     *
     * Usage: {{ craft.secureForms.captcha() }}
     */
    public function captcha(): Markup
    {
        $captcha = Plugin::getInstance()->spam->getCaptcha();

        return $captcha?->render() ?? Template::raw('');
    }

    /**
     * Render the honeypot field (or nothing when disabled).
     *
     * Usage: {{ craft.secureForms.honeypot() }}
     */
    public function honeypot(): Markup
    {
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->honeypotEnabled) {
            return Template::raw('');
        }

        // Visually hidden but present in the DOM: bots auto-fill it, humans never see it
        $input = Html::textInput($settings->honeypotParam, '', [
            'tabindex' => '-1',
            'autocomplete' => 'off',
        ]);

        $html = Html::tag('div', $input, [
            'aria-hidden' => 'true',
            'style' => 'position:absolute;left:-9999px;overflow:hidden;',
        ]);

        return Template::raw($html);
    }

    /**
     * The action path forms should post to.
     */
    public function submitAction(): string
    {
        return 'secure-forms/submit';
    }
}
