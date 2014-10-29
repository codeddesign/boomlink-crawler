<?php

class ProjectListener extends Service
{
    private $dbo;

    /**
     * do some sets:
     */
    public function doSets()
    {
        // init db:
        $this->dbo = new MySQL();

        Standards::debug(__CLASS__ . ' (parent thread) is: ' . $this->getPID());
    }

    /**
     * @return array|bool
     */
    private function newProjects()
    {
        $q = 'SELECT * FROM _sitemap_domain_info WHERE status=0';
        return $this->dbo->getResults($q);
    }

    /**
     * Expected data: whois / robotsfile
     */
    private function saveCollectedData()
    {
        $collected = $this->getDataCollected();
        if (!is_array($collected) OR count($collected) == 0) {
            return false;
        }

        // sets:
        $values = $updateKeys = $tempData = array();
        $notNeeded = array(
            'domain',
            'domain_id',
        );

        // merge info because it's related to same db table
        $collected = array_values($collected);
        foreach ($collected as $k_no => $info) {
            foreach ($info as $i_no => $i) {
                if (isset($collected[$k_no + 1])) {
                    $tempData[$k_no] = array_merge($collected[$k_no][$i_no], $collected[$k_no + 1][$i_no]);

                    // save needed ones or add more:
                    $tempData[$k_no]['id'] = $tempData[$k_no]['domain_id'];
                    $tempData[$k_no]['status'] = '1';

                    // remove not needed ones:
                    foreach ($notNeeded as $n_no => $key) {
                        if (isset($tempData[$k_no][$key])) {
                            unset($tempData[$k_no][$key]);
                        }
                    }
                }
            }
        }

        // create values[] to be inserted to db:
        $patternValues = '(%s, \'%s\', \'%s\', \'%s\', \'%s\', \'%s\', \'%s\')';
        foreach ($tempData as $t_no => $info) {
            foreach ($info as $i_key => $i_value) {
                $info[$i_key] = addslashes($i_value);
            }

            $values[] = sprintf($patternValues, $info['id'], $info['robots_file'], $info['server_ip'], $info['registration_date'], $info['server_location'], $info['hosting_company'], $info['status']);
        }

        // create update keys:
        $patternKeys = '%s=VALUES(%s)';
        $tableKeys = array('id', 'robots_file', 'server_ip', 'registration_date', 'server_location', 'hosting_company', 'status');
        foreach ($tableKeys as $k_no => $key) {
            if ($key !== 'id') {
                $updateKeys[] = sprintf($patternKeys, $key, $key);
            }
        }

        // pre-check if any values:
        if (count($values) > 0) {
            $q = 'INSERT INTO _sitemap_domain_info (' . implode(', ', $tableKeys) . ') VALUES ' . implode(', ', $values) . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateKeys);
            $this->dbo->runQuery($q);
        }

        return true;
    }

    public function doWork()
    {
        $RUN = TRUE;
        while ($RUN == TRUE) {
            /* if there are new projects: */
            $newOnes = $this->newProjects();
            //Standards::debug($newOnes, Standards::DO_EXIT);

            if ($newOnes !== FALSE) {
                foreach ($newOnes as $d_id => $info) {
                    $params = array(
                        'url' => $info['project_url'],
                        'domain_id' => $info['id'],
                    );

                    /* run sub-services */
                    # 'waitable' data:
                    $this->runService('WhoIs', $params);
                    $this->runService('RobotsFile', $params);

                    # 'non-waitable' data:
                    // run crawler:
                }
            }

            # wait for 'waitable' services:
            $this->waitForFinish();

            # save data if any?
            $collected = $this->getDataCollected();
            if (is_array($collected) AND count($collected) > 0) {
                $this->saveCollectedData();

                Standards::debug('saved data:');
                Standards::debug($collected);
            }

            // ...
            Standards::debug('temporary exit!', Standards::DO_EXIT);
            Standards::doPause($this->serviceName, 1);
            $RUN = false;
        }
    }
}