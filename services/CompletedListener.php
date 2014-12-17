<?php

class CompletedListener extends Service implements ServiceInterface
{

    protected $dbo, $domains;

    public function doSets(array $arguments = array())
    {
        $this->dbo = new MySQL();
    }

    public function doWork()
    {
        $run = true;
        while ($run) {
            $algorithms = $this->getAlgorithms();
            $this->domains = $this->getDomainsAge();

            if (is_array($algorithms) AND count($algorithms) AND count($this->domains)) {
                foreach ($algorithms as $a_no => $algorithm) {
                    $linksStats = array();
                    $completedLinks = $this->getCompletedLinks($algorithm['id']);

                    if (is_array($completedLinks)) {
                        foreach ($completedLinks as $c_no => $completedLink) {
                            $linksStats[] = $this->getLinkStats($algorithm, $completedLink);
                        }

                        if (count($linksStats)) {
                            $this->saveData($linksStats);
                            $this->updateCompletedAlgos($completedLinks, $algorithm['id']);
                        }
                    }

                    Standards::doDelay($this->serviceName . '[delay]', Config::getDelay('completed_listener_delay'));
                }
            } else {
                Standards::doDelay($this->serviceName . '[pause]', Config::getDelay('completed_listener_pause'));
            }

            #$run = false;
        }
    }

    /**
     * @param $algorithm_id
     * @return mixed
     */
    private function getCompletedLinks($algorithm_id)
    {
        $q = 'SELECT * FROM page_main_info WHERE parsed_status=1 AND api_data_status=1 AND proxy_data_status=1 AND sentimental_positive IS NOT NULL AND completed_algos NOT LIKE "%\"' . $algorithm_id . '\"%" LIMIT ' . Config::getQueryLimit('completed_listener');
        return $this->dbo->getResults($q);
    }

    /**
     * @return mixed
     */
    private function getAlgorithms()
    {
        return $this->dbo->getResults('SELECT * FROM algorithms');
    }

    /**
     * @return mixed
     */
    private function getDomainsAge()
    {
        $r = $this->dbo->getResults('SELECT * FROM status_domain');
        if (count($r) === 0) {
            return $r;
        }

        return Standards::getDomainsAge($r);
    }

    /**
     * @param $algorithm
     * @param $cl
     * @return array
     */
    private function getLinkStats($algorithm, $cl)
    {
        // pre-sets / logic:
        $page_rank = (trim($cl['google_rank']) == 'n/a') ? 0 : $cl['google_rank'];

        // ..
        $points = 0;
        $algorithm_config = json_decode($algorithm['config'], 1);
        foreach ($algorithm_config as $type => $p) {
            switch ($type) {
                case 'incoming':
                    $back_links = ($cl['total_back_links'] == '') ? 0 : ($cl['total_back_links']);
                    if ($back_links > 0) {
                        $points += $p * $back_links;
                    }
                    break;
                case 'outgoing':
                    $links = $cl['follow_links'] + $cl['no_follow_links'];
                    if ($links > 0) {
                        $points += $p * $links;
                    }
                    break;
                case 'age_1':
                    if ($this->domains[$cl['DomainURLIDX']] < 1) {
                        $points += $p;
                    }
                    break;
                case 'age_3':
                    if ($this->domains[$cl['DomainURLIDX']] < 1) {
                        $points += $p;
                    }
                    break;
                case 'share':
                    $shares = $cl['fb_shares'] + $cl['fb_comments'] + $cl['fb_likes'] + $cl['tweeter'] + $cl['google_plus'];
                    if ($shares > 0) {
                        $points += $p * $shares;
                    }
                    break;
                case 'pagerank_0':
                    if ($page_rank == 0) {
                        $points += $p;
                    }
                    break;
                case 'pagerank_13':
                    if ($page_rank >= 1 AND $page_rank <= 3) {
                        $points += $p;
                    }
                    break;
                case 'pagerank_46':
                    if ($page_rank >= 4 AND $page_rank <= 6) {
                        $points += $p;
                    }
                    break;
                case 'pagerank_710':
                    if ($page_rank >= 7 AND $page_rank <= 10) {
                        $points += $p;
                    }
                    break;
            }
        }

        return array(
            'page_id' => $cl['id'],
            'algo_id' => $algorithm['id'],
            'points' => $points,
        );
    }

    /**
     * @param array $linksStats
     * @return bool|mixed
     */
    private function saveData(array $linksStats)
    {
        $rows = array();
        $row_pattern = '(\'%s\', \'%s\', \'%s\')';
        foreach ($linksStats as $l_no => $info) {
            $rows[] = sprintf($row_pattern, $info['page_id'], $info['algo_id'], $info['points']);
        }

        if (count($rows)) {
            $q = sprintf('INSERT INTO page_main_info_points (page_id, algo_id, points) VALUES %s', implode(', ', $rows));
            return $this->dbo->runQuery($q);
        }

        return false;
    }

    /**
     * @param array $completed_links
     * @param $algorithm_id
     * @return mixed
     */
    private function updateCompletedAlgos(array $completed_links, $algorithm_id)
    {
        $completed_algos = array();
        foreach ($completed_links as $c_no => $info) {
            $current_algos = (strlen(trim($info['completed_algos'])) == 0) ? array() : json_decode($info['completed_algos'], 1);
            $current_algos[] = $algorithm_id;

            $completed_algos[$info['id']] = $current_algos;
        }

        if (!count($completed_algos)) {
            return false;
        }

        $row_pattern = '(%d, \'%s\')';
        $rows = array();
        foreach ($completed_algos as $link_id => $new_algo) {
            $rows[] = sprintf($row_pattern, $link_id, Standards::json_encode_force_string($new_algo));
        }

        $query_pattern = 'INSERT INTO page_main_info (id, completed_algos) VALUES %s ON DUPLICATE KEY UPDATE completed_algos=VALUES(completed_algos)';
        $q = sprintf($query_pattern, implode(',', $rows));

        return $this->dbo->runQuery($q);
    }
}