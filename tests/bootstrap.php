<?php

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ROOTPATH'))
{
    define('ROOTPATH', dirname(__DIR__) . '/public/');
}

require_once dirname(__DIR__) . '/app/core/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Model.php';
require_once dirname(__DIR__) . '/app/core/ApiController.php';
require_once dirname(__DIR__) . '/app/core/Request.php';
require_once dirname(__DIR__) . '/app/core/Schema.php';
require_once dirname(__DIR__) . '/app/core/Migration.php';
require_once dirname(__DIR__) . '/app/core/MigrationRunner.php';

require_once __DIR__ . '/Support/TestDb.php';
require_once __DIR__ . '/Support/HttpClient.php';
require_once __DIR__ . '/TestCase.php';
