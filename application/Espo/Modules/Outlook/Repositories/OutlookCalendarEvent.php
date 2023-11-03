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

namespace Espo\Modules\Outlook\Repositories;

use Espo\ORM\Entity;

use Espo\Core\Utils\Util;

class OutlookCalendarEvent extends \Espo\Core\ORM\Repositories\RDB
{

    public function getEntityByCalendarIdEventId(string $calendarId, string $eventId)
    {
        $list = $this->where(['calendarId' => $calendarId, 'eventId' => $eventId])->limit(0, 5)->find();

        foreach ($list as $item) {
            if ($item->get('eventId') === $eventId) {
                return $item;
            }
        }

        return null;
    }

    public function getEventEntityByCalendarIdEventId(string $calendarId, string $eventId)
    {
        $entity = $this->getEntityByCalendarIdEventId($calendarId, $eventId);
        if (!$entity) return null;

        if (!$entity->get('entityType')) {
            throw new Error('OutlookCalendarEvent: Bad entity type.');
        }
        if (!$this->getEntityManager()->hasRepository($entity->get('entityType'))) {
            throw new Error('OutlookCalendarEvent: Bad entity type.');
        }

        if (!$entity->get('entityId')) return null;

        return $this->getEntityManager()->getEntity($entity->get('entityType'), $entity->get('entityId'));
    }
}
