<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Outlook Integration
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/outlook-extension-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2022 Letrium Ltd.
 *
 * License ID: 26bfa1fab74a68212506685b1b343192
 ***********************************************************************************/

namespace Espo\Modules\Outlook\Controllers;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;

class OutlookContacts extends \Espo\Core\Controllers\Base
{
    public function postActionContactFolders($params, $data, $request)
    {
        return $this->getService('OutlookContacts')->contactFolders();
    }

    public function postActionPush($params, $data, $request)
    {
        if (!$this->getAcl()->checkScope($this->name)) throw new Forbidden();
        if (empty($data->entityType)) throw new BadRequest();

        $entityType = $data->entityType;

        $params = [];
        if (isset($data->byWhere) && $data->byWhere) {
            $params['where'] = [];
            foreach ($data->where as $item) {
                $params['where'][] = (array) $item;
            }
        } else {
            if (empty($data->idList)) throw new BadRequest();
            $params['ids'] = $data->idList;
        }
        return [
            'count' => $this->getService('OutlookContacts')->push($entityType, $params)
        ];
    }
}
