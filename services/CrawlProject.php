<?php

/**
 * This class expects as arguments the domain_id which also corresponds to project id
 * It also create as sub-process the ApiData
 */
class CrawlProject extends Service
{
    private $domain_id, $dbo, $project_config, $robots_file;
    CONST NEW_STATUS = 1, CURRENT_STATUS = 0;

    public function doSets($arguments = array('domain_id'))
    {
        // default:
        $this->project_config = array();
        $this->robots_file = '';

        // other sets:
        $this->domain_id = $arguments['domain_id'];
        $this->dbo = new MySQL();


    }

    public function doWork()
    {
        if (!$this->getProjectRules()) {
            Standards::debug('Project with id ' . $this->domain_id . ' does not exist', Standards::DO_EXIT);
        }

        $un_parsed = $this->getNonParsedLinks();
        while (count($un_parsed) > 0) {

            // init curl:
            $curl = new Curl();

            // build links list:
            $links = array();
            foreach ($un_parsed as $u_no => $info) {
                $links[] = trim($info['page_url']);
            }
            $curl->addLinks($links);

            // run curl:
            $curl->run();

            # rain-check after work:
            $un_parsed = $this->getNonParsedLinks();

            # tests:
            $un_parsed = 0;
        }

    }

    /**
     * @return bool|array
     */
    private function getNonParsedLinks()
    {
        $q = 'SELECT * FROM _sitemap_links WHERE parsed_status=\'' . self::CURRENT_STATUS . '\' AND domain_id=\'' . $this->domain_id . '\' LIMIT 10';
        return $this->dbo->getResults($q);
    }

    /**
     * @return bool
     */
    private function getProjectRules()
    {
        $q = 'SELECT config, robots_file FROM _sitemap_domain_info WHERE id=\'' . $this->domain_id . '\'';
        $r = $this->dbo->getResults($q);
        if (count($r) > 0) {
            print_r($r[0]);
            exit;
            $this->project_config = json_decode($r[0]['config']);
            $this->robots_file = $r[0]['robots_file'];

            return true;
        }

        return false;
    }
}