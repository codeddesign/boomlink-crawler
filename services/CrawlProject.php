<?php

/**
 * This class expects as arguments the domain_id which also corresponds to project id
 * It also create as sub-process the ApiData
 */
class CrawlProject extends Service
{
    private $domain_id, $dbo, $project_config, $robots_file, $robots_rules;
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
        while ($un_parsed !== false AND count($un_parsed) > 0) {
            // init curl:
            $curl = new Curl();

            // build links list:
            $links = array();
            foreach ($un_parsed as $u_no => $info) {
                $links[$u_no] = trim($info['page_url']);
            }

            $curl->addLinks($links);

            // run curl:
            $curl->run();

            # body parse:
            $curlInfoS = $curl->getLinkCurlInfo();
            $bodyS = $curl->getBodyOnly();
            $headerS = $curl->getHeaderOnly();

            // holder:
            $nextLinks = $save = array();
            foreach ($links as $l_no => $link) {
                $bp = new BodyParse($link, $bodyS[$l_no], $headerS[$l_no], $curlInfoS[$l_no]);
                if ($bp->isCrawlAllowed()) {
                    $save[$l_no] = array(
                        'links_info' => $bp->getLinkInfo(),
                        'links' => array(
                            'internal' => $bp->getSpecificLinks('internal'),
                            'external' => $bp->getSpecificLinks('external'),
                        ),
                    );

                    $nextLinks = $bp->getCrawlableOnes(($l_no + 1));
                }
            }

            // remove from nextLinks the ones that don't abide robots.txt:
            foreach ($nextLinks as $link => $null) {
                $respects = Standards::respectsRobotsRules($this->robots_rules, $this->project_config['botName'], $link);
                if (!$respects) {
                    unset($nextLinks[$link]);
                }
            }

            if(count($save) > 0) {

            }
            print_r($save);
            print_r($nextLinks);
            exit;
            # do pause:
            Standards::doPause($this->serviceName, 2);

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
        $q = 'SELECT * FROM _sitemap_links WHERE parsed_status=\'' . self::CURRENT_STATUS . '\' AND depth <=' . $this->project_config['maxDepth'] . ' AND domain_id=\'' . $this->domain_id . '\' LIMIT ' . $this->project_config['atOnce'];
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
            $this->project_config = json_decode($r[0]['config'], TRUE);
            $this->robots_file = $r[0]['robots_file'];
            $this->robots_rules = Standards::getRobotsRules($this->robots_file);
            return true;
        }

        return false;
    }
}