#!/usr/bin/php -q
<?php
/* Load requirements: */
require_once 'autoload.php';

#
$lt = new LoadTime();

# Available services:
$services = array(
    array('class' => 'DomainData', 'wait' => true),
    array('class' => 'CrawlProject', 'wait' => true),
    array('class' => 'ApiData', 'wait' => true),
    array('class' => 'ProxyData', 'wait' => true),
    array('class' => 'PhantomData', 'wait' => true),
);

# RUN:
$test = new ProjectListener($services);
$test->doSets();
$test->doWork();
