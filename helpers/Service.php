<?php

class Service
{
    const DEBUG = TRUE, DO_EXIT = TRUE;
    protected $serviceName, $servicePID, $PIDs, $servicesAvailable, $servicesWaitable, $dataCollected, $crawlers;

    function __construct(array $servicesAvailable = array())
    {
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
        if (!function_exists('posix_getpid()')) {
            $this->debug('posix_getpid(): does not exist.');
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
            $this->debug('posix_kill(): does not exist', static::DO_EXIT);
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
            $this->debug('pcntl_fork(): does not exist', static::DO_EXIT);
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
                $this->debug('created thread with pid:' . $this->getPID());

                # random delay in milliseconds before thread's action:
                $this->doDelay();

                /* child action: */
                if (class_exists($callback)) {
                    # add parent's pid to arguments:
                    $callbackArgs += array('parentPID' => $this->servicePID);

                    # actual action:
                    $obj = $this->callObject($callback, $callbackArgs);
                    $collected = $obj->getdataCollected();

                    # save data:
                    //$this->debug($collected);
                    $this->memoryWrite($this->getPID(), $collected);
                } else if (function_exists($callback)) {
                    $this->callFunction($callback, $callbackArgs);
                } else {
                    $this->debug(__METHOD__ . ': Class/Function \'' . $callback . '\' does not exist.', static::DO_EXIT);
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
            $this->debug(__METHOD__ . ': service \'' . $className . '\' not found.', static::DO_EXIT);
        }
    }

    /**
     * @param $className
     * @param $method
     * @param array $arguments
     * @return mixed
     */
    protected function callObject($className, array $arguments)
    {
        //$this->debug(__METHOD__ . ': calling method');

        $obj = new $className();
        if (!method_exists($obj, 'makeSets')) {
            $this->debug($className . ' is missing makeSets() method', static::DO_EXIT);
        } else {
            $obj->makeSets($arguments);
        }

        if (!method_exists($obj, 'doWork')) {
            $this->debug($className . ' is missing doWork() method', static::DO_EXIT);
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
        //$this->debug(__METHOD__ . ': calling function');

        return $function($arguments);
    }

    /**
     * Waits for specific threads to end;
     * @param array $PIDs
     */
    protected function waitForFinish($PIDs = array())
    {
        if (count($this->PIDs) == 0) {
            $this->debug(__METHOD__ . ': No sub-processes running', static::DO_EXIT);
        }

        $waitedPIDs = $this->PIDs;
        foreach ($waitedPIDs as $pid => $service) {
            if (!array_key_exists($service, $this->servicesWaitable)) {
                unset($waitedPIDs[$pid]);
            }
        }

        while (count($waitedPIDs) > 0) {
            //$this->debug('complete list of pid\'s: ');
            //$this->debug($this->PIDs);

            $childPid = pcntl_waitpid(-1, $status);
            $temp = $this->memoryRead($childPid);

            # check to see if we got something to save first:
            if ($temp !== false AND is_array($temp) AND count($temp) > 0) {
                $this->dataCollected[$waitedPIDs[$childPid]][] = $temp;
            }

            # remove:
            $this->debug('child with pid: ' . $childPid . ' finished work (status=' . $status . ')');
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
            if (static::DEBUG) {
                $this->debug("Couldn't create shared memory segment");
            }
        } else {
            if (shmop_write($shm_id, $data_str, 0) != strlen($data_str)) {
                $this->debug("Couldn't write shared memory data");
            }
        }
    }

    /**
     * Purpose: do a random (100-300mls) or predefined (as parameter) sleep in milliseconds!
     * @param bool|int $milliseconds
     */
    protected function doDelay($milliseconds = FALSE)
    {
        if (!$milliseconds) {
            $milliseconds = rand(100, 300);
        }

        usleep($milliseconds);
    }

    /**
     * @param int $seconds
     */
    protected function doPause($seconds = 5)
    {
        $this->debug($this->serviceName . ' is sleeping ' . $seconds . 's');
        sleep($seconds);
    }

    /**
     * @param $msg
     * @param bool $exit
     */
    protected function debug($msg, $exit = false)
    {
        if (static::DEBUG) {
            if (is_array($msg)) {
                print_r($msg);
            } else {
                echo $msg;
            }
            echo "\n";
        }

        if ($exit) {
            exit(-1);
        }
    }

    /**
     * @return array
     */
    public function getDataCollected()
    {
        return $this->dataCollected;
    }
}