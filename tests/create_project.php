<?php
require_once 'autoload.php';

// load time of the script:
$ld = new LoadTime();

$rules = array(
    'atOnce' => '10',
    'botName' => 'boomlink.de',
    'maxDepth' => '3',
);

$project = array(
    // new:
    'project_title' => 'ran-x',
    'url' => 'http://ran.de',
    'config' => json_encode($rules, true),
);
$domain = Standards::getHost($project['url']);
$clean_url = Standards::getCleanURL($project['url']) . '/';

// init db:
$db = new MySQL();

# run needed queries:
// save project:
$q = 'INSERT INTO _sitemap_domain_info (project_title, domain_name, project_url, config) VALUES (\'' . addslashes($project['project_title']) . '\', \'' . $domain . '\', \'' . $clean_url . '\', \'' . addslashes($project['config']) . '\')';
$domain_id = $db->runQuery($q);

// save main link:
$q = 'INSERT INTO _sitemap_links (domain_id, page_url) VALUES (\'' . $domain_id . '\', \'' . $clean_url . '\')';
$link_id = $db->runQuery($q);