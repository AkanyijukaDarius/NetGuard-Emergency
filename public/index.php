<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Native\Mobile\Runtime;

define('LARAVEL_START', microtime(true));

// 1. Maintenance mode check
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// 2. Register the Composer autoloader
require __DIR__.'/../vendor/autoload.php';

// 3. Load the REAL application instance
/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';

// 4. Safety check for the 'true' return value
if ($app === true) {
    $app = Application::getInstance();
}

// 5. Boot the NativePHP Mobile runtime ONLY ONCE using the REAL $app
// This ensures the Kernel and all bindings are available
if (class_exists(Runtime::class) && !Runtime::isBooted()) {
    Runtime::boot($app);
}

// 6. Handle the request
$app->handleRequest(Request::capture());
