<?php

namespace Espo\Modules\Cloud3\Controllers;

use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;

class CaseItem extends \Espo\Core\Controllers\Record
{
    public function actionCreate($params, $data, $request)
    {
        throw new Forbidden('Case Item can be created only within Case record.');
    }

    public function actionDelete($params, $data, $request)
    {
        throw new Forbidden('Case Item can be deleted only within Case record.');
    }

    public function actionMassDelete($params, $data, $request)
    {
        throw new Forbidden('Case Item can be deleted only within Case record.');
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
