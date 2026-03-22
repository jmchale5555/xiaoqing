<?php

spl_autoload_register(function ($className)
{
    $className = ltrim($className, '\\');
    $parts = explode('\\', $className);
    $shortName = end($parts);

    if (!is_string($shortName) || $shortName === '')
    {
        return;
    }

    $modelFile = __DIR__ . '/../models/' . ucfirst($shortName) . '.php';
    if (is_file($modelFile))
    {
        require_once $modelFile;
        return;
    }

    $isModelClass = str_starts_with($className, 'Model\\') || count($parts) === 1;
    if ($isModelClass)
    {
        $message = "Autoload failed for model class '{$className}'. Expected file: {$modelFile}";
        error_log($message);

        if (defined('DEBUG_MODE') && DEBUG_MODE)
        {
            throw new \RuntimeException($message);
        }
    }
});

require __DIR__ . '/config.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/Database.php';
require __DIR__ . '/Model.php';
require __DIR__ . '/ApiController.php';
require __DIR__ . '/App.php';
require __DIR__ . '/Session.php';
require __DIR__ . '/Request.php';
