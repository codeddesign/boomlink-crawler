<?php

class DurationSizeData extends Service
{
    private $url_to_confess, $curl;

    /**
     * @param array $arguments
     */
    public function doSets($arguments = array())
    {
        $replace = array(
            '/8win/',
            '/var/www/html/',
            '/var/www/',
            '/services',
        );

        $path = str_ireplace($replace, '', __DIR__);
        $this->url_to_confess = 'http://localhost/' . $path . '/scripts/run_confess.php?url=' . $arguments['url'];
    }

    public function doWork()
    {
        $this->curl = new Curl();
        $this->curl->addLinks($this->url_to_confess);
        $this->curl->run();

        $this->parseData();
    }

    private function parseData()
    {
        $bodies = $this->curl->getBodyOnly();
        $this->dataCollected = json_decode($bodies, TRUE);
    }
}