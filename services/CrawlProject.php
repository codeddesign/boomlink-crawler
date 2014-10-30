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
            $updateIds = array();
            foreach ($un_parsed as $u_no => $info) {
                $links[$u_no] = trim($info['page_url']);
                $updateIds[] = $info['id'];
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
                        /*'links' => array(
                            'internal' => $bp->getSpecificLinks('internal'),
                            'external' => $bp->getSpecificLinks('external'),
                        ),*/
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

            if (count($save) > 0) {
                $this->saveLinkInfo($save, $un_parsed);

                if (count($nextLinks) > 0) {
                    $this->saveNextLinks($nextLinks);
                }
            }

            # do update of the parsed links to next status:
            $this->updateLinksByIds($updateIds);

            # do pause:
            Standards::doDelay(rand(100, 300));

            # rain-check after work:
            $un_parsed = $this->getNonParsedLinks();
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

    /**
     * @param array $updateIds
     * @return bool
     */
    private function updateLinksByIds(array $updateIds)
    {
        if (count($updateIds) > 0) {
            $q = 'UPDATE _sitemap_links SET parsed_status=\'' . static::NEW_STATUS . '\' WHERE id IN (' . implode(',', $updateIds) . ')';
            return $this->dbo->runQuery($q);
        }

        return false;
    }

    /**
     * @param array $saveData
     * @param $current_links
     * @return mixed
     */
    private function saveLinkInfo(array $saveData, $current_links)
    {
        // build up table keys:
        $once = false;
        $tableKeys = array();
        foreach ($saveData as $l_no => $info) {
            if (!$once) {
                $tableKeys['link_id'] = 0;

                $i = 1;
                foreach ($info['links_info'] as $key => $value) {
                    if (!isset($tableKeys[$key])) {
                        $tableKeys[$key] = $i;
                        $i++;
                    }
                }
                $once = true;
            }
        }
        $tableKeys = array_flip($tableKeys);

        // create values:
        $values = array();
        $i = 0;
        foreach ($saveData as $l_no => $info) {
            foreach ($info['links_info'] as $key => $value) {
                if (!isset($values[$i])) {
                    $values[$i] = '(' . $current_links[$l_no]['id'] . ', ';
                }

                $values[$i] .= sprintf('\'%s\', ', addslashes($value));
            }

            $values[$i] = substr($values[$i], 0, strrpos($values[$i], ',')) . ')';
            $i++;
        }

        $q = 'INSERT INTO _sitemap_links_info (' . implode(', ', $tableKeys) . ') VALUES ' . implode(',', $values);
        return $this->dbo->runQuery($q);
    }

    private function saveNextLinks(array $save)
    {
        $values = array();
        foreach ($save as $link => $info) {
            $values[] = '(' . $this->domain_id . ', \'' . $link . '\', \'' . $info['depth'] . '\', \'' . addslashes($info['href']) . '\')';
        }

        $q = 'INSERT INTO _sitemap_links (domain_id, page_url, depth, href) VALUES ' . implode(', ', $values);
        return $this->dbo->runQuery($q);
    }
}