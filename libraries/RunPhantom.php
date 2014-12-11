<?php

class RunPhantom
{
    protected $cmd_params, $max_attempts, $response;

    public function __construct(array $cmd_params)
    {
        if (empty($cmd_params)) {
            Standards::debug('no cmd params found', Standards::DO_EXIT);
        }

        # sets:
        $this->max_attempts = 3;
        $this->cmd_params = $cmd_params;
        $this->default_response = '{"url":"' . $this->cmd_params['link'] . '","duration":"n/a","size":"n/a"}';
    }

    public function run()
    {
        $response = false;
        $attempt = 0;
        while (!$response AND $attempt < $this->max_attempts) {
            $response = shell_exec($this->getCmd());
            $response = $this->valuesAreOK($response);

            if (!$response) {
                Standards::doDelay(null, 1);
                $attempt++;
            }
        }

        $this->response = $response;
    }

    /**
     * @return string
     */
    public function getResult()
    {
        if (!is_array($this->response)) {
            return $this->default_response;
        }

        return json_encode($this->response);
    }

    /**
     * @return string
     */
    private function getCmd()
    {
        $c = $this->cmd_params;

        return trim($c['xvfb_bin'] . ' ' . $c['phantom_bin'] . ' ' . $c['js_script_path'] . ' ' . $this->getLink());
    }

    /**
     * @param $output
     * @return bool
     */
    private function hasDelimiters($output)
    {
        if (stripos($output, '{') === false AND stripos($output, '}') === false) {
            return false;
        }

        if (stripos($output, 'start-response:') === false OR stripos($output, ':end-response') === false) {
            return false;
        }

        return true;
    }

    /**
     * @param $output
     * @return bool
     */
    private function valuesAreOK($output)
    {
        if ($output == NULL and !$this->hasDelimiters($output)) {
            return false;
        }

        $output = trim(substr($output, stripos($output, 'start-response:') + strlen('start-response:'), stripos($output, ':end-response') - strlen(':end-response') - 2));
        try {
            $output = json_decode($output, true);
        } catch (Exception $e) {
            // ..
        }

        if (!is_array($output)) {
            return false;
        }

        return $output;
    }

    /**
     * @return string
     */
    private function getLink()
    {
        return '"' . $this->cmd_params['link'] . '"';
    }
}
