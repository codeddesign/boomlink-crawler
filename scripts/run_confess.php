<?php
// pre-check:
if (isset($_GET['url'])) {
    $url = trim($_GET['url']);
} else {
    exit('no url received');
}

/**
 * @param $output
 * @return bool
 */
function valuesAreOK(array $output)
{
    return (stripos($output['duration'], 'nan') !== FALSE OR stripos($output['size'], 'nan') !== false) ? false : true;
}

// default:
$output = array(
    'url' => $url,
    'duration' => 0,
    'size' => 'n/a',
);

// sets:
$attempt = 0;
$max_attempts = 3;
$RESULT = '{"url":"' . $url . '","duration":"n/a","size":"n/a"}';
$valuesOK = FALSE;

// make attempts:
while (!$valuesOK AND $attempt < $max_attempts) {
    // ! keep order for $cmd_args
    $cmd_args = array(
        '/usr/bin/xvfb-run --auto-servernum', // needed because we are not running it in a window
        '/usr/bin/phantomjs', // path to phantomJs app
        __DIR__ . '/confess.js "' . $url . '" performance', // path to confess.js
    );
    $cmd = implode(' ', $cmd_args); // makes it string

    // run:
    exec($cmd, $output); // $output = array()
    $RESULT = $output[0];

    // check:
    $valuesOK = valuesAreOK(json_decode($RESULT, true));

    // increment + pause - if values are not ok:
    if (!$valuesOK) {
        $attempt++;
        usleep(rand(50, 100));
    }
}

// shows result:
exit($RESULT);