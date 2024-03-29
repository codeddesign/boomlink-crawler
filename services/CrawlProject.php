<?php

/**
 * This class expects as arguments the domain_id which also corresponds to project id
 * It also create as sub-process the ApiData
 */
class CrawlProject extends Service implements ServiceInterface
{
    private $domain_id, $dbo, $project_config, $robots_file, $robots_rules;
    private $userAgentName;

    public function doSets(array $arguments = array('domain_id' => '', 'domain_name' => ''))
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
        /*# todo: remove [tests]
        sleep(10);
        echo 'crawl project - service now should exit!'."\n";
        exit(9);*/

        $this->getProjectRules();

        $not_parsed = $this->getNonParsedLinks();
        $run = true;
        while ($run AND $not_parsed !== false AND count($not_parsed) > 0) {
            $this->workLogic($not_parsed);

            # small pause:
            Standards::doDelay($this->serviceName . '[pid: ' . $this->getPID() . ' | domain_id: ' . $this->domain_id . ']', Config::getDelay('crawl_project_pause'));

            # get more links:
            $not_parsed = $this->getNonParsedLinks();

            /*# todo: remove [tests]
            $run = false;*/
        }

    }

    /**
     * @param $not_parsed
     */
    private function workLogic($not_parsed)
    {
        /* build links list: */
        $links = array();
        $updateIds = array();
        foreach ($not_parsed as $u_no => $info) {
            $links[$u_no] = trim($info['PageURL']);
            $updateIds[] = $info['id'];
        }

        /* curl: */
        $multi = new RequestMulti( $this->userAgentName );
        $multi->addLinks( $not_parsed );
        $multi->send();
        $responses = $multi->getResponse();

        // holder:
        $saveHeadingsText = $saveBodyText = $notCrawlableIds = $redirects = $nextLinks = $save = array();
        foreach ($links as $l_no => $link) {
            $bp = new BodyParse($link, $responses[$l_no]->body, $responses[$l_no]->header, $responses[$l_no]->curlInfo);
            if ($bp->isCrawlAllowed()) {
                # body text only:
                $saveBodyText[$not_parsed[$l_no]['id']] = $bp->getBodyText();
                $saveHeadingsText[$not_parsed[$l_no]['id']] = $bp->getHeadingsText();

                # collected data:
                $save[$l_no] = $bp->getLinkInfo();
                
                # avoid getting lists over the maxDepth;
                $depth = ($not_parsed[$l_no]['depth'] + 1);
                if ($depth <= $this->project_config['maxDepth']) {
                    $temp = $bp->getCrawlableOnes($depth);
                    $nextLinks = array_merge($nextLinks, $temp);

                    /*# todo: remove [tests]
                    echo $link." has ".count($temp)."\n";*/
                }
            } else {
                # save the one which might not allow indexing so it could be removed
                $notCrawlableIds[] = $not_parsed[$l_no]['id'];
            }

            // get redirects:
            $temp = $bp->getRedirects();
            if (count($temp) > 0) {
                foreach ($temp as $temp_link => $temp_info) {
                    // we do not add (+1) to depth!
                    $temp[$temp_link]['depth'] = $not_parsed[$l_no]['depth'];
                }

                $redirects = array_merge($redirects, $temp);
            }
        }

        /*# todo: remove [tests]
        echo "next links after merge: ".count($nextLinks)."\n";*/

        // remove from nextLinks the ones that don't abide robots.txt:
        foreach ($nextLinks as $link => $null) {
            $respects = Standards::respectsRobotsRules($this->robots_rules, $this->project_config['botName'], $link);
            if (!$respects) {
                unset($nextLinks[$link]);
            }
        }

        /*# todo: remove [tests]
        echo "next links after filtering 'robots': ".count($nextLinks)."\n";*/

        /* extra filtering before database interaction: */
        # checkout for duplicates:
        $save_links = array();
        foreach ($save as $s_no => $s_info) {
            $save_links[] = $s_info['PageURL'];
        }
        $save_links = array_count_values($save_links);

        # we are checking if any of the $save data, has links to redirects;
        foreach ($save as $s_no => $s_info) {
            $p_url = $s_info['PageURL'];
            if (isset($redirects[$p_url]) OR $save_links[$p_url] > 1) {
                unset($save[$s_no]);
                $save_links[$p_url] -= 1;
            }
        }

        # 1. remove others:
        $nextLinks = array_diff_key($nextLinks, $redirects);
        $nextLinks = array_diff_key($nextLinks, $save_links);

        /*# todo: remove [tests]
        echo "next links after 'array_diff': ".count($nextLinks)."\n";*/

        $redirects = array_diff_key($redirects, $nextLinks);
        $redirects = array_diff_key($redirects, $save_links);

        # 2. remove possible duplicates:
        $nextLinks = Standards::removePossibleDuplicates($nextLinks);

        /*# todo: remove [tests]
        echo "next links after 'remove possible duplicates': ".count($nextLinks)."\n";*/

        $redirects = Standards::removePossibleDuplicates($redirects);

        if (count($save) > 0) {
            $this->saveLinkInfo($save, $not_parsed);
            $this->saveBodyText($saveBodyText);
            $this->saveHeadingsText($saveHeadingsText);

            # determine if there are any already parsed:
            $nextLinks = $this->filterNotYetParsed($nextLinks);

            /*# todo: remove [tests]
            echo "next links after 'removing the ones from db': ".count($nextLinks)."\n";
            print_r($nextLinks);
            die;*/

            if (count($nextLinks) > 0) {
                $this->saveNextLinks($nextLinks);
            }
        }

        # it's important that this has to be AFTER saveLinkInfo(), otherwise if will get a DB conflict due to duplicated 'PageURL'
        $this->saveRedirects($redirects);

        # remove the ones that their body does not allow indexing:
        $this->removeLinksByIds($notCrawlableIds);

        # do update of the parsed links to next status:
        $this->updateLinksByIds($updateIds);
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
        $exit = Standards::DO_EXIT;

        // robots file:
        $r = $this->dbo->getResults('SELECT * FROM status_domain WHERE DomainURLIDX=' . $this->domain_id);
        if(!count($r) or !is_array($r)) {
            Standards::debug('Project with id ' . $this->domain_id . ' does not exist', $exit);
            return false;
        }

        $this->robots_file = $r[0]['robots_file'];
        $this->robots_rules = Standards::getRobotsRules($this->robots_file);

        // crawler global config:
        $r = $this->dbo->getResults('SELECT * FROM crawler_config');
        if (!count($r) or !is_array($r)) {
            Standards::debug('Crawler config is missing', $exit);
            return false;
        }

        $this->project_config = json_decode($r[0]['config'], true);
        $this->userAgentName = $this->project_config['botName'];

        return true;
    }

    /**
     * @param array $updateIds
     * @return bool
     */
    private function updateLinksByIds(array $linkIds)
    {
        if (count($linkIds) > 0) {
            $pattern = 'UPDATE page_main_info SET parsed_status=%d WHERE id IN (%s)';
            $q = sprintf($pattern, Config::NEW_STATUS, implode(',', $linkIds));

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
        foreach ($saveData as $l_no => $links_info) {
            if (!$once) {
                $tableKeys['id'] = 0;

                $i = 1;
                foreach ($links_info as $key => $value) {
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
        foreach ($saveData as $l_no => $links_info) {
            foreach ($links_info as $key => $value) {
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
            $keeper[implode('', Standards::getHostAndPathOnly($link))][] = $link;
        }

        foreach ($all as $a_no => $link) {
            $all[$a_no] = '\'' . $link . '\'';
        }

        $pattern = 'SELECT id, PageURL FROM page_main_info WHERE PageURL IN (%s)';
        $q = sprintf($pattern, implode(',', $all));
        $inDB = $this->dbo->getResults($q);

        /*echo '#db:';
        Standards::debug($inDB);*/

        if ($inDB == false) {
            return $nextLinks;
        } else {
            foreach ($inDB as $i_no => $info) {
                $db_link = implode('', Standards::getHostAndPathOnly($info['PageURL']));
                if (isset($keeper[$db_link])) {
                    foreach ($keeper[$db_link] as $k_no => $k_link) {
                        unset($nextLinks[$k_link]);
                    }
                }
            }
        }

        /*echo '#result:';
        Standards::debug($nextLinks);*/

        return $nextLinks;
    }

    /**
     * @param array $linkIds
     * @return bool
     */
    private function removeLinksByIds(array $linkIds)
    {
        if (count($linkIds) > 0) {
            $pattern = 'DELETE FROM page_main_info WHERE id IN (%s)';
            $q = sprintf($pattern, Config::NEW_STATUS, implode(',', $linkIds));

            return $this->dbo->runQuery($q);
        }

        return false;
    }

    /**
     * @param array $saveBodyText
     * @return mixed
     */
    private function saveBodyText(array $saveBodyText)
    {
        if (!is_array($saveBodyText) OR count($saveBodyText) == 0) {
            return false;
        }

        # prepare text:
        $values = array();
        foreach ($saveBodyText as $link_id => $text) {
            $values[] = '(' . $link_id . ', \'' . addslashes($text) . '\')';
        }

        $q = 'INSERT INTO page_main_info_body (page_id, body) VALUES ' . implode(',', $values);

        return $this->dbo->runQuery($q);
    }

    /**
     * @param $saveHeadingsText
     * @return bool
     */
    private function saveHeadingsText($saveHeadingsText)
    {
        if (!is_array($saveHeadingsText) OR count($saveHeadingsText) == 0) {
            return false;
        }

        $value_pattern = '(%d, \'%s\')';
        $values = array();
        foreach ($saveHeadingsText as $link_id => $headings) {
            $values[] = sprintf($value_pattern, $link_id, addslashes(Standards::json_encode_special($headings)));
        }

        if(count($values)) {
            $q = 'INSERT INTO page_main_info_headings (page_id, heading_text) VALUES ' . implode(', ', $values);
            $this->dbo->runQuery($q);
        }

        return false;
    }
}