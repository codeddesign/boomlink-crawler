<?php

class Robots
{
    protected $curl, $link, $linkRobots, $userAgent;

    /**
     * @param $link
     * @param $userAgent
     */
    function __construct($link, $userAgent)
    {
        if (substr($link, 0, 4) !== 'http') {
            echo 'Invalid link: missing \'scheme\' (http|https). This should never happen.' . "\n";
            return false;
        }

        // sets:
        $this->link = $link;
        $this->linkRobots = $this->getRobotsLink();
        $this->userAgent = $userAgent;

        // init curl:
        $this->curl = new Curl();
        $this->curl->addLinks($this->linkRobots);

        return true;
    }

    /**
     * @return string
     */
    protected function getRobotsLink()
    {
        $parts = parse_url($this->link);

        return $parts['scheme'] . '://' . $parts['host'] . '/robots.txt';
    }

    /**
     * @return array
     * ^ more precisely it returns allowed AND not-allowed paths applied to current $userAgent OR to all *
     */
    protected function parseRobotsFile()
    {
        $lines = explode("\n", $this->curl->getBodyOnly());
        foreach ($lines as $l_num => $line) {
            $line = trim($line);

            if (preg_match('#^\s*User-agent: (.*)#i', $line, $matched)) {
                $tempAgent = trim($matched[1]);
                if ($tempAgent == $this->userAgent OR $tempAgent == '*') {
                    //echo $lines[$l_num+1];
                }
            }
        }

        return array();
    }

    /**
     * @return array|bool
     */
    public function getRules()
    {
        // run curl:
        $this->curl->run();
        $info = $this->curl->getLinkCurlInfo();
        if ($info['http_code'] == '200') {
            return $this->parseRobotsFile();
        } else {
            return false;
        }
    }
}