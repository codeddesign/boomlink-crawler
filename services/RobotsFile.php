<?php

class Robots extends Service
{
    private $curl, $link, $linkRobots, $userAgent;

    /**
     * @param array $arguments
     */
    public function makeSets(array $arguments = array('url' => '', 'crawlerAgent' => ''))
    {

        $this->link = $arguments['url'];
        $this->userAgent = $arguments['crawlerAgent'];
        $this->linkRobots = $this->getRobotsLink();
        if (substr($this->link, 0, 4) !== 'http') {
            $this->debug('Invalid link: missing \'scheme\' (http|https). This should never happen.' . "\n", static::DO_EXIT);
        }

        // init curl:
        $this->curl = new Curl();
        $this->curl->addLinks($this->linkRobots);
    }

    /**
     * @return string
     */
    private function getRobotsLink()
    {
        $parts = parse_url($this->link);

        return $parts['scheme'] . '://' . $parts['host'] . '/robots.txt';
    }

    /**
     * @return array
     * ^ more precisely it returns allowed AND not-allowed paths applied to current $userAgent OR to all *
     */
    private function parseRobotsFile()
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
    private function getRules()
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