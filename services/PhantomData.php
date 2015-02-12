<?php

class PhantomData extends Service implements ServiceInterface
{
    private $dbo, $urls, $link_ids, $external_links, $userAgentName;
    private $responses;

    function doSets(array $arguments = array())
    {
        $this->userAgentName = 'boomlink';

        $this->dbo = new MySQL();
    }

    function doWork()
    {
        /*# todo: remove [tests]
        sleep(10);
        echo 'phantom data - service now should exit!'."\n";
        exit(9);*/

        $RUN = true;
        while ($RUN) {
            $this->urls = $this->getProjectLinks();
            if ($this->urls === FALSE) {
                #$RUN = false;
                Standards::doDelay($this->serviceName . '[pid: ' . $this->getPID() . ']', Config::getDelay('phantom_data_wait'));
            } else {
                //
                $this->external_links = array();
                $this->link_ids = array();
                foreach ($this->urls as $a_no => $info) {
                    $this->link_ids[] = $info['id'];
                    $this->external_links[$info['id']] = Config::getConfessLink($info['PageURL']);
                }

                // do the actual curl:
                $multi = new RequestMulti( $this->userAgentName );
                $multi->addLinks( $this->external_links );
                $multi->send();
                $this->responses = $multi->getResponse();

                // parse body's for needed data:
                $this->parseData();

                # save data:
                $this->saveData();
                $this->updateStatus();

                # pause:
                Standards::doDelay($this->serviceName . '[pid: ' . $this->getPID() . ']', Config::getDelay('phantom_data_pause'));
                $this->dataCollected = array();
            }

            # $RUN = false;
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

    private function parseData()
    {
        foreach ($this->responses as $key => $response) {
            $temp = json_decode( $response->body, true );
            if ( ! is_array( $temp )) {
                $temp = array(
                    'duration' => 'n/a',
                    'size'     => 'n/a',
                );
            }

            $this->dataCollected[$response->linkId]['page_weight'] = $temp['size'];
            $this->dataCollected[$response->linkId]['load_time']   = $temp['duration'];
        }
    }
}