<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Translation;

use Akeneo\Channel\Infrastructure\Component\Model\ChannelInterface;
use Akeneo\Channel\Infrastructure\Component\Repository\ChannelRepositoryInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithFamilyVariantInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithValuesInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Supertext\AkeneoTranslationBundle\Api\HtmlDocument;
use Supertext\AkeneoTranslationBundle\Api\SupertextException;
use Supertext\AkeneoTranslationBundle\Settings\Settings;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Translates the text values of one product or product model from a source locale into
 * target locales: one Supertext document per target language, then Akeneo's own updater,
 * validator and saver (so versioning, completeness and the search index stay right).
 *
 * Field rules (keep docs/DEVELOPER.md → Field rules and docs/USER_GUIDE.md in sync):
 * - Attribute types pim_catalog_text and pim_catalog_textarea, localizable ones only.
 *   Textareas with the rich text editor go as HTML, everything else as plain text.
 * - Only the values that belong to this level: a variant product's inherited values are
 *   translated on its product model.
 * - Every channel's value of a scopable attribute, into the locales that channel has.
 */
final class EntityTranslator
{
    public const TEXT_TYPES = ['pim_catalog_text', 'pim_catalog_textarea'];

    /** @var array<string, AttributeInterface|null> */
    private array $attributes = [];

    /** @var array<string, list<string>>|null */
    private ?array $channelLocales = null;

