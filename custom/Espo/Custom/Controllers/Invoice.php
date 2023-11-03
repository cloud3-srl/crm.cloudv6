<?php

namespace Espo\Custom\Controllers;

class Invoice extends \Espo\Modules\Sales\Controllers\Invoice
{
    public function actionGetAttributesFromCase($params, $data, $request)
    {
        $caseId = $request->get('caseId');
        if (empty($caseId)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->getAttributesFromCase($caseId);
    }
}
