<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Controller;

use Akeneo\UserManagement\Bundle\Context\UserContext;
use Oro\Bundle\SecurityBundle\SecurityFacade;
use Supertext\AkeneoTranslationBundle\Api\CurlTransport;
use Supertext\AkeneoTranslationBundle\Api\SupertextClient;
use Supertext\AkeneoTranslationBundle\Api\SupertextException;
use Supertext\AkeneoTranslationBundle\Settings\Settings;
use Supertext\AkeneoTranslationBundle\Translation\EntityTranslator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Internal API behind System → Supertext. Needs Akeneo's "System configuration" permission
 * (oro_config_system), like System → Configuration. The API key is never sent back.
 */
final class SettingsController
{
    public const ACL = 'oro_config_system';

    public function __construct(
        private readonly Settings $settings,
        private readonly EntityTranslator $translator,
        private readonly SecurityFacade $securityFacade,
        private readonly UserContext $userContext,
    ) {
    }

    public function getAction(): JsonResponse
    {
        if (!$this->securityFacade->isGranted(self::ACL)) {
            return new JsonResponse(['message' => 'You are not allowed to change the system configuration.'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->payload());
    }

    public function saveAction(Request $request): JsonResponse
    {
        if (!$this->securityFacade->isGranted(self::ACL)) {
            return new JsonResponse(['message' => 'You are not allowed to change the system configuration.'], Response::HTTP_FORBIDDEN);
        }

        $input  = json_decode($request->getContent(), true);
        $errors = $this->settings->update(\is_array($input) ? $input : []);

        if ($errors !== []) {
            return new JsonResponse(['message' => implode(' ', $errors), 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($this->payload());
    }

    /** Cost-free check of the key (the one typed in the form, else the saved one). */
    public function testAction(Request $request): JsonResponse
    {
        if (!$this->securityFacade->isGranted(self::ACL)) {
            return new JsonResponse(['message' => 'You are not allowed to change the system configuration.'], Response::HTTP_FORBIDDEN);
        }

        $input = json_decode($request->getContent(), true);
        $typed = \is_array($input) ? SupertextClient::normalizeKey((string) ($input['api_key'] ?? '')) : '';
        $key   = $this->settings->apiKeySource() !== 'environment' && $typed !== '' ? $typed : $this->settings->apiKey();
        $url   = $this->settings->baseUrl();

        if (\is_array($input) && !$this->settings->baseUrlFromEnvironment() && \in_array($input['environment'] ?? null, Settings::ENVIRONMENTS, true)) {
            $url = SupertextClient::baseUrlFor((string) $input['environment'], $input['environment'] === 'custom' ? (string) ($input['api_url'] ?? '') : '');
        }

        if ($key === '') {
            return new JsonResponse(['ok' => false, 'message' => 'No Supertext API key is configured.'], Response::HTTP_OK);
        }

        try {
            (new SupertextClient($key, $url, new CurlTransport(30), 30))->validateApiKey();
        } catch (SupertextException $e) {
            return new JsonResponse(['ok' => false, 'message' => $e->getMessage()], Response::HTTP_OK);
        }

        return new JsonResponse(['ok' => true, 'message' => sprintf('Connected to %s.', $url)]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $key       = $this->settings->apiKey();
        $uiLocale  = (string) $this->userContext->getUiLocaleCode();
        $languages = [];

        foreach ($this->translator->activatedLocales() as $locale) {
            $stored      = $this->settings->languages()[$locale] ?? [];
            $languages[] = [
                'locale'       => $locale,
                'label'        => (string) \Locale::getDisplayName($locale, $uiLocale !== '' ? $uiLocale : 'en_US'),
                'code'         => (string) ($stored['code'] ?? ''),
                'default_code' => Settings::defaultLanguageCode($locale),
                'politeness'   => $this->settings->politeness($locale),
            ];
        }

        $version = Settings::version();

        return [
            'version'                  => $version,
            'release_url'              => Settings::releaseUrl($version),
            'api_key_source'           => $this->settings->apiKeySource(),
            'api_key_hint'             => $key !== '' ? '…' . substr($key, -4) : '',
            'environment'              => $this->settings->environment(),
            'api_url'                  => $this->settings->customUrl(),
            'api_url_from_environment' => $this->settings->baseUrlFromEnvironment(),
            'effective_url'            => $this->settings->baseUrl(),
            'timeout'                  => $this->settings->timeout(),
            'languages'                => $languages,
            'links'                    => ['signup' => Settings::SIGNUP_URL, 'api_key' => Settings::API_KEY_URL],
        ];
    }
}
