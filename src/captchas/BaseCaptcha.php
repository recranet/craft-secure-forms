<?php

namespace recranet\secureforms\captchas;

use Craft;
use GuzzleHttp\Exception\GuzzleException;
use recranet\secureforms\models\Settings;

/**
 * Shared verification plumbing for HTTP-based captcha providers.
 */
abstract class BaseCaptcha implements CaptchaInterface
{
    public function __construct(protected Settings $settings)
    {
    }

    /**
     * POST to the provider's siteverify endpoint and return the decoded JSON.
     *
     * @throws CaptchaError when the request fails or returns malformed data —
     * an unreachable verification API is an availability problem, not spam
     */
    protected function siteVerify(string $url, array $params): array
    {
        try {
            $response = Craft::createGuzzleClient(['timeout' => 5])
                ->post($url, ['form_params' => $params]);
        } catch (GuzzleException $e) {
            throw new CaptchaError(sprintf('%s verification request failed: %s', $this->getName(), $e->getMessage()), 0, $e);
        }

        $result = json_decode((string)$response->getBody(), true);

        if (!is_array($result)) {
            throw new CaptchaError(sprintf('%s verification returned a malformed response', $this->getName()));
        }

        return $result;
    }

    /**
     * Split provider error codes into config errors (our problem) and
     * visitor errors (their problem), and throw for config errors.
     *
     * @throws CaptchaError
     */
    protected function assertNoConfigError(array $errorCodes, array $configErrorCodes): void
    {
        $configErrors = array_intersect($errorCodes, $configErrorCodes);

        if ($configErrors !== []) {
            throw new CaptchaError(sprintf(
                '%s rejected the verification request due to a configuration problem: %s (check the site/secret keys and the domain allowlist)',
                $this->getName(),
                implode(', ', $configErrors)
            ));
        }
    }
}
