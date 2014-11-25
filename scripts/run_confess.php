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
require_once __DIR__ . '/../libraries/runPhantom.php';
require_once __DIR__ . '/../helpers/Standards.php';

// go:
$rp = new RunPhantom(
    array(
        'xvfb_bin' => '/usr/bin/xvfb-run -a',
        'phantom_bin' => '/usr/bin/phantomjs',
        'confess_path' => __DIR__ . '/confess.js',
        'link' => $url,
        'mode' => 'performance',
    )
);

$rp->run();
exit($rp->getResult());