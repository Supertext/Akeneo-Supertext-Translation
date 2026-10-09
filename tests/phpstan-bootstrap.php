<?php

declare(strict_types=1);

// PHPStan needs Akeneo's classes: load the autoloader of an Akeneo installation.
// CI sets AKENEO_VENDOR (see .github/workflows/ci.yml → phpstan); locally, point it at
// the vendor/ folder of any Akeneo PIM 2026 project.
$vendor = getenv('AKENEO_VENDOR') ?: dirname(__DIR__) . '/vendor';
if (!is_file($vendor . '/autoload.php')) {
    fwrite(STDERR, "PHPStan: set AKENEO_VENDOR to the vendor/ folder of an Akeneo PIM installation.\n");
    exit(1);
}
require $vendor . '/autoload.php';
