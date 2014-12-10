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

class ProjectListener extends Service implements ServiceInterface
{
    private $dbo, $allProjects;

    /**
     * @param array $arguments
     */
    public function doSets(array $arguments = array())
    {
        // init db:
        $this->dbo = new MySQL();

        Standards::debug(__CLASS__ . ' (parent thread) is: ' . $this->getPID());
    }

    public function doWork()
    {
        # RUN: parallel sub-service ProxyData:
        $this->runService('ProxyData', array());
        $this->runService('PhantomData', array());
        $this->runService('CompletedListener', array());

        // rest of logic:
        $RUN = TRUE;
        while ($RUN == TRUE) {
            /* if there are new projects: */
            $this->getAllProjects();
            $toCrawl = $this->areNewProjects();

            if ($toCrawl !== FALSE) {
                foreach ($toCrawl as $d_id => $info) {
                    $params = array(
                        'url' => $info['DomainURL'],
                        'domain_id' => $info['DomainURLIDX'],
                        'domain_name' => $info['domain_name'],
                    );

                    /* run sub-services */
                    if ($info['server_ip'] == '') {
                        $this->runService('DomainData', $params);
                    }

                    # .. :
                    $this->runService('CrawlProject', $params);

                    # keep track of the 'crawled' domain:
                    $this->crawlingDomains[$info['domain_name']] = '';

                    # run api data:
                    $this->runService('ApiData', $params);
                }

                # wait for 'waitable' services:
                $this->waitForFinish();
            }

            // pause:
            Standards::doDelay($this->serviceName . '[pid-parent: ' . $this->getPID() . ']', Config::getDelay('project_listener_pause'));

            # $RUN = false;
        }
    }

    /**
     * @return array|bool
     */
    private function getAllProjects()
    {
        $q = 'SELECT * FROM status_domain';
        $this->allProjects = $this->dbo->getResults($q);
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
            if (!isset($this->crawlingDomains[$info['domain_name']])) {
                $newOnes[] = $info;
            }
        }

        return $newOnes;
    }
}