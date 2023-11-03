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

namespace Espo\Modules\Outlook\Hooks\ExternalAccount;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

use Espo\Core\{
    Exceptions\Error,
    ExternalAccount\ClientManager,
};

class Outlook
{
    public static $order = 9;

    private $entityManager;

    private $externalAccountClientManager;

    public function __construct(
        EntityManager $entityManager,
        ClientManager $externalAccountClientManager
    ) {
        $this->entityManager = $entityManager;
        $this->externalAccountClientManager = $externalAccountClientManager;
    }

    public function afterSave(Entity $entity, array $options): void
    {
        list($integration, $userId) = explode('__', $entity->id);

        if ($integration !== 'Outlook') {
            return;
        }

        if (!empty($options['isTokenRenewal'])) {
            return;
        }

        $direction = $entity->get('calendarDirection');

        $monitoredCalendarIds = $entity->get('calendarMonitoredCalendarsIds') ?? [];
        $monitoredCalendarNames = $entity->get('calendarMonitoredCalendarsNames') ?? (object) [];

        $mainCalendarId = $entity->get('calendarMainCalendarId');
        $mainCalendarName = $entity->get('calendarMainCalendarName');

        $monitoredHash = [];

        $monitoredList = $this->entityManager
            ->getRepository('OutlookCalendarUser')
            ->where([
                'type' => 'monitored',
                'userId' => $userId,
            ])
            ->find();

        foreach ($monitoredList as $item) {
            $monitoredHash[$item->get('calendarId')] = $item;
        }

        $mainHash = [];

        $mainList = $this->entityManager
            ->getRepository('OutlookCalendarUser')
            ->where([
                'type' => 'main',
                'userId' => $userId,
            ])
            ->find();

        foreach ($mainList as $item) {
            $mainHash[$item->get('calendarId')] = $item;
        }

        if ($direction == 'OutlookToEspo') {
            if (!in_array($mainCalendarId, $monitoredCalendarIds)) {
                $monitoredCalendarIds[] = $mainCalendarId;
                $monitoredCalendarNames->$mainCalendarId = $mainCalendarName;
            }

            $mainCalendarId = null;
            $mainCalendarName = null;
        }

        if ($direction == 'EspoToOutlook') {
            $monitoredCalendarIds = [];
        }

        foreach ($monitoredCalendarIds as $calendarId) {
            if ($calendarId === $mainCalendarId) {
                continue;
            }

            $outlookCalendar = $this->entityManager
                ->getRepository('OutlookCalendar')
                ->getByOutlookCalendarId($calendarId);

            if (!$outlookCalendar) {
                $outlookCalendar = $this->entityManager->getEntity('OutlookCalendar');

                $outlookCalendar->set('name', $monitoredCalendarNames->$calendarId);
                $outlookCalendar->set('calendarId', $calendarId);

                $this->entityManager->saveEntity($outlookCalendar);
            }

            $id = $outlookCalendar->id;

            if (isset($monitoredHash[$id])) {
                if (!$monitoredHash[$id]->get('active')) {
                    $monitoredHash[$id]->set('active', true);

                    $this->entityManager->saveEntity($monitoredHash[$id]);
                }
            }
            else {
                $calendarUser = $this->entityManager->getEntity('OutlookCalendarUser');

                $calendarUser->set('userId', $userId);
                $calendarUser->set('type', 'monitored');
                $calendarUser->set('calendarId', $id);

                $this->entityManager->saveEntity($calendarUser);
            }
        }

        foreach ($monitoredHash as $id => $item) {
            if (
                $item->get('active') &&
                (
                    !is_array($monitoredCalendarIds) ||
                    !in_array($item->get('outlookCalendarId'), $monitoredCalendarIds)
                )
            ) {
                $monitoredHash[$id]->set('active', false);

                $this->entityManager->saveEntity($monitoredHash[$id]);
            }
        }

        if (!$mainCalendarId) {
            foreach ($mainHash as $item) {
                if ($item->get('active')) {
                    $item->set('active', false);
                    $this->entityManager->saveEntity($item);
                }
            }
        }
        else {
            $outlookCalendar = $this->entityManager
                ->getRepository('OutlookCalendar')
                ->getByOutlookCalendarId($mainCalendarId);

            if (!$outlookCalendar) {
                $outlookCalendar = $this->entityManager->getEntity('OutlookCalendar');

                $outlookCalendar->set('name', $mainCalendarName);
                $outlookCalendar->set('calendarId', $mainCalendarId);

                $this->entityManager->saveEntity($outlookCalendar);
            }

            $id = $outlookCalendar->id;

            foreach ($mainHash as $calendarId => $item) {
                if ($item->get('active') && $id !== $calendarId) {
                    $item->set('active', false);

                    $this->entityManager->saveEntity($item);
                }
                else if (!$item->get('active') && $id === $calendarId) {
                    $item->set('active', true);

                    $this->entityManager->saveEntity($item);
                }
            }

            if (!isset($mainHash[$id])) {
                $item = $this->entityManager->getEntity('OutlookCalendarUser');

                $item->set('userId', $userId);
                $item->set('type', 'main');
                $item->set('calendarId', $id);

                $this->entityManager->saveEntity($item);
            }
        }
    }

    public function beforeSave(Entity $entity): void
    {
        list($integration, $userId) = explode('__', $entity->id);

        if ($integration !== 'Outlook') {
            return;
        }

        $prevEntity = $this->entityManager->getEntity('ExternalAccount', $entity->id);

        if ($prevEntity && $prevEntity->get('calendarStartDate') > $entity->get('calendarStartDate')) {
            $calendarUserList = $this->entityManager
                ->getRepository('OutlookCalendarUser')
                ->where([
                    'active' => true,
                    'userId' => $userId,
                ])
                ->find();

            foreach ($calendarUserList as $calendarUser) {
                $calendarUser->set('skipToken', null);
                $calendarUser->set('deltaToken', null);
                $calendarUser->set('lastSyncedAt', null);

                $this->entityManager->saveEntity($calendarUser);
            }
        }
    }

    public function afterConnect(Entity $entity, array $options): void
    {
        if ($options['integration'] !== 'Outlook') {
            return;
        }

        $clientManager = $this->externalAccountClientManager;

        $client = $clientManager->create($options['integration'], $options['userId']);

        if (!$client) {
            throw new Error();
        }

        $isMail = false;

        $userId = $options['userId'];

        if (!$this->entityManager->getEntity('User', $userId)) {
            $isMail = true;
        }

        if ($isMail) {
            $client = $client->getMailClient();
        }

        $result = $client->requestUserData();

        if (empty($result)) {
            throw new Error("Outlook did not return user data.");
        }

        $outlookUserId = $result['Id'] ?? $result['id'] ?? null;

        if (!$outlookUserId) {
            throw new Error("Outlook did not return user ID.");
        }

        $entity->set('outlookUserId', $outlookUserId);

        $this->entityManager->saveEntity($entity);
    }
}
