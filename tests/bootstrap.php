<?php

// The unit tests cover src/Api and src/Translation/Planner.php, which have no Akeneo
// dependencies: they run with only PHPUnit available (no `composer install` of Akeneo needed).
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

spl_autoload_register(static function (string $class): void {
    foreach (['Supertext\\AkeneoTranslationBundle\\Tests\\' => __DIR__ . '/', 'Supertext\\AkeneoTranslationBundle\\' => __DIR__ . '/../src/'] as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
