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
        $this->max_attempts = 5;
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
                $attempt++;
                Standards::doDelay(null, rand(1 / 4, 1 / 2));
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
        return $c['xvfb_bin'] . ' ' . $c['phantom_bin'] . ' ' . $c['confess_path'] . ' ' . $this->getLink() . ' ' . $c['mode'];
    }

    /**
     * @param $output
     * @return bool
     */
    private function valuesAreOK($output)
    {
        if ($output == NULL OR stripos($output, 'duration') == FALSE) {
            return FALSE;
        }

        if (stripos($output, '{') === FALSE AND stripos($output, '}') === FALSE) {
            return FALSE;
        }

        $output = substr($output, strpos($output, '{'), strpos($output, '}') + 1);
        try {
            $output = json_decode($output, true);
        } catch (Exception $e) {
            // ..
        }

        if (!is_array($output) OR stripos($output['duration'], 'nan') !== FALSE OR stripos($output['size'], 'nan') !== FALSE) {
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
