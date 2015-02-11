<?php

class Service
{
    protected $serviceName, $servicePID, $servicesAvailable, $servicesWaitable, $crawlingDomains;
    protected $dataCollected = array();

    /*
     * holds the current running 'sub-services'
     * Info: check saveToRunning() for format
     * */
    protected $PIDs = array();

    function __construct(array $servicesAvailable = array())
    {
        // keeps available services:
        $this->servicesAvailable = $servicesAvailable;
        $this->servicesWaitable = $this->getServicesWaitable($servicesAvailable);

        // service sets:
        $this->servicePID = $this->getPID();
        $this->serviceName = strtolower(get_class($this));
    }

    /**
     * @return int
     */
    public function getPID()
    {
        if (!function_exists('posix_getpid')) {
            Standards::debug('posix_getpid(): does not exist.');

            return false;
        }

        return posix_getpid();
    }

    /**
     * @return string
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    /**
     * @param $servicesAvailable
     * @return array
     */
    protected function getServicesWaitable($servicesAvailable)
    {
        $waitable = array();
        foreach ($servicesAvailable as $s_no => $service) {
            if (isset($service['wait']) AND $service['wait']) {
                $waitable[strtolower($service['class'])] = true;
            }
        }

        return $waitable;
    }

    /**
     * @param null $pid
     */
    private function threadKill($pid = null)
    {
        if (!function_exists('posix_kill')) {
            Standards::debug('posix_kill(): does not exist', Standards::DO_EXIT);
        }

        if ($pid == null) {
            $pid = $this->getPID();
        }

        posix_kill($pid, 9);
    }

    /**
     * @return int
     */
    private function threadFork()
    {
        if (!function_exists('pcntl_fork')) {
            Standards::debug('pcntl_fork(): does not exist', Standards::DO_EXIT);
        }

        return pcntl_fork();
    }

    /**
     * @param $callback
     * @param $callbackArgs
     */
    private function threadCreate($callback, $callbackArgs)
    {
        if ($this->servicePID == $this->getPID()) {
            # few need pre-sets:
            $temp_DomainName = (isset($callbackArgs['domain_name'])) ? $callbackArgs['domain_name'] : 'no-domain';
            $temp_ServiceName = strtolower($callback);

            # ..
            $childPid = $this->threadFork();
            $this->saveToRunning( $childPid, $temp_ServiceName, $temp_DomainName);

            if (!$childPid) {
                Standards::debug('created thread with pid:' . $this->getPID() . ' [' . $temp_DomainName . ' > ' . $temp_ServiceName . ']');

                /* child action: */
                if (class_exists($callback)) {
                    # add parent's pid to arguments:
                    $callbackArgs += array('parentPID' => $this->servicePID);

                    # actual action:
                    $obj = $this->callService($callback, $callbackArgs);
                    $collected = $obj->getDataCollected();

                    # save data:
                    if ($this->isDataToSave($collected)) {
                        $this->memoryWrite($this->getPID(), $collected);
                    }
                } else {
                    Standards::debug(__METHOD__ . ': Class/Function \'' . $callback . '\' does not exist.', Standards::DO_EXIT);
                }

                /* needed exit: */
                $this->threadKill();
            }
        }
    }

    /**
     * @param $className
     * @param $arguments
     */
    protected function runService($className, array $arguments = array())
    {
        $found = false;
        foreach ($this->servicesAvailable as $s_no => $info) {
            if (strtolower($info['class']) == strtolower($className)) {
                $found = true;
                $this->threadCreate($info['class'], $arguments);
            }
        }

        if (!$found) {
            Standards::debug(__METHOD__ . ': service \'' . $className . '\' not \'available\'.', Standards::DO_EXIT);
        }
    }

    /**
     * @param $className
     * @param $method
     * @param array $arguments
     * @return mixed
     */
    protected function callService($className, array $arguments)
    {
        //Standards::debug(__METHOD__ . ': calling method');

        $obj = new $className();
        if (!method_exists($obj, 'doSets')) {
            Standards::debug($className . ' is missing doSets() method', Standards::DO_EXIT);
        } else {
            $obj->doSets($arguments);
        }

        if (!method_exists($obj, 'doWork')) {
            Standards::debug($className . ' is missing doWork() method', Standards::DO_EXIT);
        } else {
            $obj->doWork($arguments);
        }

        return $obj;
    }

    /**
     * @param $data
     * @return bool
     */
    private function isDataToSave($data)
    {
        return ($data !== null AND $data !== false AND is_array($data) AND count($data) > 0);
    }

    /**
     * @param $pid
     * @return bool|mixed
     */
    private function memoryRead($pid)
    {
        $shm_data = false;
        $shm_id = @shmop_open($pid, 'a', 0, 0);
        if ($shm_id) {
            $shm_data = shmop_read($shm_id, 0, shmop_size($shm_id));
            shmop_delete($shm_id);
            shmop_close($shm_id);
        }

        if ($shm_data == false) {
            return false;
        }

        return json_decode($shm_data, true);
    }

