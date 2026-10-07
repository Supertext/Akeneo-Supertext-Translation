<?php

declare(strict_types=1);

namespace Supertext\AkeneoDemo;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Demo only (bin/console supertext:demo-setup, run by the container on every start).
 *
 * Creates what is missing and never changes what exists:
 * - the locales en_US, de_CH, fr_CH and it_CH on the "ecommerce" channel,
 * - the attributes name, short_description and description (rich text, per channel),
 *   the family "chocolate" and the family variant "chocolate_by_weight",
 * - an English sample product and a sample product model with two variants,
 * - the demo accounts from DEMO_ADMIN_* and DEMO_EDITOR_* (passwords are never printed).
 */
final class DemoSetupCommand extends Command
{
    protected static $defaultName = 'supertext:demo-setup';

    public const LOCALES = ['en_US', 'de_CH', 'fr_CH', 'it_CH'];

    public function __construct(private readonly ContainerInterface $services)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Demo: locales, sample catalog and demo accounts (creates only what is missing)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->channel($output);
        $this->structure($output);
        $this->samples($output);
        $this->account($output, 'DEMO_ADMIN', true);
        $this->account($output, 'DEMO_EDITOR', false);

        return Command::SUCCESS;
    }

    private function channel(OutputInterface $output): void
    {
        $channel = $this->services->get('pim_catalog.repository.channel')->findOneByIdentifier('ecommerce');
        $missing = array_diff(self::LOCALES, $channel->getLocaleCodes());

        if ($missing === []) {
            return;
        }

        $this->services->get('pim_catalog.updater.channel')->update($channel, [
            'locales' => array_values(array_unique(array_merge($channel->getLocaleCodes(), self::LOCALES))),
            'labels'  => ['en_US' => 'E-commerce', 'de_CH' => 'E-Commerce', 'fr_CH' => 'E-commerce', 'it_CH' => 'E-commerce'],
        ]);
        $this->validateAndSave('channel', $channel);
        $output->writeln('[demo] Locales added to the ecommerce channel: ' . implode(', ', $missing));
    }

    private function structure(OutputInterface $output): void
    {
        $this->create('attribute_group', 'marketing', [
            'code' => 'marketing', 'sort_order' => 1,
            'labels' => ['en_US' => 'Marketing', 'de_CH' => 'Marketing', 'fr_CH' => 'Marketing', 'it_CH' => 'Marketing'],
        ], $output);

        $attributes = [
            'name' => ['type' => 'pim_catalog_text', 'localizable' => true, 'scopable' => false, 'labels' => ['en_US' => 'Name', 'de_CH' => 'Name', 'fr_CH' => 'Nom', 'it_CH' => 'Nome']],
            'short_description' => ['type' => 'pim_catalog_textarea', 'localizable' => true, 'scopable' => false, 'wysiwyg_enabled' => false,
                'labels' => ['en_US' => 'Short description', 'de_CH' => 'Kurzbeschreibung', 'fr_CH' => 'Description courte', 'it_CH' => 'Descrizione breve']],
            'description' => ['type' => 'pim_catalog_textarea', 'localizable' => true, 'scopable' => true, 'wysiwyg_enabled' => true,
                'labels' => ['en_US' => 'Description', 'de_CH' => 'Beschreibung', 'fr_CH' => 'Description', 'it_CH' => 'Descrizione']],
            'weight' => ['type' => 'pim_catalog_simpleselect', 'localizable' => false, 'scopable' => false,
                'labels' => ['en_US' => 'Weight', 'de_CH' => 'Gewicht', 'fr_CH' => 'Poids', 'it_CH' => 'Peso']],
        ];

        foreach ($attributes as $code => $data) {
            $this->create('attribute', $code, ['code' => $code, 'group' => 'marketing'] + $data, $output);
        }

        foreach (['100g' => '100 g', '400g' => '400 g'] as $code => $label) {
            if ($this->services->get('pim_catalog.repository.attribute_option')->findOneByIdentifier('weight.' . $code) === null) {
                $option = $this->services->get('pim_catalog.factory.attribute_option')->create();
                $this->services->get('pim_catalog.updater.attribute_option')->update($option, [
                    'attribute' => 'weight', 'code' => $code, 'labels' => array_fill_keys(self::LOCALES, $label),
                ]);
                $this->validateAndSave('attribute_option', $option);
            }
        }

        $this->create('family', 'chocolate', [
            'code'               => 'chocolate',
            'attributes'         => ['sku', 'name', 'short_description', 'description', 'weight'],
            'attribute_as_label' => 'name',
            'labels'             => ['en_US' => 'Chocolate', 'de_CH' => 'Schokolade', 'fr_CH' => 'Chocolat', 'it_CH' => 'Cioccolato'],
        ], $output);

        $this->create('family_variant', 'chocolate_by_weight', [
            'code'              => 'chocolate_by_weight',
            'family'            => 'chocolate',
            'variant_attribute_sets' => [['level' => 1, 'axes' => ['weight'], 'attributes' => ['sku', 'weight']]],
            'labels'            => ['en_US' => 'Chocolate by weight'],
        ], $output);
    }

    private function samples(OutputInterface $output): void
    {
        $products = $this->services->get('pim_catalog.repository.product');

        if ($products->findOneByIdentifier('praline-box-16') === null) {
            $product = $this->services->get('pim_catalog.builder.product')->createProduct('praline-box-16', 'chocolate');
            $this->services->get('pim_catalog.updater.product')->update($product, ['values' => self::englishValues(
                'Handmade praline box, 16 pieces',
                "Sixteen pralines from our Bern workshop, filled with hazelnut, caramel and dark ganache.\nA gift box for every occasion.",
                '<p>Every praline is filled and decorated <strong>by hand</strong> in our Bern workshop, with Swiss milk and cocoa from <a href="https://www.supertext.com">our fair trade partners</a>.</p><ul><li>Gluten-free</li><li>Keeps for six weeks at 15 to 18 °C</li></ul>',
            )]);
            $this->validateAndSave('product', $product);
            $output->writeln('[demo] Sample product praline-box-16 created.');
        }

        $models = $this->services->get('pim_catalog.repository.product_model');

        if ($models->findOneByIdentifier('dark-chocolate-bar') === null) {
            $model = $this->services->get('pim_catalog.factory.product_model')->create();
            $this->services->get('pim_catalog.updater.product_model')->update($model, [
                'code'           => 'dark-chocolate-bar',
                'family_variant' => 'chocolate_by_weight',
                'values'         => self::englishValues(
                    'Dark chocolate bar, 72% cocoa',
                    'A dark chocolate bar with notes of red berries, made from single-origin cocoa from Ecuador.',
                    '<p>We roast the cocoa beans <strong>slowly</strong> and conch the chocolate for 72 hours, which gives it its smooth texture.</p>',
                ),
            ]);
            $this->validateAndSave('product_model', $model);

            foreach (['100g', '400g'] as $weight) {
                $variant = $this->services->get('pim_catalog.builder.product')->createProduct('dark-chocolate-bar-' . $weight, 'chocolate');
                $this->services->get('pim_catalog.updater.product')->update($variant, [
                    'parent' => 'dark-chocolate-bar',
                    'values' => ['weight' => [['locale' => null, 'scope' => null, 'data' => $weight]]],
                ]);
                $this->validateAndSave('product', $variant);
            }

            $output->writeln('[demo] Sample product model dark-chocolate-bar created.');
        }
    }

    /** @return array<string, list<array{locale: ?string, scope: ?string, data: string}>> */
    private static function englishValues(string $name, string $short, string $description): array
    {
        return [
            'name'              => [['locale' => 'en_US', 'scope' => null, 'data' => $name]],
            'short_description' => [['locale' => 'en_US', 'scope' => null, 'data' => $short]],
            'description'       => [['locale' => 'en_US', 'scope' => 'ecommerce', 'data' => $description]],
        ];
    }

    private function account(OutputInterface $output, string $prefix, bool $admin): void
    {
        $email    = trim((string) getenv($prefix . '_EMAIL'));
        $password = (string) getenv($prefix . '_PASSWORD');

        if ($email === '' || $password === '') {
            $output->writeln(sprintf('[demo] %s_EMAIL / %s_PASSWORD not set, no %s account.', $prefix, $prefix, $admin ? 'admin' : 'editor'));

            return;
        }

        $users = $this->services->get('pim_user.repository.user');

        if ($users->findOneByIdentifier($email) !== null) {
            $output->writeln(sprintf('[demo] %s: account exists, left unchanged.', $prefix));

            return;
        }

        if (mb_strlen($password) < 8 || \strlen($password) > 4096) {
            $output->writeln(sprintf('[demo] WARNING: %s_PASSWORD does not meet Akeneo\'s password rules (8 to 4096 characters); %s account skipped.', $prefix, $prefix));

            return;
        }

        $username = preg_replace('/[^A-Za-z0-9._-]/', '', strstr($email, '@', true) ?: $email) ?: 'demo';

        if (\strlen($username) < 3) {
            $username .= '-demo';
        }

        for ($candidate = $username, $i = 2; $users->findOneByIdentifier($candidate) !== null; $i++) {
            $candidate = $username . '-' . $i;
        }

        $user = $this->services->get('pim_user.factory.user')->create();
        $this->services->get('pim_user.updater.user')->update($user, [
            'username'               => $candidate,
            'password'               => $password,
            'first_name'             => 'Demo',
            'last_name'              => $admin ? 'Administrator' : 'Editor',
            'email'                  => $email,
            'user_default_locale'    => 'en_US',
            'catalog_default_locale' => 'en_US',
            'catalog_default_scope'  => 'ecommerce',
            'default_category_tree'  => 'master',
            'roles'                  => $admin ? ['ROLE_ADMINISTRATOR'] : ['ROLE_CATALOG_MANAGER'],
            'groups'                 => $admin ? ['IT support', 'All'] : ['Redactor', 'All'],
        ]);

        $violations = $this->services->get('validator')->validate($user);

        if (\count($violations) > 0) {
            $messages = [];

            foreach ($violations as $violation) {
                if ($violation->getPropertyPath() !== 'password' && $violation->getPropertyPath() !== 'plainPassword') {
                    $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
                } else {
                    $messages[] = 'the password does not meet Akeneo\'s rules';
                }
            }

            $output->writeln(sprintf('[demo] WARNING: %s account skipped (%s).', $prefix, implode('; ', $messages)));

            return;
        }

        $this->services->get('pim_user.saver.user')->save($user);
        $output->writeln(sprintf('[demo] %s: account created (%s, username %s).', $prefix, $admin ? 'Administrator' : 'Catalog manager', $candidate));
    }

    /** @param array<string, mixed> $data */
    private function create(string $type, string $code, array $data, OutputInterface $output): void
    {
        if ($this->services->get('pim_catalog.repository.' . $type)->findOneByIdentifier($code) !== null) {
            return;
        }

        $entity = $this->services->get('pim_catalog.factory.' . $type)->create();
        $this->services->get('pim_catalog.updater.' . $type)->update($entity, $data);
        $this->validateAndSave($type, $entity);
        $output->writeln(sprintf('[demo] %s %s created.', str_replace('_', ' ', ucfirst($type)), $code));
    }

    private function validateAndSave(string $type, object $entity): void
    {
        $violations = $this->services->get(\in_array($type, ['product', 'product_model'], true) ? 'pim_catalog.validator.product' : 'validator')->validate($entity);

        if (\count($violations) > 0) {
            $messages = [];

            foreach ($violations as $violation) {
                $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }

            throw new \RuntimeException(sprintf('Demo setup: invalid %s: %s', $type, implode('; ', $messages)));
        }

        $this->services->get('pim_catalog.saver.' . $type)->save($entity);
    }
}
