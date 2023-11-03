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

namespace Espo\Modules\Outlook\Hooks\Common;

use Espo\ORM\Entity;

use Espo\Core\Templates\Entities\Event;

use Espo\Core\Hooks\Base as BaseHook;

class OutlookCalendar extends BaseHook
{
    public static $order = 8;

    protected function isEvent($entity)
    {
        return (
            $entity instanceof Event
            ||
            $entity->getEntityType() === 'Meeting'
            ||
            $entity->getEntityType() === 'Call'
        );
    }

    public function afterSave(Entity $entity, $options)
    {
        if (!empty($options['silent'])) {
            return;
        }

        if (!$this->isEvent($entity)) {
            return;
        }

        if ($entity->isNew()) {
            return;
        }

        $calendarId = null;

        if (!empty($options['isOutlookSync'])) {
            $calendarId = $options['calendarId'];
        }

        $isChanged = false;

        $attributeList = [
            'name',
            'dateStart',
            'dateEnd',
            'isAllDay',
            'description',
            'status',
        ];

        foreach ($attributeList as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                $isChanged = true;
            }
        }

        if (!$isChanged) {
            return;
        }

        $list = $this->getEntityManager()
            ->getRepository('OutlookCalendarEvent')
            ->where([
                'entityId' => $entity->id,
                'entityType' => $entity->getEntityType(),
                'outlookDeleted' => false,
            ])
            ->find();

        foreach ($list as $event) {
            if ($event->get('calendarId') === $calendarId) {
                continue;
            }

            if ($event->get('isEspoEvent')) {
                if ($entity->get('status') === 'Not Held') {
                    $event->set('isDeleted', true);
                }
                else if ($event->get('isDeleted')) {
                    $event->set('isDeleted', false);
                }
            }

            $event->set('isUpdated', true);

            $this->getEntityManager()->saveEntity($event);
        }
    }

    public function afterRemove(Entity $entity, $options)
    {
        if (!empty($options['silent'])) {
            return;
        }

        if (!$this->isEvent($entity)) {
            return;
        }

        $calendarId = null;

        if (!empty($options['isOutlookSync'])) {
            $calendarId = $options['calendarId'];
        }

        $list = $this->getEntityManager()
            ->getRepository('OutlookCalendarEvent')
            ->where([
                'entityId' => $entity->id,
                'entityType' => $entity->getEntityType(),
                'outlookDeleted' => false,
            ])
            ->find();

        foreach ($list as $event) {
            if ($event->get('calendarId') === $calendarId) {
                continue;
            }

            $event->set('isDeleted', true);

            $this->getEntityManager()->saveEntity($event);
        }
    }
}
