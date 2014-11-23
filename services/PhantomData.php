<?php

class PhantomData extends Service implements ServiceInterface
{
    private $dbo, $urls, $link_ids, $external_links, $curl;

    function doSets(array $arguments = array())
    {
        $this->dbo = new MySQL();
    }

    function doWork()
    {
        $RUN = true;
        while ($RUN !== false) {
            $this->urls = $this->getProjectLinks();
            if ($this->urls === FALSE) {
                #$RUN = false;
                Standards::doDelay($this->serviceName . '[pid: ' . $this->getPID() . ']', 'phantom_data_wait');
            } else {
                //
                $this->external_links = array();
                $this->link_ids = array();
                foreach ($this->urls as $a_no => $info) {
                    $this->link_ids[] = $info['id'];
                    $this->external_links['phantom_' . $info['id']] = Config::getConfessLink($info['PageURL']);
                }

                // do the actual curl:
                $this->curl = new Curl();
                $this->curl->addLinks($this->external_links);
                $this->curl->run();

                // parse body's for needed data:
                $this->parseApiData();

                /*Standards::debug($this->external_links);
                Standards::debug($this->dataCollected);*/

                # save data:
                $this->saveData();
                $this->updateStatus();

                # pause:
                Standards::doDelay($this->serviceName . '[pid: ' . $this->getPID() . ']', Config::getDelay('phantom_data_pause'));
                $this->dataCollected = array();
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

        $pattern = 'UPDATE page_main_info SET phantom_data_status=%d WHERE id IN (%s)';
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
        $pattern = '(%s, \'%s\', \'%s\')';
        $values = array();
        foreach ($this->dataCollected as $link_id => $info) {
            $values[] = sprintf($pattern, $link_id, $info['page_weight'], $info['load_time']);
        }

        // prepare update keys:
        $patternKeys = '%s=VALUES(%s)';
        $tableKeys = array('id', 'page_weight', 'load_time');
        $updateKeys = array();
        foreach ($tableKeys as $k_no => $key) {
            if ($key !== 'id') {
                $updateKeys[] = sprintf($patternKeys, $key, $key);
            }
        }

        $pattern = 'INSERT INTO page_main_info (%s) VALUES %s ON DUPLICATE KEY UPDATE %s';
        $q = sprintf($pattern, implode(',', $tableKeys), implode(',', $values), implode(', ', $updateKeys));
        return $this->dbo->runQuery($q);
    }

    /**
     * @return mixed
     */
    private function getProjectLinks()
    {
        $pattern = 'SELECT * FROM page_main_info WHERE phantom_data_status=%d LIMIT %d';
        $q = sprintf($pattern, Config::CURRENT_STATUS, Config::getQueryLimit('phantom_data'));
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
                case 'phantom':
                    $temp = json_decode($content, true);
                    if (!is_array($temp)) {
                        $temp = array(
                            'duration' => 'n/a',
                            'size' => 'n/a',
                        );
                    }

                    $this->dataCollected[$link_id]['page_weight'] = $temp['size'];
                    $this->dataCollected[$link_id]['load_time'] = $temp['duration'];
                    break;
            }
        }
    }
}