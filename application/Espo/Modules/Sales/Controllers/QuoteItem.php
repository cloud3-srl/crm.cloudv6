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
 * Copyright (C) 2015-2022 Letrium Ltd.
 *
 * License ID: c235cfac520a05e355b12cda9ca78531
 ***********************************************************************************/

namespace Espo\Modules\Sales\Controllers;

use Espo\Core\Exceptions\Forbidden;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;

use stdClass;

class QuoteItem extends \Espo\Core\Controllers\Record
{
    public function postActionCreate(Request $request, Response $response): stdClass
    {
        throw new Forbidden();
    }

    public function deleteActionDelete(Request $request, Response $response): bool
    {
        throw new Forbidden();
    }

    public function actionCreate($params, $data, $request)
    {
        throw new Forbidden('Quote Item can be created only within Quote record.');
    }

    public function actionDelete($params, $data, $request)
    {
        throw new Forbidden('Quote Item can be deleted only with Quote record.');
    }

    public function actionMassDelete($params, $data, $request)
    {
        throw new Forbidden('Quote Item can be deleted only with Quote record.');
    }

    public function beforeMassConvertCurrency($params, $data, $request)
    {
        throw new Forbidden();
    }

    public function beforeConvertCurrency($params, $data, $request)
    {
        throw new Forbidden();
    }
}
