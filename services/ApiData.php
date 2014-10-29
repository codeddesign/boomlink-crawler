<?php

class ApiData extends Service
{
    private $curl, $external_links, $arguments;

    /**
     * [!IMPORTANT] $arguments is an array of arrays holding links to be parsed and another needed information
     * @param array $arguments
     */
    public function doSets(array $arguments = array('domain_id' => '', 'urls' => array()))
    {
        $this->arguments = $arguments;

        if (!isset($arguments['urls']) OR !is_array($arguments['urls'])) {
            Standards::debug(__CLASS__ . ': expects arguments to hold an array of urls.', Standards::DO_EXIT);
        }

        $this->external_links = array();
        foreach ($arguments['urls'] as $a_no => $info) {
            $temp = array(
                'majestic_' . $a_no => Config::getApiLink('majestic', $info['url']),
                'uclassify_read_' . $a_no => Config::getApiLink('uclassify_read', $info['url']),
            );

            $this->external_links = array_merge($this->external_links, $temp);
        }
    }

    /**
     * Starts multiple curls with the external links;
     */
    public function doWork()
    {
        // do the actual curl:
        $this->curl = new Curl();
        $this->curl->addLinks($this->external_links);
        $this->curl->run();

        // parse body's for needed data:
        $this->parseApiData();
    }

    private function parseApiData()
    {
        $bodies = $this->curl->getBodyOnly();
        foreach ($bodies as $key => $content) {
            $parts = explode('_', $key);
            $match = $parts[0];
            $link_no = $parts[count($parts) - 1];

            switch ($match) {
                case 'majestic':
                    $arr = json_decode($content, TRUE);
                    $total = 0;

                    if (isset($arr['DataTables']['BackLinks']['Headers']['TotalBackLinks'])) {
                        $total = $arr['DataTables']['BackLinks']['Headers']['TotalBackLinks'];
                    }

                    $this->dataCollected[$match][$this->arguments['urls'][$link_no]['link_id']] = $total;
                    break;
                case 'uclassify':
                    $xml = simplexml_load_string($content);
                    $arr = json_decode(json_encode($xml), TRUE);

                    // default:
                    $save = array(
                        'negative' => '0.000000',
                        'positive' => '0.000000'
                    );

                    if (isset($arr['readCalls']['classify']['classification']['class'])) {
                        $info = $arr['readCalls']['classify']['classification']['class'];

                        foreach ($info as $key_no => $temp) {
                            $attributes = $temp['@attributes'];
                            $save[$attributes['className']] = $attributes['p'];
                        }
                    }

                    $this->dataCollected[$match][$this->arguments['urls'][$link_no]['link_id']] = $save;
                    break;
            }
        }
    }
}