<?php

class DomainData extends Service
{
    private $params, $dbo;

    public function doSets(array $arguments = array('url' => '', 'domain_id' => ''))
    {
        $this->params = $arguments;
    }

    public function doWork()
    {
        # init db:
        $this->dbo = new MySQL();

        # get robots file:
        $rf = new RobotsFile($this->params);
        $this->dataCollected = $rf->getData();

        # get who is info:
        $wi = new WhoIs($this->params);
        $this->dataCollected = array_merge($this->dataCollected, $wi->getData());

        # save data:
        $this->saveCollectedData();

        Standards::doDelay($this->serviceName, rand(5, 10));
    }

    /**
     * Expected data: whois / robotsfile
     */
    private function saveCollectedData()
    {
        $info = $this->getDataCollected();
        if (!is_array($info) OR count($info) == 0) {
            return false;
        }

        // sets:
        $values = $updateKeys = array();

        # backup id add extra value:
        $info['id'] = $info['domain_id'];
        $info['status'] = '1';

        # remove:
        unset($info['domain_id']);
        unset($info['domain']);

        // create values[] to be inserted to db:
        $patternValues = '(%s, \'%s\', \'%s\', \'%s\', \'%s\', \'%s\', \'%s\')';
        $values[] = sprintf($patternValues, $info['id'], $info['robots_file'], $info['server_ip'], $info['registration_date'], $info['server_location'], $info['hosting_company'], $info['status']);

        // create update keys:
        $patternKeys = '%s=VALUES(%s)';
        $tableKeys = array('DomainURLIDX', 'robots_file', 'server_ip', 'registration_date', 'server_location', 'hosting_company', 'Status');
        foreach ($tableKeys as $k_no => $key) {
            if ($key !== 'id') {
                $updateKeys[] = sprintf($patternKeys, $key, $key);
            }
        }

        // pre-check if any values:
        if (count($values) > 0) {
            $pattern = 'INSERT INTO status_domain (%s) VALUES %s ON DUPLICATE KEY UPDATE %s';
            $q = sprintf($pattern, implode(',', $tableKeys), implode(',', $values), implode(',', $updateKeys));
            $this->dbo->runQuery($q);
        }

        return true;
    }
}