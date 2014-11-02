<?php

/*
 * * This service workflow *
 * 1. It waits for new projects to come up
 * If there are new projects it will create some sub-processes to:
 * - get robots.txt file and domain information - single time here
 * - it will create a single instance of CrawlProject (if not already running which is determine by the status value from table)
 *
 * 2. It creates a sub-process of ProxyData - that gets the links from db and adds more info to them
 * */

class ProjectListener extends Service
{
    private $dbo, $crawlingDomains, $allProjects;

    /**
     * do some sets:
     */
    public function doSets()
    {
        // init db:
        $this->dbo = new MySQL();
        $this->crawlingDomains = array();

        Standards::debug(__CLASS__ . ' (parent thread) is: ' . $this->getPID());
    }

    /**
     * @return array|bool
     */
    private function getAllProjects()
    {
        $q = 'SELECT * FROM _sitemap_domain_info';
        $this->allProjects = $this->dbo->getResults($q);
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
            $pattern = 'INSERT INTO _sitemap_domain_info (%s) VALUES %s ON DUPLICATE KEY UPDATE %s';
            $q = sprintf($pattern, implode(',', $tableKeys), implode(',', $values), implode(',', $updateKeys));
            $this->dbo->runQuery($q);
        }

        return true;
    }

    /**
     * @return array|bool
     */
    private function areNewProjects()
    {
        $newOnes = false;

        if (!is_array($this->allProjects)) {
            return $newOnes;
        }

        foreach ($this->allProjects as $c_no => $info) {
            if ($info['status'] == 0 OR !isset($this->crawlingDomains[$info['domain_name']])) {
                $newOnes[] = $info;
            }
        }

        return $newOnes;
    }

    public function doWork()
    {
        # RUN: parallel sub-service ProxyData:
        $this->runService('ProxyData', array());
        $this->runService('PhantomData', array());

        // rest of logic:
        $RUN = TRUE;
        while ($RUN == TRUE) {
            /* if there are new projects: */
            $this->getAllProjects();
            $toCrawl = $this->areNewProjects();

            //Standards::debug($newOnes, Standards::DO_EXIT);

            if ($toCrawl !== FALSE) {
                foreach ($toCrawl as $d_id => $info) {
                    $params = array(
                        'url' => $info['project_url'],
                        'domain_id' => $info['id'],
                    );

                    /* run sub-services */
                    # 'waitable' data:
                    if ($info['server_ip'] == '') {
                        $this->runService('WhoIs', $params);
                        $this->runService('RobotsFile', $params);
                    }

                    # 'non-waitable' data:
                    $this->runService('CrawlProject', $params);
                    $this->runService('ApiData', $params);

                    # keep track of the 'crawled' domain:
                    $this->crawlingDomains[$info['domain_name']] = '';
                }

                # wait for 'waitable' services:
                $this->waitForFinish();

                # save data if any:
                $this->getDataCollected();
                $this->saveCollectedData();
            } else {
                Standards::debug('no new projects. no work to do?!');
            }

            // ...
            Standards::debug('temporary exit!', Standards::DO_EXIT);
            Standards::doPause('pause: ' . $this->serviceName, 1);
            //$RUN = false;
        }
    }
}