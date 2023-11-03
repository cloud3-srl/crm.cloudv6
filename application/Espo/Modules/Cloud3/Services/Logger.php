<?php

namespace Espo\Modules\Cloud3\Services;

class Logger
{
    private $filePath;
    private $filePointer;

    public function __construct($path) {
        $this->filePath = $path;
    }

    public function write($message) {
        if (!is_resource($this->filePointer)) {
            $this->open();
        }
        $time = @date('[d/M/Y:H:i:s]');
        fwrite($this->filePointer, "$time $message" . PHP_EOL);
        echo $message;
    }

    public function close() {
        fclose($this->filePointer);
    }

    private function open() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $log_file_default = 'c:/php/logfile.txt';
        }
        else {
            $log_file_default = '/tmp/logfile.txt';
        }
        $lfile = $this->filePath ? $this->filePath : $log_file_default;
        $this->filePointer = fopen($lfile, 'a') or exit("Can't open $lfile!");
    }
}