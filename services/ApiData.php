<?php

class ApiData extends Service
{
    private $arguments, $domain_id, $dbo, $external_links, $urls, $link_ids, $curl;
    CONST MAX_LINKS = 5, SECONDS_PAUSE = 1;

    /**
     * [!IMPORTANT] $arguments is an array of arrays holding links to be parsed and another needed information
     * @param array $arguments
     */
    public function doSets(array $arguments = array('domain_id' => ''))
    {
        $this->arguments = $arguments;
        $this->domain_id = $arguments['domain_id'];
        $this->dbo = new MySQL();
    }

    /**
     * Starts multiple curls with the external links;
     */
    public function doWork()
    {
        $RUN = true;
        while ($RUN !== false) {
            $this->urls = $this->getProjectLinks();

            if ($this->urls === FALSE) {
                $RUN = false;
            } else {
                //
                $this->external_links = array();
                $this->link_ids = array();
                foreach ($this->urls as $a_no => $info) {
                    $this->link_ids[] = $info['id'];

                    $temp = array(
                        'majestic_' . $info['id'] => Config::getApiLink('majestic', $info['PageURL']),
                        'uclassify_read_' . $info['id'] => Config::getApiLink('uclassify_read', $info['PageURL']),
                    );

                    $this->external_links = array_merge($this->external_links, $temp);
                }

                // do the actual curl:
                $this->curl = new Curl();
                $this->curl->addLinks($this->external_links);
                $this->curl->run();

                // parse body's for needed data:
                $this->parseApiData();

                # save data:
                $this->saveData();
                $this->updateStatus();

                # pause:
                Standards::doPause($this->serviceName . '[pid: ' . $this->getPID() . ' | domain_id: ' . $this->domain_id . ']', self::SECONDS_PAUSE);
            }
        }
    }

    /**
     * @return bool
     */
    private function updateStatus()
    {
        if (count($this->link_ids) == 0) {
            return false;
        }

        $pattern = 'UPDATE page_main_info SET api_data_status=%d WHERE id IN (%s)';
        $q = sprintf($pattern, Config::NEW_STATUS, implode(',', $this->link_ids));
        return $this->dbo->runQuery($q);
    }

    /**
     * @return mixed
     */
    private function saveData()
    {
        if (count($this->dataCollected) == 0) {
            return false;
        }

        // prepare values:
        $pattern = '(%s, \'%s\', \'%s\', \'%s\')';
        $values = array();
        foreach ($this->dataCollected as $link_id => $info) {
            $values[] = sprintf($pattern, $link_id, $info['sentimental'], $info['negative'], $info['positive']);
        }

        // prepare update keys:
        $patternKeys = '%s=VALUES(%s)';
        $tableKeys = array('id', 'sentimental', 'negative', 'positive');
        $updateKeys = array();
        foreach ($tableKeys as $k_no => $key) {
            if ($key !== 'id') {
                $updateKeys[] = sprintf($patternKeys, $key, $key);
            }
        }

        $pattern = 'INSERT INTO page_main_info (%s) VALUES %s ON DUPLICATE KEY UPDATE %s';
        $q = sprintf($pattern, implode(',', $tableKeys), implode(',', $values), implode(',', $updateKeys));
        return $this->dbo->runQuery($q);
    }

    /**
     * @return mixed
     */
    private function getProjectLinks()
    {
        $pattern = 'SELECT * FROM page_main_info WHERE DomainURLIDX=%d AND api_data_status=%d LIMIT %d';
        $q = sprintf($pattern, $this->domain_id, Config::CURRENT_STATUS, self::MAX_LINKS);
        return $this->dbo->getResults($q);
    }

    private function parseApiData()
    {
        $bodies = $this->curl->getBodyOnly();
        foreach ($bodies as $key => $content) {
            $parts = explode('_', $key);
            $match = $parts[0];
            $link_id = $parts[count($parts) - 1];

            switch ($match) {
                case 'majestic':
                    $arr = json_decode($content, TRUE);
                    $total = 0;

                    if (isset($arr['DataTables']['BackLinks']['Headers']['TotalBackLinks'])) {
                        $total = $arr['DataTables']['BackLinks']['Headers']['TotalBackLinks'];
                    }

                    $this->dataCollected[$link_id]['sentimental'] = $total;
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

                    $this->dataCollected[$link_id]['negative'] = $save['negative'];
                    $this->dataCollected[$link_id]['positive'] = $save['positive'];
                    break;
            }
        }
    }
}