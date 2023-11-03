<?php

namespace Espo\Modules\Cloud3\Core\Utils;

use Espo\Core\Utils\Config;

class Logger
{
    private $config;
    private $logFile;

    public function __construct(Config $config) {
        $this->config = $config;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    public function write($message) {
        if (!is_resource($this->logFile)) {
            $this->open();
        }
        $time = @date('[d/M/Y:H:i:s]');
        fwrite($this->logFile, "$time $message" . PHP_EOL);
        echo $message;
    }

    public function close() {
        fclose($this->logFile);
    }

    private function open() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $log_file_default = 'c:/php/logfile.txt';
        }
        else {
            $log_file_default = '/tmp/logfile.txt';
        }
        $lfile = $this->getConfig()->get('logFilePath') ? $this->getConfig()->get('logFilePath').date("Y-m-d").'.log' : $log_file_default;
        $this->logFile = fopen($lfile, 'a') or exit("Can't open $lfile!");
    }
}