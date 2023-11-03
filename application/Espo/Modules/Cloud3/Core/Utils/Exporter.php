<?php

namespace Espo\Modules\Cloud3\Core\Utils;

class Exporter
{
    private $logger;
    private $rapportiniURL = 'http://10.0.50.51/api/rapportini';

    public function __construct($logger) {
        $this->logger = $logger;
    }

    public function exportRapportini() {
        $invoices = $this->getEntityManager()->getRepository('Invoice')->where([
            'adHoc' => 1,
            'status!=' => 'Sent'
        ])->find();
        foreach($invoices as $invoice) {
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
            $master = json_decode(APIClient::post($this->rapportiniURL, $data));
            $invoiceItems = $this->getEntityManager()->getRepository('Invoice')->findRelated($invoice, 'items');
            foreach ($invoiceItems as $invoiceItem) {
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
                $detail = json_decode($apiClient->post($this->rapportiniURL.'/'.$master->orserial, $data));
                echo $detail->cprownum.' - '.$detail->orcodart.'\n';
            }
            $invoice->set('status', 'Sent');
            $this->getEntityManager()->saveEntity($invoice);
        }
    }
}