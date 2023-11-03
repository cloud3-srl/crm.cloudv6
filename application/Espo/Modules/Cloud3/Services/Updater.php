<?php

namespace Espo\Modules\Cloud3\Services;

class Updater extends \Espo\Core\Services\Base
{
    private $logger;

    public function updateAccounts($clientiURL) {
        $this->logger = new Logger("data/logs/AdHocAPI_".date("Y-m-d").".log");
        $newAndModified = array('n' => 0, 'r' => "");
        $deleted = array('n' => 0, 'r' => "");
        if(file_put_contents('data/tmp/clienti.json', APIClient::get($clientiURL))) {
            $clienti = json_decode(file_get_contents('data/tmp/clienti.json'));
        }
        $jobId = trim(strval($clienti[0]->jobId));
        if(!empty($jobId)) {
            foreach ($clienti as $cliente) {
                //Account
                $account = $this->getEntityManager()->getRepository('Account')->where(['anCodice' => trim($cliente->ancodice)])->findOne();
                if (!$account || $account->get('cpccchk') != trim($cliente->cpccchk)) {
                    // New or modified
                    $account = !$account ? $this->getEntityManager()->getEntity('Account') : $account;
                    $account->set(array(
                        'anCodice' => trim($cliente->ancodice),
                        'name' => trim($cliente->andescri),
                        'billingAddressStreet' => trim($cliente->anindiri),
                        'billingAddressPostalCode' => trim($cliente->ancap),
                        'billingAddressCity' => trim($cliente->anlocali),
                        'billingAddressState' => trim($cliente->anprovin),
                        'billingAddressCountry' => trim($cliente->annazion),
                        'phoneNumber' => trim($cliente->antelefo),
                        'emailAddress' => trim($cliente->anEmail),
                        'utdc' => !empty($cliente->utdc) ? trim($cliente->utdc) : trim($cliente->utdv),
                        'utdv' => !empty($cliente->utdv) ? trim($cliente->utdv) : trim($cliente->utdc),
                        'cpccchk' => trim($cliente->cpccchk)
                    ));
                    $newAndModified['n'] += 1;
                    $newAndModified['r'] .= "   " . trim($cliente->ancodice) . "\n";
                }
                $account->set(array(
                    'deleted' => 0,
                    'idJob' => $jobId,
                ));
                $this->getEntityManager()->saveEntity($account);
            }
            // Deleted
            $accountList = $this->getEntityManager()->getRepository('Account')->where([
                [
                    'AND' => [
                        'idJob!=' => $jobId,
                        'anCodice!=' => null
                    ]
                ]
            ])->find();
            foreach ($accountList as $account) {
                $account->set('deleted', 1);
                $this->getEntityManager()->saveEntity($account);
                $deleted['n'] += 1;
                $deleted['r'] .= "   " . trim($account->get('anCodice')) . "\n";
            }
            $this->logger->write("ACCOUNTS (JobID: " . $jobId . ")\n - New and modified: " . $newAndModified['n'] . "\n" . $newAndModified['r'] . " - Deleted: " . $deleted['n'] . "\n" . $deleted['r']);
        } else {
            $this->logger->write("Something went wrong, JobID is empty.\n");
        }
    }

