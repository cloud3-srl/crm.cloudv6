<?php

namespace Espo\Modules\Cloud3\Services;

class AdHocAPIUpdater extends \Espo\Core\Services\Base
{
    protected function init()
    {
        parent::init();

        $this->addDependency('injectableFactory');
        $this->addDependency('entityManager');
        $this->addDependency('serviceFactory');
        $this->addDependency('config');
        $this->addDependency('logger');
    }

    protected function getEntityManager()
    {
        return $this->injections['entityManager'];
    }

    protected function getLogger()
    {
        return $this->injections['logger'];
    }

    protected function getServiceFactory()
    {
        return $this->injections['serviceFactory'];
    }

    protected function getConfig()
    {
        return $this->injections['config'];
    }

    protected function getAdHocAPIClient()
    {
        return $this->getServiceFactory()->create('AdHocAPIClient');
    }

    public function updateAccounts() {
        $newAndModified = array('n' => 0, 'r' => "");
        if(list($jobId, $clienti) = $this -> getAdHocAPIClient()->getClienti()) {
            foreach ($clienti as $cliente) {
                //Account
                if($this->compareAndUpdateAccount($cliente, $jobId)) {
                    $newAndModified['n'] += 1;
                    $newAndModified['r'] .= "   " . trim($cliente->ancodice) . "\n";
                }
            }
            $this->getLogger()->write("UPDATING ACCOUNTS (JobID: " . $jobId . ")\n - New and modified: " . $newAndModified['n'] . "\n" . $newAndModified['r']);
            return $jobId;
        } else {
            $this->getLogger()->write("UPDATING ACCOUNTS\n - AdHocAPI connection failed.\n");
            return false;
        }
    }

    public function updateProducts() {
        $newAndModified = array('n' => 0, 'r' => "");
        if(list($jobId, $articoli) = $this -> getAdHocAPIClient()->getArticoli()) {
            foreach ($articoli as $articolo) {
                if($this->compareAndUpdateProduct($articolo,$this->updateAndGetProductCategory($articolo, $jobId),$jobId)) {
                    $newAndModified['n'] += 1;
                    $newAndModified['r'] .= "   " . trim($articolo->arcodart) . "\n";
                }
            }
            $this->getLogger()->write("UPDATING PRODUCTS (JobID: " . $jobId . ")\n - New and modified: " . $newAndModified['n'] . "\n" . $newAndModified['r']);
            return $jobId;
        } else {
            $this->getLogger()->write("UPDATING PRODUCTS\n - AdHocAPI connection failed.\n");
            return false;
        }
    }

    public function updatePrepagati() {
        $newAndModified = array('n' => 0, 'r' => "");
        if(list($jobId, $prepagati) = $this -> getAdHocAPIClient()->getPrepagati()) {
            foreach ($prepagati as $prepagato) {
                if($this->compareAndUpdatePrepagato($prepagato,$jobId)) {
                    $newAndModified['n'] += 1;
                    $newAndModified['r'] .= "   " . trim($prepagato->mvserial) . "\n";
                }
            }
            $this->getLogger()->write("UPDATING PREPAGATI (JobID: " . $jobId . ")\n - New and modified: " . $newAndModified['n'] . "\n" . $newAndModified['r']);
            return $jobId;
        } else {
            $this->getLogger()->write("UPDATING PREPAGATI\n - AdHocAPI connection failed.\n");
            return false;
        }
    }

    public function deleteAccounts($jobId) {
        $deleted = array('n' => 0, 'r' => "");
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
            $deleted['r'] .= "   " . trim($account('anCodice')) . "\n";
        }
        $this->getLogger()->write("DELETING ACCOUNTS (JobID: " . $jobId . ")\n - Deleted: " . $deleted['n'] . "\n" . $deleted['r']);
    }

    public function deleteProducts($jobId) {
        $deleted = array('n' => 0, 'r' => "");
        $productList = $this->getEntityManager()->getRepository('Product')->where(['idJob!=' => $jobId])->find();
        foreach ($productList as $product) {
            $product->set('deleted', 1);
            $this->getEntityManager()->saveEntity($product);
            $deleted['n'] += 1;
            $deleted['r'] .= "   " . trim($product->get('arCodArt')) . "\n";
        }
        $this->getLogger()->write("DELETING PRODUCTS (JobID: " . $jobId . ")\n - Deleted: " . $deleted['n'] . "\n" . $deleted['r']);
    }

    public function deletePrepagati($jobId) {
        $deleted = array('n' => 0, 'r' => "");
        $prepagatoList = $this->getEntityManager()->getRepository('Prepagato')->where(['idJob!=' => trim($jobId)])->find();
        foreach ($prepagatoList as $prepagato) {
            $prepagato->set('deleted', 1);
            $this->getEntityManager()->saveEntity($prepagato);
            $deleted['n'] += 1;
            $deleted['r'] .= "   " . trim($prepagato->get('mvSerial')) . "\n";
        }
        $this->getLogger()->write("DELETING PREPAGATI (JobID: " . $jobId . ")\n - Deleted: " . $deleted['n'] . "\n" . $deleted['r']);
    }

    private function compareAndUpdateAccount($cliente, $jobId) {
        $updated = false;
        $account = $this->getEntityManager()->getRepository('Account')->where(['anCodice' => trim($cliente->ancodice)])->findOne();
        if (!$account || $account->get('cpccchk') != trim($cliente->cpccchk)) {
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
            $updated = true;
        }
        $account->set(array(
            'deleted' => 0,
            'idJob' => $jobId,
        ));
        $this->getEntityManager()->saveEntity($account);
        return $updated;
    }

    private function compareAndUpdateProduct($articolo, $category, $jobId) {
        $updated = false;
        $product = $this->getEntityManager()->getRepository('Product')->where(['arCodArt' => trim($articolo->arcodart)])->findOne();
        if (!$product || $product->get('cpccchk') != trim($articolo->cpccchk)) {
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
            $updated = true;
        }
        $product->set(array(
            'deleted' => 0,
            'idJob' => $jobId,
        ));
        $this->getEntityManager()->saveEntity($product);
        return $updated;
    }

    private function compareAndUpdatePrepagato($prepagato, $jobId) {
        $updated = false;
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
            $updated = true;
        }
        $prep->set(array(
            'deleted' => 0,
            'idJob' => $jobId,
        ));
        $this->getEntityManager()->saveEntity($prep);
        return $updated;
    }

    private function updateAndGetProductCategory($articolo, $jobId) {
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
        return $category;
    }
}