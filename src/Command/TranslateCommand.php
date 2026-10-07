<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Command;

use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithValuesInterface;
use Ramsey\Uuid\Uuid;
use Supertext\AkeneoTranslationBundle\Api\SupertextException;
use Supertext\AkeneoTranslationBundle\Translation\EntityTranslator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/console supertext:translate <identifier>... --from=en_US [--to=de_DE,fr_FR] [--overwrite] [--model]
 */
final class TranslateCommand extends Command
{
    protected static $defaultName = 'supertext:translate';

    public function __construct(
        private readonly object $productRepository,
        private readonly object $productModelRepository,
        private readonly EntityTranslator $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Translates products (or product models with --model) with Supertext')
            ->addArgument('identifiers', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Product identifiers or UUIDs (product model codes with --model)')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Source locale, e.g. en_US')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Target locales, comma-separated (default: all other activated locales)')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Replace target values that already have text')
            ->addOption('model', null, InputOption::VALUE_NONE, 'The identifiers are product model codes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locales = $this->translator->activatedLocales();
        $source  = (string) $input->getOption('from');

        if (!\in_array($source, $locales, true)) {
            $output->writeln(sprintf('<error>--from must be one of the activated locales: %s</error>', implode(', ', $locales)));

            return Command::INVALID;
        }

        $targets = $input->getOption('to') !== null
            ? array_values(array_filter(array_map('trim', explode(',', (string) $input->getOption('to')))))
            : array_values(array_diff($locales, [$source]));

        foreach ($targets as $target) {
            if (!\in_array($target, $locales, true)) {
                $output->writeln(sprintf('<error>%s is not an activated locale.</error>', $target));

                return Command::INVALID;
            }
        }

        $failed = false;

        foreach ($input->getArgument('identifiers') as $identifier) {
            $entity = $this->find((string) $identifier, (bool) $input->getOption('model'));

            if ($entity === null) {
                $output->writeln(sprintf('<error>%s: not found</error>', $identifier));
                $failed = true;

                continue;
            }

            try {
                $results = $this->translator->translate($entity, $source, $targets, (bool) $input->getOption('overwrite'));
            } catch (SupertextException $e) {
                $output->writeln(sprintf('<error>%s: %s</error>', $identifier, $e->getMessage()));

                return Command::FAILURE;
            }

            foreach ($results as $target => $result) {
                $line = match ($result['status']) {
                    'translated' => sprintf('%s → %s: translated (%d values)', $identifier, $target, $result['translated']),
                    'nothing'    => sprintf('%s → %s: skipped (%s)', $identifier, $target, $result['existing'] > 0 ? 'already has text; use --overwrite' : 'no text to translate'),
                    default      => sprintf('%s → %s: error: %s', $identifier, $target, $result['message']),
                };

                if ($result['status'] === 'translated' && $result['message'] !== '') {
                    $line .= ' ' . $result['message'];
                }

                $output->writeln($result['status'] === 'error' ? "<error>$line</error>" : $line);
                $failed = $failed || $result['status'] === 'error';
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    private function find(string $identifier, bool $model): ?EntityWithValuesInterface
    {
        if ($model) {
            $entity = $this->productModelRepository->findOneByIdentifier($identifier);
        } elseif (Uuid::isValid($identifier)) {
            $entity = $this->productRepository->findOneByUuid(Uuid::fromString($identifier));
        } else {
            $entity = $this->productRepository->findOneByIdentifier($identifier);
        }

        return $entity instanceof EntityWithValuesInterface ? $entity : null;
    }
}
