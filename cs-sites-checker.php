#!/usr/bin/php
<?php
spl_autoload_register(function ($class) {
    $class = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    require_once($class);
});

$app = new \SitesChecker\ClientApplication();
try {
    $app->parseOptions();
    $app->execute();
} catch (\Exception $e) {
    $message = $e instanceof \SitesChecker\Exceptions\CliArgException ?
        $app->usageString() : 'Fatal error: ' . $e->getMessage();
    echo $message . "\n";
    return false;
}
return true;