    public function updateProducts($articoliURL) {
        $newAndModified = array('n' => 0, 'r' => "");
        $deleted = array('n' => 0, 'r' => "");
        if(file_put_contents('data/tmp/articoli.json', APIClient::get($articoliURL))) {
            $articoli = json_decode(file_get_contents('data/tmp/articoli.json'));
        }
        $jobId = trim($articoli[0]->jobId);
        if(!empty($jobId)) {
            foreach ($articoli as $articolo) {
                // ProductCategory
                $category = $this->getEntityManager()->getRepository('ProductCategory')->where(['arCatCon' => trim($articolo->arcatcon)])->findOne();
                if (!$category || $category->get('idJob') != $jobId) {
                    $category = !$category ? $this->getEntityManager()->getEntity('ProductCategory') : $category;
                    $category->set(array(
                        'arCatCon' => trim($articolo->arcatcon),
                        'name' => trim($articolo->ardescon),
                        'idJob' => $jobId
                    ));
                    $this->getEntityManager()->saveEntity($category);
                }
                // Product
                $product = $this->getEntityManager()->getRepository('Product')->where(['arCodArt' => trim($articolo->arcodart)])->findOne();
                if (!$product || $product->get('cpccchk') != trim($articolo->cpccchk)) {
                    // New or modified
                    $product = !$product ? $this->getEntityManager()->getEntity('Product') : $product;
                    $product->set(array(
                        'arCodArt' => trim($articolo->arcodart),
                        'name' => trim($articolo->ardesart),
                        'description' => trim($articolo->ardessup),
                        'categoryId' => $category->get('id'),
                        'utdc' => !empty($articolo->utdc) ? trim($articolo->utdc) : trim($articolo->utdv),
                        'utdv' => !empty($articolo->utdv) ? trim($articolo->utdv) : trim($articolo->utdc),
                        'cpccchk' => trim($articolo->cpccchk)
                    ));
                    $newAndModified['n'] += 1;
                    $newAndModified['r'] .= "   " . trim($articolo->arcodart) . "\n";
                }
                $product->set(array(
                    'deleted' => 0,
                    'idJob' => $jobId,
                ));
                $this->getEntityManager()->saveEntity($product);
            }
            // Deleted
            $productList = $this->getEntityManager()->getRepository('Product')->where(['idJob!=' => $jobId])->find();
            foreach ($productList as $product) {
                $product->set('deleted', 1);
                $this->getEntityManager()->saveEntity($product);
                $deleted['n'] += 1;
                $deleted['r'] .= "   " . trim($product->get('arCodArt')) . "\n";
            }
            $this->logger->write("PRODUCTS (JobID: " . $jobId . ")\n - New and modified: " . $newAndModified['n'] . "\n" . $newAndModified['r'] . " - Deleted: " . $deleted['n'] . "\n" . $deleted['r']);
        } else {
            $this->logger->write("Something went wrong, JobID is empty.\n");
        }
    }

    public function updatePrepagati($prepagatiURL) {
        $newAndModified = array('n' => 0, 'r' => "");
        $deleted = array('n' => 0, 'r' => "");
        $prepagati = json_decode(APIClient::get($prepagatiURL));
        $jobId = trim(strval($prepagati[0]->jobId));
        if(!empty($jobId)) {
            foreach ($prepagati as $prepagato) {
                // Prepagato
                $prep = $this->getEntityManager()->getRepository('Prepagato')->where(['mvSerial' => trim($prepagato->mvserial)])->findOne();
                if (!$prep || $prep->get('cpccchk') != trim($prepagato->cpccchk)) {
                    // New or modified
                    $prep = !$prep? $this->getEntityManager()->getEntity('Prepagato') : $prep;
                    $account = $this->getEntityManager()->getRepository('Account')->where(['anCodice' => trim($prepagato->mvcodcon)])->findOne();
                    $prep->set(array(
                        'accountId' => !empty($account->get('id')) ? $account->get('id') : null,
                        'mvSerial' => trim($prepagato->mvserial),
                        'mvNumReg' => trim($prepagato->mvnumreg),
                        'mvNumDoc' => trim($prepagato->mvnumdoc),
                        'mvDatReg' => trim($prepagato->mvdatreg),
                        'mvDatDoc' => trim($prepagato->mvdatdoc),
                        'mvAlfDoc' => trim($prepagato->mvalfdoc),
                        'mvTipCon' => trim($prepagato->mvtipcon),
                        'mvCodCon' => trim($prepagato->mvcodcon),
                        'mvQtaRes' => trim($prepagato->mvqtares),
                        'utdc' => !empty($prepagato->utdc) ? trim($prepagato->utdc) : trim($prepagato->utdv),
                        'utdv' => !empty($prepagato->utdv) ? trim($prepagato->utdv) : trim($prepagato->utdc),
                        'cpccchk' => trim($prepagato->cpccchk)
                    ));
                    $newAndModified['n'] += 1;
                    $newAndModified['r'] .= "   " . trim($prepagato->mvserial) . "\n";
                }
                $prep->set(array(
                    'deleted' => 0,
                    'idJob' => $jobId,
                ));
                $this->getEntityManager()->saveEntity($prep);
            }
            // Deleted
            $prepagatoList = $this->getEntityManager()->getRepository('Prepagato')->where(['idJob!=' => trim($jobId)])->find();
            foreach ($prepagatoList as $prepagato) {
                $prepagato->set('deleted', 1);
                $this->getEntityManager()->saveEntity($prepagato);
                $deleted['n'] += 1;
                $deleted['r'] .= "   " . trim($prepagato->get('mvSerial')) . "\n";
            }
            $this->logger->write("PREPAGATI (JobID: " . $jobId . ")\n - New and modified: " . $newAndModified['n'] . "\n" . $newAndModified['r'] . " - Deleted: " . $deleted['n'] . "\n" . $deleted['r']);
        } else {
            $this->logger->write("Something went wrong, JobID is empty.\n");
        }
    }
}