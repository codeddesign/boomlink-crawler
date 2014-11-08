<?php
/**
 * @param $className
 */
function __autoload($className)
{
    $dirs = array(
        'helpers',
        'libraries',
        'services',

    );

    foreach ($dirs as $d_no => $dir) {
        $fileName = '../' . $dir . '/' . $className . '.php';
        if (file_exists($fileName)) {
            require_once $fileName;
        }
    }
}

// load time of the script:
$ld = new LoadTime();

if (!isset($_POST['atOnce']) OR $_POST['atOnce'] == 0) {
    header('Location: index.php?err=1');
    exit();
}

$rules = array(
    'atOnce' => trim($_POST['atOnce']),
    'botName' => trim($_POST['botName']),
    'maxDepth' => '3',
);

$url = trim($_POST['url']);
if (!Standards::linkHasScheme($url)) {
    $url = 'http://' . $url;
}

$project = array(
    // new:
    'project_title' => $_POST['project_title'],
    'url' => $url,
    'config' => json_encode($rules, true),
);

$domain = Standards::getHost($project['url']);
$clean_url = Standards::getCleanURL($url);

// init db:
$db = new MySQL();

# run needed queries:
// save project:
$q = 'INSERT INTO status_domain (project_title, domain_name, DomainURL, config) VALUES (\'' . addslashes($project['project_title']) . '\', \'' . $domain . '\', \'' . $clean_url . '\', \'' . addslashes($project['config']) . '\')';
$domain_id = $db->runQuery($q);

# extra add:
$q = 'INSERT INTO domains_to_crawl (idx, DomainURL) VALUES (\'' . $domain_id . '\', \'' . $project['url'] . '\')';
$db->runQuery($q);

// save main link:
if (isset($_POST['links']) AND strlen(trim($_POST['links'])) > 0) {
    $values = array();
    $lines = explode("\n", $_POST['links']);
    foreach ($lines as $l_num => $line) {
        $line = trim($line);
        $values[] = '(\'' . $domain_id . '\', \'' . $line . '\')';
    }

    $q = 'INSERT INTO page_main_info (DomainURLIDX, pageURL) VALUES ';
    $q .= implode(',', $values);
    $db->runQuery($q);
} else {
    $q = 'INSERT INTO page_main_info (DomainURLIDX, pageURL) VALUES (\'' . $domain_id . '\', \'' . $clean_url . '\')';
    $link_id = $db->runQuery($q);
}

header('Location: index.php?msg=1');