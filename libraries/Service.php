<?php

class Service
{
    protected $serviceName, $servicePID, $PIDs, $servicesAvailable, $servicesWaitable, $dataCollected, $crawlers, $startTime;

    function __construct(array $servicesAvailable = array())
    {
        $this->startTime = time();

        // keeps available services:
        $this->servicesAvailable = $servicesAvailable;
        $this->servicesWaitable = $this->getServicesWaitable($servicesAvailable);

        // service sets:
        $this->servicePID = $this->getPID();
        $this->serviceName = strtolower(get_class($this));

        // default sets:
        $this->dataCollected = $this->PIDs = array();
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
    private function threadKill($pid = NULL)
    {
        if (!function_exists('posix_kill')) {
            Standards::debug('posix_kill(): does not exist', Standards::DO_EXIT);
        }

        if ($pid == NULL) {
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
            $childPid = $this->threadFork();
            $this->PIDs[$childPid] = strtolower($callback);

            if (!$childPid) {
                Standards::debug('created thread with pid:' . $this->getPID());

                # random delay in milliseconds before thread's action:
                Standards::doDelay();

                /* child action: */
                if (class_exists($callback)) {
                    # add parent's pid to arguments:
                    $callbackArgs += array('parentPID' => $this->servicePID);

                    # actual action:
                    $obj = $this->callService($callback, $callbackArgs);
                    $collected = $obj->getDataCollected();

                    # save data:
                    //Standards::debug($collected);
                    $this->memoryWrite($this->getPID(), $collected);
                } else if (function_exists($callback)) {
                    $this->callFunction($callback, $callbackArgs);
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
     * @param $function
     * @param $arguments
     * @return mixed
     */
    protected function callFunction($function, $arguments)
    {
        //Standards::debug(__METHOD__ . ': calling function');

        return $function($arguments);
    }

    /**
     * Waits for specific threads to end;
     * @param array $PIDs
     */
    protected function waitForFinish($PIDs = array())
    {
        if (count($this->PIDs) == 0) {
            Standards::debug(__METHOD__ . ': No sub-processes running', Standards::DO_EXIT);
        }

        $waitedPIDs = $this->PIDs;
        foreach ($waitedPIDs as $pid => $service) {
            if (!array_key_exists($service, $this->servicesWaitable)) {
                unset($waitedPIDs[$pid]);
            }
        }

        while (count($waitedPIDs) > 0) {
            //Standards::debug('complete list of pid\'s: ');
            //Standards::debug($this->PIDs);

            $childPid = pcntl_waitpid(-1, $status);
            $temp = $this->memoryRead($childPid);

            # check to see if we got something to save first:
            if ($temp !== false AND is_array($temp) AND count($temp) > 0) {
                $this->dataCollected[$waitedPIDs[$childPid]][] = $temp;
            }

            # remove:
            Standards::debug('child with pid: ' . $childPid . ' finished work (status=' . $status . ')');
            unset($waitedPIDs[$childPid]);
            unset($this->PIDs[$childPid]);
        }
    }

    /**
     * @param $pid
     * @return bool|mixed
     */
    protected function memoryRead($pid)
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

        return json_decode($shm_data, TRUE);
    }

    /**
     * @param $pid
     * @param array $data_arr
     */
    protected function memoryWrite($pid, array $data_arr)
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
     * @param int $hours
     * @return bool
     */
    private function hoursPassed($hours = 6)
    {
        $currentTime = time();
        $_24h = 60 * 60 * $hours;

        $result = ($this->startTime + $_24h) > $currentTime;
        if ($result) {
            // reset startTime:
            $this->startTime = time();
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getDataCollected()
    {
        return $this->dataCollected;
    }
}