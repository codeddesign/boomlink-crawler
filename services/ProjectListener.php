<?php

class ProjectListener extends Service
{
    private $startTime;

    public function getNewProjects()
    {
        $i = 0;
        $newOne = true;
        while ($i++ < 5) {
            /* if there are new projects: */
            if ($newOne) {
                # get WhoIs:
//                $this->runService('WhoIs', array('url' => 'codeddesign.org', 'domain_id' => '123'));
//                $this->runService('whois', array('url' => 'prosieben.de', 'domain_id' => '1234'));

                # get RobotsFile:
//                $this->runService('RobotsFile', array('url' => 'http://www.prosieben.de', 'domain_id' => '333'));
//                $this->runService('RobotsFile', array('url' => 'http://www.codeddesign.org', 'domain_id' => '303'));

                # get Social:
                $this->runService('ExternalData', array('url' => 'http://www.sat1gold.de/tv/', 'domain_id' => '1', 'link_id' => '123'));
                $this->runService('ExternalData', array('url' => 'http://www.codeddesign.org', 'domain_id' => '2', 'link_id' => '124'));
                $this->runService('ExternalData', array('url' => 'http://www.protv.ro', 'domain_id' => '3', 'link_id' => '125'));
                $this->runService('ExternalData', array('url' => 'http://www.trafic.ro', 'domain_id' => '4', 'link_id' => '126'));
                $this->runService('ExternalData', array('url' => 'http://www.sat1.de', 'domain_id' => '5', 'link_id' => '127'));

                # get GooglePageRank:

                # wait for 'waitable' services:
                $this->waitForFinish();
                $newOne = false;

                echo "data collected:\n";
                $this->debug($this->getDataCollected());
                $this->debug('temporary exit!', static::DO_EXIT);
            }

            // ...
            $this->doPause();
        }
    }

    /**
     * do some sets:
     */
    public function makeSets()
    {
        $this->startTime = time();
        $this->debug(__CLASS__ . ' (parent thread) is: ' . $this->getPID());
    }

    /**
     * @return bool
     */
    private function about24HoursPassed()
    {
        $currentTime = time();
        $_24h = 60 * 60 * 24;

        $result = ($this->startTime + $_24h) > $currentTime;
        if ($result) {
            // reset startTime:
            $this->startTime = time();
        }

        return $result;
    }
}