<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Command;

use Supertext\AkeneoTranslationBundle\Api\SupertextException;
use Supertext\AkeneoTranslationBundle\Settings\Settings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** bin/console supertext:check: is an API key set, and does Supertext accept it? */
final class CheckCommand extends Command
{
    protected static $defaultName = 'supertext:check';

    public function __construct(private readonly Settings $settings)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Checks the Supertext API key and connection');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $client = $this->settings->client();
        $output->writeln(sprintf('Supertext Translation for Akeneo PIM %s', Settings::version() !== '' ? Settings::version() : '(version unknown)'));

        if (!$client->hasApiKey()) {
            $output->writeln('<error>No Supertext API key is configured.</error>');
            $output->writeln('No Supertext account yet? Create one at ' . Settings::SIGNUP_URL);
            $output->writeln('Generate your API key at supertext.com → Integrations → API (requires the Admin role): ' . Settings::API_KEY_URL);
            $output->writeln('Then enter it under System → Supertext or set SUPERTEXT_API_KEY.');

            return Command::FAILURE;
        }

        try {
            $client->validateApiKey();
        } catch (SupertextException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('Supertext API key accepted (%s, key from %s).', $this->settings->baseUrl(), $this->settings->apiKeySource() === 'environment' ? 'SUPERTEXT_API_KEY' : 'System → Supertext'));

        return Command::SUCCESS;
    }
}
