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

if (!isset($_POST['maxDepth']) OR $_POST['maxDepth'] == '0' OR $_POST['atOnce'] == 0) {
    header('Location: index.php?err=1');
    exit();
}

$rules = array(
    'atOnce' => trim($_POST['atOnce']),
    'botName' => trim($_POST['botName']),
    'maxDepth' => trim($_POST['maxDepth']),
);

$project = array(
    // new:
    'project_title' => $_POST['project_title'],
    'url' => trim($_POST['url']),
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
if (isset($_POST['links']) AND strlen(trim($_POST['links'])) > 0) {
    $values = array();
    $lines = explode("\n", $_POST['links']);
    foreach ($lines as $l_num => $line) {
        $line = trim($line);
        $values[] = '(\'' . $domain_id . '\', \'' . $line . '\')';
    }

    $q = 'INSERT INTO _sitemap_links (domain_id, page_url) VALUES ';
    $q .= implode(',', $values);
    $db->runQuery($q);
} else {
    $q = 'INSERT INTO _sitemap_links (domain_id, page_url) VALUES (\'' . $domain_id . '\', \'' . $clean_url . '\')';
    $link_id = $db->runQuery($q);
}

header('Location: index.php?msg=1');