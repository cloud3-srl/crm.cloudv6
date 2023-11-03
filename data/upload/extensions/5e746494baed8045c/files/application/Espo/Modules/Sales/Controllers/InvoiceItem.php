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
 * Copyright (C) 2015-2020 Letrium Ltd.
 * 
 * License ID: 2f687f2013bc552b8556948039df639e
 ***********************************************************************************/

namespace Espo\Modules\Sales\Controllers;

use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;

class InvoiceItem extends \Espo\Core\Controllers\Record
{
    public function actionCreate($params, $data, $request)
    {
        throw new Forbidden('Invoice Item can be created only within Invoice record.');
    }

    public function actionDelete($params, $data, $request)
    {
        throw new Forbidden('Invoice Item can be deleted only with Invoice record.');
    }

    public function actionMassDelete($params, $data, $request)
    {
        throw new Forbidden('Invoice Item can be deleted only with Invoice record.');
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