    public function __construct(
        private readonly IdentifiableObjectRepositoryInterface $attributeRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly ObjectUpdaterInterface $productUpdater,
        private readonly ObjectUpdaterInterface $productModelUpdater,
        private readonly ValidatorInterface $validator,
        private readonly SaverInterface $productSaver,
        private readonly SaverInterface $productModelSaver,
        private readonly Settings $settings,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /** @return array<string, list<string>> channel code => locale codes */
    public function channelLocales(): array
    {
        if ($this->channelLocales === null) {
            $this->channelLocales = [];

            foreach ($this->channelRepository->findAll() as $channel) {
                if ($channel instanceof ChannelInterface) {
                    $this->channelLocales[$channel->getCode()] = array_values($channel->getLocaleCodes());
                }
            }
        }

        return $this->channelLocales;
    }

    /** @return list<string> the activated locales (the locales of all channels) */
    public function activatedLocales(): array
    {
        $locales = array_values(array_unique(array_merge([], ...array_values($this->channelLocales()))));
        sort($locales);

        return $locales;
    }

    /** @return list<TextUnit> the translatable values of $entity in $source */
    public function sourceUnits(EntityWithValuesInterface $entity, string $source): array
    {
        $units = [];

        foreach ($this->ownValues($entity) as $value) {
            if ($value->getLocaleCode() !== $source || !Planner::hasText($value->getData())) {
                continue;
            }

            $attribute = $this->attribute($value->getAttributeCode());

            if ($attribute === null || !\in_array($attribute->getType(), self::TEXT_TYPES, true) || !$attribute->isLocalizable()) {
                continue;
            }

            $units[] = new TextUnit(
                $attribute->getCode(),
                $value->getScopeCode(),
                (string) $value->getData(),
                $attribute->getType() === 'pim_catalog_textarea' && (bool) $attribute->isWysiwygEnabled(),
                $attribute->isLocaleSpecific() ? array_values($attribute->getAvailableLocaleCodes()) : [],
                $attribute->getMaxCharacters() !== null ? (int) $attribute->getMaxCharacters() : null,
            );
        }

        usort($units, static fn (TextUnit $a, TextUnit $b): int => strcmp($a->key(), $b->key()));

        return $units;
    }

    /**
     * What a translation would do, without calling Supertext.
     *
     * @param list<string> $targets
     *
     * @return array{units: int, targets: array<string, array{translate: int, existing: int, unavailable: int}>}
     */
    public function preview(EntityWithValuesInterface $entity, string $source, array $targets): array
    {
        $units  = $this->sourceUnits($entity, $source);
        $result = ['units' => \count($units), 'targets' => []];

        foreach ($targets as $target) {
            $plan                       = Planner::plan($units, $target, $this->channelLocales(), $this->hasTextCallback($entity), false);
            $result['targets'][$target] = ['translate' => \count($plan['translate']), 'existing' => $plan['existing'], 'unavailable' => $plan['unavailable']];
        }

        return $result;
    }

    /**
     * @param list<string> $targets
     *
     * @return array<string, array{status: string, translated: int, existing: int, message: string, key: string, params: array<string, string|int>, detail: string}>
     *         status: "translated", "nothing" (no text to translate or all kept), "error";
     *         message: English; key/params/detail: for the UI (`supertext_translation.error.<key>`)
     */
    public function translate(EntityWithValuesInterface $entity, string $source, array $targets, bool $overwrite): array
    {
        $units   = $this->sourceUnits($entity, $source);
        $client  = $this->settings->client();
        $results = [];
        $changed = false;
        $updater = $entity instanceof ProductModelInterface ? $this->productModelUpdater : $this->productUpdater;

        if (!$client->hasApiKey()) {
            throw new SupertextException('No Supertext API key is configured.', key: 'no_api_key');
        }

        $baseline = $this->violationKeys($entity);

        foreach ($targets as $target) {
            if ($target === $source) {
                continue;
            }

            $plan = Planner::plan($units, $target, $this->channelLocales(), $this->hasTextCallback($entity), $overwrite);

            if ($plan['translate'] === []) {
                $results[$target] = ['status' => 'nothing', 'translated' => 0, 'existing' => $plan['existing'], 'message' => '', 'key' => '', 'params' => [], 'detail' => ''];

                continue;
            }

            try {
                $translations = $this->translateUnits($client, $plan['translate'], $source, $target);
            } catch (SupertextException $e) {
                $results[$target] = ['status' => 'error', 'translated' => 0, 'existing' => $plan['existing'], 'key' => $e->key, 'params' => $e->params, 'detail' => $e->detail, 'message' => $e->getMessage()];

                continue;
            }

            $previous = [];
            $values   = [];
            $tooLong  = [];

            foreach ($plan['translate'] as $index => $unit) {
                $text = $translations[$index] ?? '';

                if (!Planner::hasText($text)) {
                    continue;
                }

                if ($unit->maxCharacters !== null && !$unit->html && mb_strlen($text) > $unit->maxCharacters) {
                    $tooLong[] = $unit->attribute;

                    continue;
                }

                $current                                       = $this->ownValues($entity)->getByCodes($unit->attribute, $unit->scope, $target);
                $previous[$unit->attribute][]                  = ['locale' => $target, 'scope' => $unit->scope, 'data' => $current?->getData()];
                $values[$unit->attribute][]                    = ['locale' => $target, 'scope' => $unit->scope, 'data' => $text];
            }

            if ($values === []) {
                $results[$target] = [
                    'status'     => 'error',
                    'translated' => 0,
                    'existing'   => $plan['existing'],
                    'message'    => $tooLong !== [] ? sprintf('The translation is longer than the attribute allows: %s.', implode(', ', $tooLong)) : 'Supertext returned no text.',
                    'key'        => $tooLong !== [] ? 'too_long' : 'no_text',
                    'params'     => $tooLong !== [] ? ['attributes' => implode(', ', $tooLong)] : [],
                    'detail'     => '',
                ];

                continue;
            }

            try {
                $updater->update($entity, ['values' => $values]);
                $violations = array_diff_key($this->violationKeys($entity), $baseline);
            } catch (\Throwable $e) {
                $this->logger->warning('Supertext: could not apply the translation', ['locale' => $target, 'exception' => $e]);
                $violations = ['update' => $e->getMessage()];
            }

            if ($violations !== []) {
                // Put the previous values back so the other languages can still be saved.
                try {
                    $updater->update($entity, ['values' => $previous]);
                } catch (\Throwable) {
                }

                $detail           = implode(' ', array_unique(array_values($violations)));
                $results[$target] = ['status' => 'error', 'translated' => 0, 'existing' => $plan['existing'], 'message' => 'Akeneo did not accept the translation: ' . $detail, 'key' => 'rejected', 'params' => [], 'detail' => $detail];

                continue;
            }

            $changed  = true;
            $count    = array_sum(array_map('count', $values));
            $message  = $tooLong !== [] ? sprintf('Not translated because the translation is too long: %s.', implode(', ', $tooLong)) : '';
            $results[$target] = [
                'status'     => 'translated',
                'translated' => $count,
                'existing'   => $plan['existing'],
                'message'    => $message,
                'key'        => $tooLong !== [] ? 'partly_too_long' : '',
                'params'     => $tooLong !== [] ? ['attributes' => implode(', ', $tooLong)] : [],
                'detail'     => '',
            ];
        }

        if ($changed) {
            ($entity instanceof ProductModelInterface ? $this->productModelSaver : $this->productSaver)->save($entity);
        }

        return $results;
    }

    /**
     * @param list<TextUnit> $units
     *
     * @return array<int, string> unit index => translated text
     */
    private function translateUnits(\Supertext\AkeneoTranslationBundle\Api\SupertextClient $client, array $units, string $source, string $target): array
    {
        $segments = [];
        $isHtml   = [];

        foreach ($units as $index => $unit) {
            $segments[$index] = ['text' => $unit->text, 'html' => $unit->html];
            $isHtml[$index]   = $unit->html;
        }

        $translations = [];

        foreach (HtmlDocument::chunks($segments) as $chunk) {
            $html = $client->translateDocument(
                HtmlDocument::build($chunk),
                $this->settings->languageCode($target),
                $this->settings->languageCode($source),
                $this->settings->politeness($target) ?: 'default',
            );
            $translations += HtmlDocument::parse($html, $isHtml);
        }

        return $translations;
    }

    /** @return callable(string, ?string, string): bool */
    private function hasTextCallback(EntityWithValuesInterface $entity): callable
    {
        $values = $this->ownValues($entity);

        return static fn (string $attribute, ?string $scope, string $locale): bool => Planner::hasText($values->getByCodes($attribute, $scope, $locale)?->getData());
    }

    /** @return \Akeneo\Pim\Enrichment\Component\Product\Model\WriteValueCollection */
    private function ownValues(EntityWithValuesInterface $entity): iterable
    {
        return $entity instanceof EntityWithFamilyVariantInterface ? $entity->getValuesForVariation() : $entity->getValues();
    }

    private function attribute(string $code): ?AttributeInterface
    {
        if (!\array_key_exists($code, $this->attributes)) {
            $attribute               = $this->attributeRepository->findOneByIdentifier($code);
            $this->attributes[$code] = $attribute instanceof AttributeInterface ? $attribute : null;
        }

        return $this->attributes[$code];
    }

    /** @return array<string, string> violation key => message */
    private function violationKeys(EntityWithValuesInterface $entity): array
    {
        $keys = [];

        /** @var ConstraintViolationInterface $violation */
        foreach ($this->validator->validate($entity) as $violation) {
            $keys[$violation->getPropertyPath() . '|' . $violation->getMessage()] = (string) $violation->getMessage();
        }

        return $keys;
    }
}
