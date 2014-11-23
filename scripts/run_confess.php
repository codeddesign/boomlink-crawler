<?php
// pre-check:
if (isset($_GET['url'])) {
    $url = trim($_GET['url']);
} elseif (isset($argv[1])) {
    $url = trim($argv[1]);
} else {
    exit('no url received');
}

/**
 * @param $output
 * @return bool
 */
function valuesAreOK($output)
{
    if ($output == NULL OR stripos($output, 'duration') == FALSE) {
        return FALSE;
    }

    if (stripos($output, '{') === FALSE AND stripos($output, '}') === FALSE) {
        return FALSE;
    }

    $output = substr($output, strpos($output, '{'), strpos($output, '}') + 1);
    try {
        $output = json_decode($output, true);
    } catch (Exception $e) {
        // ..
    }

    if (!is_array($output) OR stripos($output['duration'], 'nan') !== FALSE OR stripos($output['size'], 'nan') !== FALSE) {
        return false;
    }

    return $output;
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
$valuesOK = FALSE;

// make attempts:
$DEFAULT = '{"url":"' . $url . '","duration":"n/a","size":"n/a"}';
while (!$valuesOK AND $attempt < $max_attempts) {
    // ! keep order for $cmd_args
    $cmd_args = array(
        # '/usr/bin/xvfb-run --auto-servernum', // needed because we are not running it in a window
        '/usr/bin/phantomjs', // path to phantomJs app
        __DIR__ . '/confess.js "' . $url . '" performance', // path to confess.js
    );
    $cmd = implode(' ', $cmd_args); // makes it string

    // run:
    $RESULT = shell_exec($cmd); // $output = array()

    // check:
    $valuesOK = valuesAreOK($RESULT);

    // increment + pause - if values are not ok:
    if (!$valuesOK) {
        $attempt++;
        usleep(rand(350, 750));
    }
}

// shows result:
if (!isset($RESULT) OR $valuesOK == FALSE OR !is_array($valuesOK)) {
    exit($DEFAULT);
}

exit(json_encode($valuesOK, true));