    /**
     * @param $pid
     * @param array $data_arr
     */
    private function memoryWrite($pid, array $data_arr)
    {
        $data_str = Standards::json_encode_special($data_arr);

        $shm_id = shmop_open($pid, "c", 0777, strlen($data_str));
        if (!$shm_id) {
            Standards::debug("Couldn't create shared memory segment");
        } else {
            if (shmop_write($shm_id, $data_str, 0) != strlen($data_str)) {
                Standards::debug("Couldn't write shared memory data");
            }
        }
    }

    /**
     * @return array
     */
    public function getDataCollected()
    {
        return $this->dataCollected;
    }

    /**
     * @param $threadPid
     * @param $serviceName
     * @param $domainName
     */
    protected function saveToRunning( $threadPid, $serviceName, $domainName )
    {

        if ( ! isset( $this->PIDs[$serviceName] )) {
            $this->PIDs[$serviceName] = array();
        }

        $this->PIDs[$serviceName][$domainName] = $threadPid;
    }

    /**
     * Purpose:
     * - Check's to see if we got any children closed
     * - It's being used in the service runner (like ProjectListener)
     * - removes associate data
     */
    protected function checkForClosed()
    {
        $childPid = pcntl_waitpid( - 1, $status, WNOHANG );
        if ($childPid > 1) {
            /*echo $childPid.' exited with status = '.$status."\n";*/

            foreach ($this->PIDs as $serviceName => $running) {
                foreach ($running as $domain => $r_pid) {
                    if ($r_pid == $childPid) {
                        unset( $this->PIDs[$serviceName][$domain] );

                        /*echo "removed: ".$r_pid."\n";
                        print_r($this->PIDs);
                        echo "\n";*/
                    }
                }
            }
        }
    }

    /*
     * - DEPRECATE METHODS ZONE -
     *
     * Note:
     * - in case this methods will be needed the code needs to be reviewed and adapted
     * to current changes, because some attributes might not be available anymore or
     * have a different structure (e.g.: some arrays)
    */


    /**
     * Waits for threads to exit after work ('waitable' ones)
     *
     * @param bool $disabled
     * @return bool
     */
    private function _deprecated_waitForFinish($disabled = true)
    {
        if ($disabled) {
            return false;
        }

        $waitedPIDs = $this->PIDs;

        if (count($waitedPIDs) == 0) {
            Standards::debug(__METHOD__ . ': No sub-processes running', Standards::DO_EXIT);
        }

        while (count($waitedPIDs) > 0) {
            $childPid = pcntl_waitpid(-1, $status, WNOHANG);

            # check to see if we got something to save first:
            $temp = $this->memoryRead($childPid);
            if ($this->isDataToSave($temp)) {
                if (isset($waitedPIDs[$childPid])) {
                    $this->dataCollected[$childPid] = $temp;
                }
            }

            # removing child pids:
            if ($childPid !== 0) {
                Standards::debug('child with pid: ' . $childPid . ' finished work (status=' . $status . ')');

                // needed un-sets - order matters !:
                foreach ($this->PIDs as $tempPid => $info) {
                    if (is_array($this->crawlingDomains)) {
                        foreach ($this->crawlingDomains as $domain_name => $null) {
                            if ($domain_name == $info['domain_name']) {
                                unset($this->crawlingDomains[$domain_name]);
                            }
                        }
                    }
                }

                if (isset($waitedPIDs[$childPid])) {
                    unset($waitedPIDs[$childPid]);
                }

                if (isset($this->PIDs[$childPid])) {
                    unset($this->PIDs[$childPid]);
                }
            }

            Standards::doDelay(' Waiting for \'waitable\' threads to end .. ', Config::getDelay('wait_for_finish_pause'));
        }

        return true;
    }

    /**
     * Add's signal handler to running pid's
     *
     */
    private function _deprecated_signalAdd()
    {
        $signals = array(
            SIGTERM,
            SIGHUP,
            SIGINT,
            SIGUSR1,
        );

        foreach ($signals as $s_no => $signal) {
            pcntl_signal($signal, array($this, 'signalHandler'));
        }
    }

    /**
     * Used by $this->signalAdd() in callback.
     *
     * @param $sigNo
     */
    private function _deprecated_signalHandler($sigNo)
    {
        # send message to end them:
        foreach ($this->PIDs as $pid => $ignore) {
            posix_kill($pid, $sigNo);
        }

        # wait to finish:
        foreach ($this->PIDs as $pid => $ignore) {
            pcntl_waitpid($pid, $status);
        }

        Standards::debug(null, Standards::DO_EXIT);
    }
}