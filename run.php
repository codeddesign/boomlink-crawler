#!/usr/bin/php -q
<?php
declare(ticks = 1);

/* Load requirements: */
require_once 'autoload.php';

#
$lt = new LoadTime();

# Available services:
$services = array(
    array('class' => 'DomainData', 'wait' => false),
    array('class' => 'CrawlProject', 'wait' => false),
    array('class' => 'ApiData', 'wait' => false),
    array('class' => 'ProxyData', 'wait' => false),
    array('class' => 'PhantomData', 'wait' => false),
);

# RUN:
$test = new ProjectListener($services);
$test->doSets();
$test->doWork();
