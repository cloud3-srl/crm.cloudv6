<?php

namespace Espo\Custom\Controllers;

class CaseObj extends \Espo\Modules\Crm\Controllers\CaseObj
{
    public function actionGetAttributesFromCall($params, $data, $request)
    {
        $callId = $request->get('callId');
        if (empty($callId)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->getAttributesFromAnotherRecord('Call', $callId);
    }
}
