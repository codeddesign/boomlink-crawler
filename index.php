<?php
/*
 * index.php
 * - acts like an external controller
 * - accepts a limited case of operations
 * - if no operation is set it exists stating that the controller 'exists'
 * */

$operations = array(
    'start',
    'stop',
    'status'
);

$op = 'n/a';
$msg = 'exists';
if (isset($_GET['op'])) {
    $op = strtolower(trim($_GET['op']));
}

function getRunning()
{
    $running = array();
    $lines = explode("\n", shell_exec('ps aux | grep \'php run.php\''));
    foreach ($lines as $l_no => $line) {
        if (trim($line) and stripos($line, 'grep') === false) {
            // remove 2 spaces:
            $line = str_replace('  ', ' ', $line);

            // split the line:
            $parts = explode(' ', $line);
            $total = count($parts);

            // 'form' command:
            $command = trim($parts[$total - 2] . ' ' . $parts[$total - 1]);
            if ($command == 'php run.php') {
                $running[] = $parts[1];
            }
        }
    }

    return $running;
}

/*
 * responses:
 * - started / killed / already
 * - on / off
 */
switch ($op) {
    case 'start':
        $msg = 'already';
        if (!count(getRunning())) {
            shell_exec('php run.php > /dev/null &');
            $msg = 'started';
        }

        break;
    case 'stop':
        $pids = getRunning();

        $msg = 'already';
        if (count($pids)) {
            shell_exec('kill ' . implode(' ', $pids));
            $msg = 'killed';
        }
        break;
    case 'status':
        $pids = getRunning();

        $msg = 'on';
        if (!count($pids)) {
            $msg = 'off';
        }
        break;
}

exit($msg);
