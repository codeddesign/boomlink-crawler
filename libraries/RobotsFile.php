<?php

class RobotsFile
{
    private $link, $data;

    /**
     * @param array $arguments
     */
    public function __construct(array $arguments = array('url' => '', 'domain_id' => ''))
    {
        $this->link = trim($arguments['url']);
        $this->data = array(
            'domain' => Standards::getHost($this->link),
            'domain_id' => $arguments['domain_id']
        );
        $this->data['robots_file'] = false;

        $this->doWork();
    }

    /**
     * Runs a curl to the robots.txt file of the given link and it's saves the body to $collectedData if the files is found.
     */
    private function doWork()
    {
        $curl = new Curl();
        $curl->addLinks($this->getRobotsLink());
        $curl->run();

        $curlInfo = $curl->getLinkCurlInfo();
        $curlInfo = (is_array($curlInfo)) ? $curlInfo[key($curlInfo)] : $curlInfo;

        if (isset($curlInfo['http_code']) AND substr($curlInfo['http_code'], 0, 2) == '20') {
            $body = $curl->getBodyOnly();
            $this->data['robots_file'] = is_array($body) ?  $body[key($body)] : $body;
        }
    }

    /**
     * @return string
     */
    private function getRobotsLink()
    {
        if (strlen($this->link) == 0 OR !Standards::linkHasScheme($this->link)) {
            # This should never happen:
            Standards::debug(__CLASS__ . ': Invalid link: missing \'scheme\' (http|https).' . "\n", Standards::DO_EXIT);
        }

        $parts = parse_url($this->link);
        return $parts['scheme'] . '://' . $parts['host'] . '/robots.txt';
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }
}