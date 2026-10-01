<?php

declare(strict_types=1);

$ilias_root = getenv('ILIAS_ROOT') ?: dirname(__DIR__, 9);
if (!is_file($ilias_root . '/vendor/composer/vendor/autoload.php')) {
    throw new RuntimeException('Set ILIAS_ROOT to an ILIAS 11 installation.');
}
require $ilias_root . '/vendor/composer/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/autoload.php';
defined('SYSTEM_ROLE_ID') || define('SYSTEM_ROLE_ID', 2);
defined('ANONYMOUS_USER_ID') || define('ANONYMOUS_USER_ID', 13);
