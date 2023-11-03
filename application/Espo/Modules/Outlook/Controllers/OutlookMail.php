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

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\BadRequest;

class OutlookMail extends \Espo\Core\Controllers\Base
{
    public function postActionConnect($params, $data)
    {
        $entityType = $data->entityType ?? null;
        $id = $data->id ?? null;
        $code = $data->code ?? null;

        if (!$entityType) throw new BadRequest();
        if (!$id) throw new BadRequest();
        if (!$code) throw new BadRequest();

        $this->getServiceFactory()->create('OutlookMail')->processAccessCheck($entityType, $id);

        return $this->getServiceFactory()->create('OutlookMail')->connect($entityType, $id, $code);
    }

    public function postActionDisconnect($params, $data)
    {
        $entityType = $data->entityType ?? null;
        $id = $data->id ?? null;

        if (!$entityType) throw new BadRequest();
        if (!$id) throw new BadRequest();

        $this->getServiceFactory()->create('OutlookMail')->processAccessCheck($entityType, $id);

        return $this->getServiceFactory()->create('OutlookMail')->disconnect($entityType, $id);
    }

    public function postActionPing($params, $data)
    {
        $entityType = $data->entityType ?? null;
        $id = $data->id ?? null;

        if (!$entityType) throw new BadRequest();
        if (!$id) throw new BadRequest();

        $this->getServiceFactory()->create('OutlookMail')->processAccessCheck($entityType, $id);

        $integration = $this->getContainer()->get('entityManager')->getEntity('Integration', 'Outlook');
        if ($integration) {
            return [
                'clientId' => $integration->get('clientId'),
                'redirectUri' => $this->getConfig()->get('siteUrl') . '/oauth-callback.php',
                'isConnected' => $this->getServiceFactory()->create('OutlookMail')->ping($entityType, $id),
            ];
        }
    }
}
