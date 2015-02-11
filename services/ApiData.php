<?php

class ApiData extends Service implements ServiceInterface
{
    private $arguments, $dbo, $external_links, $urls, $link_ids;
    private $responses;
    private $userAgentName;

    /**
     * [!IMPORTANT] $arguments is an array of arrays holding links to be parsed and another needed information
     * @param array $arguments
     */
    public function doSets(array $arguments = array('domain_id' => '', 'domain_name' => ''))
    {
        $this->userAgentName = 'boomlink';

        $this->arguments = $arguments;

        $this->dbo = new MySQL();
    }

    /**
     * Starts multiple curls with the external links;
     */
    public function doWork()
    {
        /*# todo: remove [tests]
        sleep(10);
        echo 'api data - service now should exit!'."\n";
        exit(9);*/

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
                $multi = new RequestMulti( $this->userAgentName );
                $multi->addLinks( $this->external_links );
                $multi->send();
                $this->responses = $multi->getResponse();

                // parse body's for needed data:
                $this->parseApiData();

                # save data:
                $this->saveData();
                $this->updateStatus();

                # pause:
                Standards::doDelay($this->serviceName . '[pid: ' . $this->getPID() . ' | domain_id: ' . $this->arguments['domain_name'] . ']', Config::getDelay('api_data_pause'));
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
        $pattern = '(%s, \'%s\', \'%s\', \'%s\', \'%s\')';
        $values = array();
        foreach ($this->dataCollected as $link_id => $info) {
            $values[] = sprintf($pattern, $link_id, $info['total_back_links'], $info['sentimental_negative'], $info['sentimental_positive'], $info['sentimental_type']);
        }

        // prepare update keys:
        $patternKeys = '%s=VALUES(%s)';
        $tableKeys = array('id', 'total_back_links', 'sentimental_negative', 'sentimental_positive', 'sentimental_type');
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
        $q = sprintf($pattern, $this->arguments['domain_id'], Config::CURRENT_STATUS, Config::getQueryLimit('api_data'));
        return $this->dbo->getResults($q);
    }

    private function parseApiData()
    {
        foreach ($this->responses as $r_no => $response) {
            $parts = explode('_', $response->linkId);
            $match = $parts[0];
            $link_id = $parts[count($parts) - 1];

            switch ($match) {
                case 'majestic':
                    $arr = json_decode($response->body, TRUE);
                    $total = 0;

                    if (isset($arr['DataTables']['BackLinks']['Headers']['TotalBackLinks'])) {
                        $total = $arr['DataTables']['BackLinks']['Headers']['TotalBackLinks'];
                    }

                    $this->dataCollected[$link_id]['total_back_links'] = $total;
                    break;
                case 'uclassify':
                    $xml = simplexml_load_string($response->body);
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

                    $this->dataCollected[$link_id]['sentimental_negative'] = $save['negative'];
                    $this->dataCollected[$link_id]['sentimental_positive'] = $save['positive'];
                    $this->dataCollected[$link_id]['sentimental_type'] = $this->getSentimentalType($save['negative'], $save['positive']);
                    break;
            }
        }
    }

    /**
     * @param $negative
     * @param $positive
     * @return int
     */
    private function getSentimentalType($negative, $positive)
    {
        if ($negative == $positive) {
            return Standards::SENTIMENTAL_NEUTRAL;
        }

        return ($negative > $positive) ? Standards::SENTIMENTAL_NEGATIVE : Standards::SENTIMENTAL_POSITIVE;
    }
}