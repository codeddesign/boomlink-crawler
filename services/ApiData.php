<?php

class ApiData extends Service
{
    private $curl, $arguments, $domain_id, $dbo, $external_links, $urls, $link_ids;
    CONST MAX_LINKS = 5, CURRENT_STATUS = 0, NEW_STATUS = 1, SECONDS_PAUSE = 1;

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
                        'majestic_' . $info['id'] => Config::getApiLink('majestic', $info['page_url']),
                        'uclassify_read_' . $info['id'] => Config::getApiLink('uclassify_read', $info['page_url']),
                        'phantom_' . $info['id'] => Config::getConfessLink($info['page_url']),
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
                Standards::doPause('ApiData', static::SECONDS_PAUSE);
                exit('fin.');
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

        $q = 'UPDATE _sitemap_links SET api_data_status=' . static::NEW_STATUS . ' WHERE id IN (' . implode(',', $this->link_ids) . ')';
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
        $pattern = '(%s, \'%s\', \'%s\', \'%s\', \'%s\', \'%s\')';
        $values = array();
        foreach ($this->dataCollected as $link_id => $info) {
            $values[] = sprintf($pattern, $link_id, $info['sentimental'], $info['negative'], $info['positive'], $info['page_weight'], $info['load_time']);
        }

        // prepare update keys:
        $patternKeys = '%s=VALUES(%s)';
        $tableKeys = array('link_id', 'sentimental', 'negative', 'positive', 'page_weight', 'load_time');
        $updateKeys = array();
        foreach ($tableKeys as $k_no => $key) {
            if ($key !== 'link_id') {
                $updateKeys[] = sprintf($patternKeys, $key, $key);
            }
        }

        $q = 'INSERT INTO _sitemap_links_info (' . implode(',', $tableKeys) . ') VALUES ';
        $q .= implode(',', $values) . ' ';
        $q .= 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updateKeys);

        return $this->dbo->runQuery($q);
    }

    private function getProjectLinks()
    {
        $q = 'SELECT * FROM _sitemap_links WHERE domain_id=' . $this->domain_id . ' AND api_data_status=' . static::CURRENT_STATUS . ' LIMIT ' . static::MAX_LINKS;
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
                case 'phantom':
                    $temp = json_decode($content, true);
                    if (!is_array($temp)) {
                        $temp = array(
                            'url' => $this->urls[$link_id]['page_url'],
                            'duration' => 'n/a',
                            'size' => 'n/a',
                        );
                    }

                    $this->dataCollected[$link_id]['page_weight'] = $temp['size'];
                    $this->dataCollected[$link_id]['load_time'] = $temp['duration'];
                    $this->dataCollected[$link_id]['ignore_it'] = $temp['url'];
                    break;
            }
        }
    }
}