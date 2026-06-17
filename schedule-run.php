<?php

/**
 * Plesk scheduled task entrypoint: Run a PHP script, cron * * * * *
 * Script path (relative to domain home): bus-v2.kotor.me/schedule-run.php
 */

use Symfony\Component\Console\Input\ArgvInput;

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$status = $app->handleCommand(new ArgvInput(['artisan', 'schedule:run']));

exit($status);
