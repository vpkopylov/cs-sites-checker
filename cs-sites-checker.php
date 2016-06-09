#!/usr/bin/php
<?php
$loader = require __DIR__ . '/vendor/autoload.php';
$loader->add('SitesChecker', __DIR__ . '/classes');
use SitesChecker\Console\RunCommand;
use SitesChecker\Console\WorkerCommand;
use Symfony\Component\Console\Application;

$application = new Application();

$application->add(new RunCommand);
$application->add(new WorkerCommand);
$application->run();

return true;
