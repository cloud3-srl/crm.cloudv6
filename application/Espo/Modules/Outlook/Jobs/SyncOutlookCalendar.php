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

namespace Espo\Modules\Outlook\Jobs;

use Throwable;

class SyncOutlookCalendar extends \Espo\Core\Jobs\Base
{
    public function run()
    {
        $integrationEntity = $this->getEntityManager()->getEntity('Integration', 'Outlook');

        if (!$integrationEntity || !$integrationEntity->get('enabled')) {
            return false;
        }

        $service = $this->getServiceFactory()->create('OutlookCalendar');

        $itemList = $this->getEntityManager()
            ->getRepository('OutlookCalendarUser')
            ->join(['user'])
            ->where([
                'active' => true,
                'user.isActive' => true,
            ])
            ->find();

        try {
            foreach ($itemList as $item) {
                $service->syncCalendar($item);
            }
        }
        catch (Throwable $e) {
            $GLOBALS['log']->error('Outlook Calendar sync: ' . $e->getMessage());

            return false;
        }

        return true;
    }
}
