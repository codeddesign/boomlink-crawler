#!/usr/bin/php -q
<?php
if (isset($_SERVER['REQUEST_URI'])) {
    exit('you can\'t access this file from www.');
}

declare(ticks = 1);

/* Load requirements: */
require_once 'autoload.php';

# Available services:
$services = Config::getAvailableServices();

# RUN:
$test = new ProjectListener($services);
$test->doSets();
$test->doWork();
