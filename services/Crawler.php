<?php
/*
 * Crawler class:
 * * Workflow:
 *   - acts like a server;
 *   - does the actual crawling based on project and it's settings (by user);
 *   - ^ so each project will have his own crawler;
 *   - creates a 'tree' (list) of 'nodes' (links) based on depth
 *   - has a default sleeping period between actual accesses to the links;
 * * Obligatory:
 *   - it obeys the main crawl engine rules (no-follow, no-index, robots.txt, others, ..);
 * * Mandatory or based on user's input:
 *   - depth to crawl too;
 *   - rules (like: ONLY no specific paths, AVOID specific paths, others, ...);
 * */

class Crawler {
    function __construct() {
    }

    protected function listen() {
    }

    protected function createSubCrawler() {
    }
}