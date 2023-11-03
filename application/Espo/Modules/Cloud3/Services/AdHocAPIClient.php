<?php

namespace Espo\Modules\Cloud3\Services;

use Matrix\Exception;

class AdHocAPIClient extends \Espo\Core\Services\Base
{
    protected function init()
    {
        parent::init();

        $this->addDependency('injectableFactory');
        $this->addDependency('config');
    }

    protected function getConfig()
    {
        return $this->injections['config'];
    }

    public function getClienti() {
        if($clienti = $this->get($this->getConfig()->get('clientiURL'), $this->getConfig()->get('tempPath').'clienti.json')) {
            return array(
                $clienti[0]->jobId,
                $clienti
            );
        }
        return false;
    }

    public function getArticoli() {
        if($articoli = $this->get($this->getConfig()->get('articoliURL'), $this->getConfig()->get('tempPath').'articoli.json')) {
            return array(
                $articoli[0]->jobId,
                $articoli
            );
        }
        return false;
    }

    public function getPrepagati() {
        if($prepagati = $this->get($this->getConfig()->get('prepagatiURL'), $this->getConfig()->get('tempPath').'prepagati.json')) {
            return array(
                $prepagati[0]->jobId,
                $prepagati
            );
        }
        return false;
    }

    public function postRapportinoMast($data) {
        if($mast = $this->post($this->getConfig()->get('rapportiniURL'), $data)) {
            return $mast->orserial;
        }
        return false;
    }

    public function postRapportinoDett($data, $orSerial) {
        if($dett = $this->post($this->getConfig()->get('rapportiniURL').'/'.$orSerial, $data)) {
            return $dett->cprownum;
        }
        return false;
    }

    private function post($url, array $post = NULL, array $options = array()) {
        $defaults = array(
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_POSTFIELDS => json_encode($post),
            CURLOPT_HTTPHEADER => array('Content-Type: application/json')
        );
        $ch = curl_init();
        curl_setopt_array($ch, ($options + $defaults));
        $result = curl_exec($ch);
        if(curl_errno($ch)){
            return false;
        }
        curl_close($ch);
        return json_decode($result);
    }

    private function get($url, $responseFile = false, array $options = array()) {

        $defaults = array(
            CURLOPT_URL => $url. (strpos($url, '?') === FALSE ? '?' : ''),
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30
        );
        if($responseFile) {
            if(!$fileHandler = fopen($responseFile, 'w+')) {
                return false;
            }
            $defaults += [CURLOPT_FILE => $fileHandler];
        }
        $ch = curl_init();
        curl_setopt_array($ch, ($options + $defaults));
        $result = curl_exec($ch);
        if(curl_errno($ch)){
            return false;
        }
        curl_close($ch);
        if($responseFile){
            fclose($fileHandler);
            return json_decode(file_get_contents($responseFile));
        } else {
            return json_decode($result);
        }
    }
}