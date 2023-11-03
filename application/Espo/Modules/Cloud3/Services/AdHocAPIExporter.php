<?php

namespace Espo\Modules\Cloud3\Services;

class AdHocAPIExporter extends \Espo\Core\Services\Base
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

    public function exportRapportini() {
        $invoices = $this->getEntityManager()->getRepository('Invoice')->where([
            'adHoc' => 1,
            'status!=' => 'Sent'
        ])->find();
        foreach($invoices as $invoice) {
            if($orSerial = $this->exportRapportinoMaster($invoice)) {
                $invoiceItems = $this->getEntityManager()->getRepository('Invoice')->findRelated($invoice, 'items');
                foreach ($invoiceItems as $invoiceItem) {
                    if($cpRowNum = $this->exportRapportinoDetail($invoiceItem, $orSerial)) {
                        $invoice->set('status', 'Sent');
                        $this->getEntityManager()->saveEntity($invoice);
                    } else {
                        // Delete master
                        // Set status Error
                    }
                }
            } else {
                //Set status Error
            }
        }
    }

    private function exportRapportinoMaster($invoice) {
        $invoiceAccount = $this->getEntityManager()->getRepository('Invoice')->findRelated($invoice, 'account');
        $invoiceUser = $this->getEntityManager()->getRepository('Invoice')->findRelated($invoice, 'createdBy');
        $data = array(
            "orstato" => 1,
            "ortipdoc" => "RA",
            "ordatdoc" => $invoice->get('dateInvoiced'),
            "ornumdoc" => (int)(substr($invoice->get('number'), -5)),
            "oralfdoc" => "RAPP",
            "ornote" =>  $invoice->get('name'),
            "ortipcon" => "C",
            "orcodcon" => $invoiceAccount->get('anCodice'),
            "orcodage" => $invoiceUser->get('orCodAge'),
            "orcodval" => "EUR",
            "utcc" => $invoiceUser->get('orCodUte'),
            "utdc" => $invoice->get('dateInvoiced'),
            "cpccchk" => "xxxxxxxxxx"
        );
        if($orSerial = $this->getAdHocAPIClient()->postRapportinoMast($data)){
            return $orSerial;
        }
        return false;
    }

    private function exportRapportinoDetail($invoiceItem, $orSerial) {
        $itemProduct = $invoiceItem->get('product');
        if ($itemProduct) {
            $data = array(
                "orcodice" => $itemProduct->get('arCodArt'),
                "orcodart" => $itemProduct->get('arCodArt'),
                "ordesart" => $invoiceItem->get('name'),
                "ortiprig" => $itemProduct->get('isCaseItem') == 1 ? "M" : "R",
                "ordessup" => $invoiceItem->get('description'),
                "orcodlis" => "EUR",
                "orunimis" => $itemProduct->get('isCaseItem') == 1 ? "h." : "n.",
                "orqtamov" => $invoiceItem->get('quantity'),
                "orqtaum1" => $invoiceItem->get('quantity'),
                "cpccchk" => "xxxxxxxxxx"
            );
        }
        if($cpRowNum = $this->getAdHocAPIClient()->postRapportinoDett($data, $orSerial)){
            return $cpRowNum;
        }
        return false;
    }

}