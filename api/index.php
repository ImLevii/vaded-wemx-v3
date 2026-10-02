<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';

(require __DIR__.'/../bootstrap/vercel.php')($app);

$app->handleRequest(Request::capture());
