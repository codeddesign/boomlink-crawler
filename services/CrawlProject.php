<?php

/**
 * This class expects as arguments the domain_id which also corresponds to project id
 * It also create as sub-process the ApiData
 */
class CrawlProject extends Service
{
    private $domain_id, $dbo, $project_config, $robots_file, $robots_rules;

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
                $links[$u_no] = trim($info['PageURL']);
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
            $redirects = $nextLinks = $save = array();
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

                    # avoid getting lists over the maxDepth;
                    $depth = ($un_parsed[$l_no]['depth'] + 1);
                    if ($depth <= $this->project_config['maxDepth']) {
                        $nextLinks = $bp->getCrawlableOnes($depth);
                    }
                }

                // get redirects:
                $temp = $bp->getRedirects();
                if (count($temp) > 0) {
                    foreach ($temp as $temp_link => $temp_info) {
                        // we do not add (+1) to depth!
                        $temp[$temp_link]['depth'] = $un_parsed[$l_no]['depth'];
                    }

                    $redirects = array_merge($redirects, $temp);
                }
            }

            // remove from nextLinks the ones that don't abide robots.txt:
            foreach ($nextLinks as $link => $null) {
                $respects = Standards::respectsRobotsRules($this->robots_rules, $this->project_config['botName'], $link);
                if (!$respects) {
                    unset($nextLinks[$link]);
                }
            }

            /* extra filtering before database interaction: */
            $flipped_links = array_flip($links);
            foreach ($flipped_links as $link => $l_no) {
                if (isset($redirects[$link])) {
                    // technically here we are removing the saved data of the ones which might be redirected to one of the links from the current set of X# links
                    unset($save[$l_no]);
                }
            }

            if (count($save) > 0) {
                $this->saveLinkInfo($save, $un_parsed);

                # it's important that this has to be AFTER saveLinkInfo(), otherwise if will get a DB conflict due to duplicated 'PageURL'
                $this->saveRedirects($redirects);

                // determine if there are any already parsed:
                $nextLinks = $this->filterNotYetParsed($nextLinks);
                if (count($nextLinks) > 0) {
                    $this->saveNextLinks($nextLinks);
                }
            }

            # do update of the parsed links to next status:
            $this->updateLinksByIds($updateIds);

            # do pause:
            Standards::doDelay($this->serviceName, rand(100, 300));

            # rain-check after work:
            $un_parsed = $this->getNonParsedLinks();
        }

    }

    /**
     * @param array $redirects
     * @return mixed
     */
    private function saveRedirects(array $redirects)
    {
        if (count($redirects) == 0) {
            return false;
        }

        // prepare values:
        $values = array();
        foreach ($redirects as $link => $link_info) {
            $values[] = '(\'' . $link . '\', \'' . $this->domain_id . '\', \'' . $link_info['depth'] . '\', \'' . $link_info['http_code'] . '\', 1, 1, 1, 1, 1)';
        }

        // prepare duplicate:
        $tableKeys = array('PageURL', 'DomainURLIDX', 'depth', 'http_code', 'is_301', 'parsed_status', 'api_data_status', 'proxy_data_status', 'phantom_data_status');
        $updateKeys = array();
        foreach ($tableKeys as $k_no => $key) {
            if ($key !== 'PageURL') {
                $updateKeys[] = sprintf('%s=VALUES(%s)', $key, $key);
            }
        }

        $pattern = 'INSERT INTO page_main_info (%s) VALUES %s ON DUPLICATE KEY UPDATE %s';
        $q = sprintf($pattern, implode(',', $tableKeys), implode(',', $values), implode(',', $updateKeys));
        return $this->dbo->runQuery($q);
    }

    /**
     * @return bool|array
     */
    private function getNonParsedLinks()
    {
        $pattern = 'SELECT * FROM page_main_info WHERE parsed_status=%d AND depth <= %d AND DomainURLIDX=%d LIMIT %d';
        $q = sprintf($pattern, Config::CURRENT_STATUS, $this->project_config['maxDepth'], $this->domain_id, $this->project_config['atOnce']);
        return $this->dbo->getResults($q);
    }

    /**
     * @return bool
     */
    private function getProjectRules()
    {
        $pattern = 'SELECT config, robots_file FROM status_domain WHERE DomainURLIDX=%d';
        $q = sprintf($pattern, $this->domain_id);
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
            $pattern = 'UPDATE page_main_info SET parsed_status=%d WHERE id IN (%s)';
            $q = sprintf($pattern, Config::NEW_STATUS, implode(',', $updateIds));
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
                $tableKeys['id'] = 0;

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

        // prepare update keys:
        $updateKeys = array();
        foreach ($tableKeys as $k_no => $key) {
            if ($key !== 'id') {
                $updateKeys[] = sprintf('%s=VALUES(%s)', $key, $key);
            }
        }

        $pattern = 'INSERT INTO page_main_info (%s) VALUES %s ON DUPLICATE KEY UPDATE %s';
        $q = sprintf($pattern, implode(',', $tableKeys), implode(',', $values), implode(',', $updateKeys));
        return $this->dbo->runQuery($q);
    }

    /**
     * @param array $nextLinks
     * @return mixed
     */
    private function saveNextLinks(array $nextLinks)
    {
        $values = array();
        $tableKeys = array('DomainURLIDX', 'PageURL', 'depth', 'href');
        foreach ($nextLinks as $link => $info) {
            $values[] = '(' . $this->domain_id . ', \'' . $link . '\', \'' . $info['depth'] . '\', \'' . addslashes($info['href']) . '\')';
        }

        $pattern = 'INSERT INTO page_main_info (%s) VALUES %s';
        $q = sprintf($pattern, implode(',', $tableKeys), implode(',', $values));
        return $this->dbo->runQuery($q);
    }


    /**
     * @param array $nextLinks
     * @return array
     */
    private function filterNotYetParsed(array $nextLinks)
    {
        if (count($nextLinks) == 0) {
            return $nextLinks;
        }

        $all = array();
        foreach ($nextLinks as $link => $null) {
            $all = array_merge($all, Standards::generatePossibleLinks($link));
        }

        foreach ($all as $a_no => $link) {
            $all[$a_no] = '\'' . $link . '\'';
        }

        $pattern = 'SELECT id, PageURL FROM page_main_info WHERE PageURL IN (%s)';
        $q = sprintf($pattern, implode(',', $all));
        $r = $this->dbo->getResults($q);

        if ($r == FALSE) {
            return $nextLinks;
        } else {
            foreach ($r as $r_no => $info) {
                if (isset($nextLinks[$info['PageURL']])) {
                    unset($nextLinks[$info['PageURL']]);
                }
            }
        }

        return $nextLinks;
    }
}