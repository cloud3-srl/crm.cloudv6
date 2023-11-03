<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Sales Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/sales-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: c235cfac520a05e355b12cda9ca78531
 ***********************************************************************************/

namespace Espo\Modules\Sales\Controllers;

use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\Error;

class Invoice extends \Espo\Core\Controllers\Record
{
    public function actionGetAttributesFromOpportunity($params, $data, $request)
    {
        $opportunityId = $request->get('opportunityId');
        if (empty($opportunityId)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->getAttributesFromOpportunity($opportunityId);
    }

    public function actionGetAttributesFromQuote($params, $data, $request)
    {
        $quoteId = $request->get('quoteId');
        if (empty($quoteId)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->getAttributesFromQuote($quoteId);
    }

    public function actionGetAttributesFromSalesOrder($params, $data, $request)
    {
        $salesOrderId = $request->get('salesOrderId');
        if (empty($salesOrderId)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->getAttributesFromSalesOrder($salesOrderId);
    }

    public function postActionGetAttributesForEmail($params, $data)
    {
        if (is_array($data)) $data = (object) $data;

        if (empty($data->id) || empty($data->templateId)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->getAttributesForEmail($data->id, $data->templateId);
    }
}
