<?php

/* LoadTime (debugging purposes):
 * shows how long it took to load the 'test'
 * */

class LoadTime
{
    protected $start;
    protected $pid;

    function __construct($pid)
    {
        $this->pid = $pid;
        $this->start = $this->getTime();
    }

    /**
     * @return mixed
     */
    protected function getTime()
    {
        $mTime = explode(" ", microtime());
        return $mTime[1] + $mTime[0];
    }

    function __destruct()
    {
        $css = 'position:fixed; width: 100%%; bottom: 0;left: 0;text-align: center;background-color: black; color: yellow; font-family: Arial, cursive, sans-serif';
        $pattern = '<div style=\'' . $css . '\'>[%s] -> completed in: %s seconds.</div>';

        $msg = sprintf($pattern, $this->pid, number_format($this->getTime() - $this->start, 6));
        if (php_sapi_name() == 'cli') {
            echo "\n" . strip_tags($msg) . "\n";
        } else {
            echo $msg."\n";
        }
    }
}