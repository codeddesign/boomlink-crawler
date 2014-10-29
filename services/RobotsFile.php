<?php

class RobotsFile extends Service
{
    private $link;

    /**
     * @param array $arguments
     */
    public function makeSets(array $arguments = array('url' => '', 'domain_id' => ''))
    {
        $this->link = trim($arguments['url']);
        $this->dataCollected = array(
            'domain' => Standards::getHost($this->link),
            'domain_id' => $arguments['domain_id']
        );
    }

    /**
     * Runs a curl to the robots.txt file of the given link and it's saves the body to $collectedData if the files is found.
     */
    public function doWork()
    {
        $curl = new Curl();
        $curl->addLinks($this->getRobotsLink());
        $curl->run();

        $curlInfo = $curl->getLinkCurlInfo();
        if ($curlInfo['http_code'] == '200') {
            $this->dataCollected['robots.txt'] = $curl->getBodyOnly();
        } else {
            $this->dataCollected['robots.txt'] = FALSE;
        }
    }

    /**
     * @return string
     */
    private function getRobotsLink()
    {
        if (strlen($this->link) == 0 OR !Standards::linkHasScheme($this->link)) {
            # This should never happen:
            Standards::debug(__CLASS__ . ': Invalid link: missing \'scheme\' (http|https).' . "\n", static::DO_EXIT);
        }

        $parts = parse_url($this->link);
        return $parts['scheme'] . '://' . $parts['host'] . '/robots.txt';
    }
}