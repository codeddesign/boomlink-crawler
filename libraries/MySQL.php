<?php

class MySQL
{
    private $config, $connection;

    function __construct()
    {
        $config = array(
            'host' => '',
            'db_name' => '',
            'port' => 3306,
            'username' => '',
            'password' => '',
        );

        // sets:
        $this->config = $config;
        $this->connection = false;

        // connect:
        $this->makeConnection();
    }

    /**
     * connects to db:
     */
    private function makeConnection()
    {
        if ($con = mysqli_connect($this->config['host'], $this->config['username'], $this->config['password'], $this->config['db_name'])) {
            $this->connection = $con;
            mysqli_query($con, 'SET CHARACTER SET utf8');
        } else {
            $this->connection = false;
            exit('EXIT: failed to connect to database.');
        }
    }

    /**
     * @return bool
     */
    private function connectionIsOk()
    {
        $status = mysqli_ping($this->connection);

        if ($status) {
            return true;
        } else {
            //just in case:
            $this->endConnection();

            //create new one:
            $this->makeConnection();
            if ($this->connection !== false) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * @param $query
     * @return bool
     */
    public function runQuery($query)
    {
        if ($this->connectionIsOk()) {
            if (!mysqli_query($this->connection, $query)) {
                exit(mysql_error());
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $query
     * @return array|null
     */
    public function getResults($query)
    {
        if ($this->connectionIsOk()) {
            if (!($handle = mysqli_query($this->connection, $query))) {
                exit(mysql_error());
            } else {
                $out = mysqli_fetch_all($handle, MYSQLI_ASSOC);
            }
        }

        if (!isset($out) OR $out == NULL) {
            $out = array();
        }

        return $out;
    }

    public function endConnection()
    {
        if ($this->connection) {
            mysqli_close($this->connection);
            $this->connection = false;
        }
    }

    function __destruct()
    {
        $this->endConnection();
    }
}