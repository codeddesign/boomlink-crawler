<?php
// pre-check:
if (isset($_GET['url'])) {
    $url = trim($_GET['url']);
} elseif (isset($argv[1])) {
    $url = trim($argv[1]);
} else {
    exit('no url received');
}

// load needed:
require_once __DIR__ . '/../libraries/RunPhantom.php';
require_once __DIR__ . '/../helpers/Standards.php';

// needed params
$params = array(
    'xvfb_bin' => '/usr/bin/xvfb-run -a',
    'phantom_bin' => '/usr/bin/phantomjs',
    'js_script_path' => __DIR__.'/netsniff.js',
    'link' => $url,
);

// go:
$rp = new RunPhantom($params);

$rp->run();
exit($rp->getResult());