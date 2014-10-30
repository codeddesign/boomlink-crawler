<?php
/* Load requirements: */
require_once 'autoload.php';

#
$lt = new LoadTime();

# Available services:
$services = array(
    array('class' => 'WhoIs', 'wait' => true),
    array('class' => 'RobotsFile', 'wait' => true),

    array('class' => 'CrawlProject', 'wait' => false),
    array('class' => 'ApiData', 'wait' => false),
    array('class' => 'ProxyData', 'wait' => false),
);

# RUN:
$test = new ProjectListener($services);
$test->doSets();
$test->doWork();
