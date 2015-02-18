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
    private $dbo;
    private $projects = array();

    /**
     * @param array $arguments
     */
    public function doSets( array $arguments = array() )
    {
        // init db:
        $this->dbo = new MySQL();

        Standards::debug( __CLASS__ . ' (parent thread) is: ' . $this->getPID() );
    }

    public function doWork()
    {
        $RUN = true;
        while ($RUN == true) {
            # get projects:
            $this->getAllProjects();

            if (is_array($this->projects)) {
                $this->handleParallelServices();

                # determine what to run:
                foreach ($this->projects as $p_no => $project) {
                    $params = array(
                        'url'         => $project['DomainURL'],
                        'domain_id'   => $project['DomainURLIDX'],
                        'domain_name' => $project['domain_name'],
                    );

                    $this->handleProjectServices( $project, $params );
                }
            }

            # check for closed project (to avoid defunct processes) & handle data:
            if (count( $this->PIDs )) {
                $this->checkForClosed();
            }

            # pause:
            $this->listenerPause();
        }
    }

    /**
     * @return array|bool
     */
    private function getAllProjects()
    {
        $this->projects = $this->dbo->getResults( 'SELECT * FROM status_domain' );
    }

    private function listenerPause()
    {
        Standards::doDelay( $this->serviceName . '[pid-parent: ' . $this->getPID() . ']', 3 /*Config::getDelay('project_listener_pause')*/ );
    }

    /**
     * Take care of running the services that a project needs to run.
     * Info and logic:
     * - if the 'server_ip' is empty means that we need to run first the DomainData service.
     *   It's purpose is to act like a checkpoint, because that service also gets the robots.txt so this means
     *   that it ran and the rest of the services can be created.
     * - It will ran 2 services for each project that is created (CrawlProject and ApiData)
     *
     * @param $project
     * @param $params
     *
     * @return bool
     */
    private function handleProjectServices( $project, $params )
    {
        $domainName = strtolower( $params['domain_name'] );
        $domainData = trim( $project['server_ip'] );

        # check if domain's data is ready:
        if ( ! $domainData) {
            $this->runService( 'DomainData', $params );
            return '';
        }

        # loop through services and check if they are running yet:
        $projectServices = array(
            'CrawlProject',
            'ApiData',
        );

        foreach ($projectServices as $s_no => $service) {
            $lowerName = strtolower( $service );

            if ( ! isset( $this->PIDs[$lowerName][$domainName] )) {
                $this->runService( $service, $params );
            }
        }

        return '';
    }


    /**
     * Takes care of the parallel running services.
     * Info:
     * - It will ran only ONE instance for each service listed in $parallelService
     * - if you want to disable one while doing tests, you can just comment the line
     *
     */
    private function handleParallelServices()
    {
        $defaultDomain = Config::getDefaultDomain();

        $parallelServices = array(
            'ProxyData',
            'PhantomData',
        );

        foreach ($parallelServices as $s_no => $service) {
            $lowerName = strtolower( $service );

            if ( ! isset( $this->PIDs[$lowerName][$defaultDomain] )) {
                $this->runService( $service, array() );
            }
        }
    }
}