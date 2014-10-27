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
                # create NEW sub-process to get whois data:
                $this->runService('WhoIs', array('url' => 'codeddesign.org', 'domain_id' => '123'));
                $this->runService('whois', array('url' => 'sat1.de', 'domain_id' => '123'));

                # create NEW sub-process to start crawling:
                // ..

                # wait for 'waitable' services:
                $this->waitForFinish();
                $newOne = false;

                echo 'data collected:';
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
    protected function about24HoursPassed()
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