--TEST--
--FILE--
<?php

putenv('SYMFONY_DEPRECATIONS_HELPER');
putenv('SYMFONY_COMPILER_DEPRECATIONS='.__DIR__.'/container_deprecations/container_deprecations.log');
putenv('ANSICON');
putenv('ConEmuANSI');
putenv('TERM');

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
define('PHPUNIT_COMPOSER_INSTALL', $vendor.'/vendor/autoload.php');
require PHPUNIT_COMPOSER_INSTALL;
require_once __DIR__.'/../../bootstrap.php';
--EXPECTF--

Unsilenced deprecation notices (1)

  1x: Not setting "logout_on_user_change" to true on firewall "main" is deprecated as of 3.4, it will always be true in 4.0.
    1x in DeprecationErrorHandler::Symfony\Bridge\PhpUnit\{closure} from Symfony\Bridge\PhpUnit
