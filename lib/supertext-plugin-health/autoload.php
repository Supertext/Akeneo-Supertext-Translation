<?php

declare(strict_types=1);

/*
 * Autoloader for using Supertext Plugin Health without Composer (tests, PHPStan). Written by
 * scripts/vendor-plugin-health.sh. With Composer, the bundle's composer.json maps the namespace.
 */
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Supertext\\PluginHealth\\')) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, \strlen('Supertext\\PluginHealth\\'))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
