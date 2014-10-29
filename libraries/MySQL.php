<?php

class MySQL
{
    private $config, $connection;

    function __construct()
    {
        $this->config = array(
            'host' => '104.131.14.145',
            'db_name' => 'site_analysis',
            'username' => 'root',
            'password' => 'My6Celeb',
        );

        // connect:
        $this->makeConnection();
    }

    /**
     * connects to db:
     */
    private function makeConnection()
    {
        $this->connection = mysqli_connect($this->config['host'], $this->config['username'], $this->config['password'], $this->config['db_name']);
        if ($this->connection) {
            mysqli_query($this->connection, 'SET CHARACTER SET utf8');
        } else {
            Standards::debug('Failed to connect to database.', Standards::DO_EXIT);
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
            if (is_resource($this->connection) AND $this->connection !== false) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * @param $query
     * @return bool|int|string
     */
    public function runQuery($query)
    {
        if ($this->connectionIsOk()) {
            if (!($id = mysqli_query($this->connection, $query))) {
                Standards::debug(mysqli_error($this->connection), Standards::DO_EXIT);
            }

            return mysqli_insert_id($this->connection);
        } else {
            Standards::debug('Something went wrong with db connection.', Standards::DO_EXIT);
            return false;
        }
    }

    /**
     * @param $query
     * @return array|bool
     */
    public function getResults($query)
    {
        $rows = FALSE;
        if ($this->connectionIsOk()) {
            $handle = mysqli_query($this->connection, $query);
            if ($handle) {
                if (mysqli_num_rows($handle) == 1) {
                    $rows[] = $handle->fetch_assoc();
                } else {
                    while ($row = mysqli_fetch_assoc($handle)) {
                        $rows[] = $row;
                    }
                }
            } else {
                Standards::debug(mysqli_error($this->connection), Standards::DO_EXIT);
            }
        }

        return $rows;
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