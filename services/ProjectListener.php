<?php

class ProjectListener extends Service
{
    /**
     * do some sets:
     */
    public function doSets()
    {
        Standards::debug(__CLASS__ . ' (parent thread) is: ' . $this->getPID());
    }

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

                # get External data (google&bing indexed, social data (fb, tweeter, google+), google rank):
//                $this->runService('ProxyData', array('url' => 'http://www.sat1gold.de/tv/', 'domain_id' => '1', 'link_id' => '123'));
//                $this->runService('ProxyData', array('url' => 'http://www.codeddesign.org', 'domain_id' => '2', 'link_id' => '124'));
//                $this->runService('ProxyData', array('url' => 'http://www.sat1.de', 'domain_id' => '5', 'link_id' => '127'));

                # get ApiData:
//                $this->runService('ApiData', array(
//                        'domain_id' => '5',
//                        'urls' => array(
//                            array('url' => 'http://www.sat1gold.de/tv/', 'link_id' => '123'),
//                            array('url' => 'http://www.codeddesign.org', 'link_id' => '124'),
//                            array('url' => 'http://www.sat1.de', 'link_id' => '127'),
//                        )
//                    )
//                );

                # get page's duration to load in seconds AND weight in kb:
                $this->runService('DurationSizeData', array('url' => 'http://prosieben.de'));

                # wait for 'waitable' services:
                $this->waitForFinish();
                $newOne = false;

                echo "data collected:\n";
                Standards::debug($this->getDataCollected());
                Standards::debug('temporary exit!', Standards::DO_EXIT);
            }

            // ...
            Standards::doPause($this->serviceName, 5);
        }
    }
}