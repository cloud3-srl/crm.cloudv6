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

namespace Espo\Modules\Outlook\Core\Outlook;

use Espo\Core\Exceptions\Error;
use Espo\Core\Container;
use Espo\Core\ExternalAccount\ClientManager;

use Espo\Modules\Outlook\Entities\OutlookCalendarUser;
use Espo\Entities\ExternalAccount;

use Espo\Modules\Outlook\Core\Outlook\Exceptions\ApiError;

use DateTime;
use DateTimeZone;
use Exception;

class CalendarManager
{
    const SYNC_MAX_PAGE_SIZE = 20;

    const PUSH_PORTION_SIZE = 20;

    const END_PERIOD = '3 months';

    protected $container;

    protected $entityManager;

    protected $metadata;

    protected $config;

    protected $selectManagerFactory;

    private $userClientMap = [];

    private $userMap = [];

    private $userWithFetchIntegrationIdList;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->entityManager = $container->get('entityManager');
        $this->metadata = $container->get('metadata');
        $this->config = $container->get('config');
        $this->aclManager = $container->get('aclManager');

        $this->selectManagerFactory = $container->get('selectManagerFactory');
    }

    protected function getClientManager(): ClientManager
    {
        return $this->container->get('externalAccountClientManager');
    }

    protected function getUserClient(string $userId)
    {
        if (!array_key_exists($userId, $this->userClientMap)) {
            $this->userClientMap[$userId] = $this->getClientManager()
                ->create('Outlook', $userId)
                ->getCalendarClient();
        }

        return $this->userClientMap[$userId];
    }

    private function getUserById(string $userId)
    {
        if (!isset($this->userMap[$userId])) {
            $this->userMap[$userId] = $this->entityManager->getEntity('User', $userId);
        }

        $user = $this->userMap[$userId];

        if (!$user) {
            throw new Error("Outlook sync: user {$userId} not found.");
        }

        return $user;
    }

    protected function getUserWithFetchIntegrationIdList()
    {
        if (isset($this->userWithFetchIntegrationIdList)) {
            return $this->userWithFetchIntegrationIdList;
        }

        $userList = $this->entityManager
            ->getRepository('User')
            ->select(['id'])
            ->where([
                'type' => ['admin', 'regular'],
                'isActive' => true,
            ])
            ->find();

        $userWithFetchIntegrationIdList = [];

        foreach ($userList as $user) {
            $ea = $this->entityManager->getRepository('ExternalAccount')->get('Outlook__' . $user->id);

            if ($ea->get('outlookCalendarEnabled') && $ea->get('calendarDirection') !== 'OutlookToEspo') {
                $userWithFetchIntegrationIdList[] = $user->id;
            }
        }

        $this->userWithFetchIntegrationIdList = $userWithFetchIntegrationIdList;

        return $userWithFetchIntegrationIdList;
    }

    public function getCalendarList($userId, $params = [])
    {
        $result = (object) [];

        $response = $this->getUserClient($userId)->getCalendarList($params);

        if (is_array($response) && isset($response['value'])) {
            foreach ($response['value'] as $item) {
                $id = $item['id'] ?? $item['Id'];
                $name = $item['name'] ?? $item['Name'];

                $result->$id = $name;
            }
         }

         return $result;
    }

    public function pushNewAttendeessOnly(OutlookCalendarUser $calendarUser, ExternalAccount $externalAccount)
    {
        return;
    }

    public function runSync(OutlookCalendarUser $calendarUser, ExternalAccount $externalAccount)
    {
        $nowString = (new DateTime())->format('Y-m-d H:i:s');

        $direction = $externalAccount->get('calendarDirection');

        if ($direction === 'OutlookToEspo' || $direction === 'Both') {
            $this->fetchFromOutlook($calendarUser, $externalAccount);
        }

        if ($direction === 'EspoToOutlook' || $direction === 'Both') {
            $this->pushToOutlook($calendarUser, $externalAccount);
        }

        $calendarUser->set('lastSyncedAt', $nowString);
        $this->entityManager->saveEntity($calendarUser);
    }

    public function fetchFromOutlook(OutlookCalendarUser $calendarUser, ExternalAccount $externalAccount)
    {
        $userId = $calendarUser->get('userId');

        $nowString = (new DateTime())->format('Y-m-d H:i:s');

        $endPeriod = $this->config->get('outlookCalendarSyncEndPeriod', self::END_PERIOD);

        $end = (new DateTime())->modify('+' . $endPeriod)->format('Y-m-d');

        $synMaxPageSize = $this->config->get('outlookCalendarSyncMaxPortionSize', self::SYNC_MAX_PAGE_SIZE);

        try {
            $result = $this->getUserClient($userId)->requestSync($calendarUser->get('outlookCalendarId'), [
                'start' => $externalAccount->get('calendarStartDate'),
                'end' => $end,
                'maxPageSize' => $synMaxPageSize,
                'deltaToken' => $calendarUser->get('deltaToken'),
                'skipToken' => $calendarUser->get('skipToken'),
            ]);
        }
        catch (Exception $e) {
            $reRun = false;

            if ($e instanceof ApiError && $calendarUser->get('deltaToken')) {
                $result = $e->getResult();
                $errorCode = $e->getOriginalCode();

                if ($errorCode === 400 && strtolower($result['message']) === strtolower('Badly formed token.')) {
                    $GLOBALS['log']->warning(
                        "Outlook sync: Delta token is not accepted. " .
                        "Syncing from now to obtain new delta token. " .
                        "User: " . $userId . ". " .
                        "Calendar ID: " . $calendarUser->get('outlookCalendarId') . "."
                    );

                    $reRun = true;
                }
            }

            if (!$reRun) {
                throw $e;
            }

            $result = $this->getUserClient($userId)->requestSync($calendarUser->get('outlookCalendarId'), [
                'start' => $nowString,
                'end' => $end,
                'maxPageSize' => $synMaxPageSize,
                'deltaToken' => null,
                'skipToken' => null,
            ]);
        }

        if (isset($result['skipToken']) || isset($result['deltaToken'])) {
            $calendarUser->set([
                'skipToken' => null,
                'deltaToken' => null,
            ]);

            if (isset($result['skipToken'])) {
                $calendarUser->set('skipToken', $result['skipToken']);
            }
            else if (isset($result['deltaToken'])) {
                $calendarUser->set('deltaToken', $result['deltaToken']);
            }
        }

        if (isset($result['itemList'])) {
            foreach ($result['itemList'] as $item) {
                $o = (object) $item;

                try {
                    $this->processOutlookItemSync($calendarUser, $externalAccount, $o);
                }
                catch (\Exception $e) {
                    $GLOBALS['log']->error($e->getMessage());
                }
            }
        }

        $isSyncFinished = $result['isSyncFinished'] ?? false;

        if ($isSyncFinished) {
            $calendarUser->set('lastSyncedAt', $nowString);
        }

        $this->entityManager->saveEntity($calendarUser);
    }

    protected function buildIdentityLabelMap(ExternalAccount $externalAccount)
    {
        $identLabelMap = [];
        $defaultEntityType = $externalAccount->get('calendarDefaultEntity');

        foreach ($externalAccount->get('calendarEntityTypes') ?? [] as $itemEntityType) {
            $identLabel = $externalAccount->get($itemEntityType . 'IdentificationLabel') ?? '';

            if ($itemEntityType !== $defaultEntityType && $identLabel) {
                $identLabelMap[$itemEntityType] = $identLabel;
            }
        }

        return $identLabelMap;
    }

    protected function processOutlookItemSync(
        OutlookCalendarUser $calendarUser,
        ExternalAccount $externalAccount,
        $item
    ) {
        $userId = $calendarUser->get('userId');

        $reason = $item->Reason ?? $item->reason ?? null;
        $id = $item->Id ?? $item->id ?? null;
        $type = $item->Type ?? null;

        if (!$reason && isset($item->{'@removed'})) {
            $reason = $item->{'@removed'}['reason'] ?? null;
        }

        if ($type === 'Occurrence' || $type === 'SeriesMaster') {
            return;
        }

        if (!$id) {
            throw new Error("Outlook sync: No event id.");
        }

        if (strpos($id, 'CalendarView(') === 0 || strpos($id, 'calendarView(') === 0) {
            $id = substr($id, 14);
            $id = substr($id, 0, -2);

            if (empty($id)) {
                throw new Error("Outlook sync: Bad event id.");
            }
        }

        $params = [
            'defaultEntity' => $externalAccount->get('calendarDefaultEntity'),
            'labelMap' => $this->buildIdentityLabelMap($externalAccount),
            'createContacts' => $externalAccount->get('calendarCreateContacts'),
            'skipPrivate' => $externalAccount->get('calendarSkipPrivate'),
        ];

        $outlookUserId = $externalAccount->get('outlookUserId');

        if ($reason === 'deleted') {
            $this->processDeleteEvent($calendarUser, $params, $id, $item);
        }
        else {
            $this->processCreateUpdateEvent($calendarUser, $outlookUserId, $params, $id, $item);
        }
    }

    protected function processDeleteEvent(
        OutlookCalendarUser $calendarUser,
        array $params,
        string $eventId,
        $item
    ) {
        $entity = $this->entityManager
            ->getRepository('OutlookCalendarEvent')
            ->getEventEntityByCalendarIdEventId(
                $calendarUser->get('calendarId'),
                $eventId
            );

        $userId = $calendarUser->get('userId');
        $user = $this->getUserById($userId);

        $relationEvent = $this->entityManager
            ->getRepository('OutlookCalendarEvent')
            ->getEntityByCalendarIdEventId(
                $calendarUser->get('calendarId'),
                $eventId
            );

        //$isEspoEvent = false;

        if ($relationEvent) {
            $isEspoEvent = $relationEvent->get('isEspoEvent');

            if ($isEspoEvent) {
                $relationEvent->set('outlookDeleted', true);

                $this->entityManager->saveEntity($relationEvent);

                return;
            }

            $this->entityManager->removeEntity($relationEvent);
        }

        if (!$entity) {
            return;
        }

        /*
        if ($isEspoEvent) {
            return;
        }

        if ($isEspoEvent && !$this->aclManager->check($user, $entity, 'delete')) {
            $GLOBALS['log']->info("Outlook sync: No access to delete event for user {$userId}.");

            return;
        }*/

        $this->entityManager->removeEntity($entity, [
            'isOutlookSync' => true,
            'noNotifications' => true,
            'calendarId' => $calendarUser->get('calendarId'),
        ]);
    }

    protected function processCreateUpdateEvent(
        OutlookCalendarUser $calendarUser,
        $outlookUserId,
        array $params,
        string $eventId,
        $item
    ) {

        $userId = $calendarUser->get('userId');

        $user = $this->getUserById($userId);

        $relationEvent = $this->entityManager
            ->getRepository('OutlookCalendarEvent')
            ->getEntityByCalendarIdEventId(
                $calendarUser->get('calendarId'),
                $eventId
            );

        $iCalUId = $item->iCalUId ?? null;

        $itemData = $this->getDataFromItem($params, $item);

        $defaultEntityType = $params['defaultEntity'];
        $identLabelMap = $params['labelMap'];
        $entityType = $defaultEntityType;

        $skipSave = false;

        if (!$relationEvent) {
            $entity = null;

            if ($itemData->isPrivate && $params['skipPrivate']) {
                return;
            }

            $relationEvent2 = $this->entityManager
                ->getRepository('OutlookCalendarEvent')
                ->where([
                    'iCalUId' => $iCalUId,
                ])
                ->findOne();

            if ($relationEvent2) {
                if ($relationEvent2->get('isEspoEvent')) {
                    return;
                }

                $entity = $this->entityManager->getEntity($relationEvent2->get('entityType'), $relationEvent2->get('entityId'));
            }

            $isPrimary = false;

            if (!$entity) {
                if (!empty($item->isCancelled) || !empty($item->IsCancelled)) {
                    return;
                }

                $isPrimary = true;

                $name = $itemData->name ?? '';

                $name = $this->getRealNameFromOutlookName($name, $identLabelMap);

                if (!$this->aclManager->check($user, $entityType, 'create')) {
                    $GLOBALS['log']->info("Outlook sync: No access to create event {$entityType} for user {$userId}.");

                    return;
                }

                $entity = $this->entityManager->getEntity($entityType);

                $entity->set($itemData);
                $entity->set('assignedUserId', $userId);

                $nameMaxLength = $entity->getAttributeParam('name', 'len');

                if ($nameMaxLength && mb_strlen($name) > $nameMaxLength) {
                    $name = mb_substr($name, 0, $nameMaxLength);
                }

                $entity->set('name', $name);

                $attendeeList = $item->attendees ?? $item->Attendees ?? [];

                if (!empty($attendeeList)) {
                    $accountId = null;
                    $leadId = null;
                    $contactId = null;

                    foreach ($attendeeList as $attendeeItem) {
                        $aEmailAddress = $attendeeItem['emailAddress']['address'] ??
                            $attendeeItem['EmailAddress']['Address'] ?? null;

                        $aName = $attendeeItem['emailAddress']['name'] ??
                            $attendeeItem['EmailAddress']['Name'] ?? null;

                        if (isset($aEmailAddress)) {
                            $e = $this->entityManager
                                ->getRepository('EmailAddress')
                                ->getEntityByAddress($aEmailAddress, null, [
                                    'Contact', 'Lead', 'User', 'Account'
                                ]);

                            if ($e) {
                                if ($e->getEntityType() === 'Contact') {
                                    if ($entity->hasLinkMultipleField('contacts')) {
                                        $entity->addLinkMultipleId('contacts', $e->id);
                                    }

                                    if (!$contactId) {
                                        $contactId = $e->id;
                                    }

                                    if (!$accountId && $e->get('accountId')) {
                                        $accountId = $e->get('accountId');
                                    }
                                }
                                else if ($e->getEntityType() === 'Lead') {
                                    if ($entity->hasLinkMultipleField('leads')) {
                                        $entity->addLinkMultipleId('leads', $e->id);
                                    }
                                    if (!$leadId) {
                                        $leadId = $e->id;
                                    }
                                }
                                else if ($e->getEntityType() === 'Account') {
                                    $accountId = $e->id;
                                }
                                else if ($e->getEntityType() === 'User') {
                                    if (
                                        $e->id !== $userId &&
                                        !in_array($e->id, $this->getUserWithFetchIntegrationIdList())
                                    ) {
                                        if ($entity->hasLinkMultipleField('users')) {
                                            $entity->addLinkMultipleId('users', $e->id);
                                        }
                                    }
                                }
                            }
                            else {
                                if ($params['createContacts']) {
                                    if ($this->aclManager->check($user, 'Contact', 'create')) {
                                        $firstName = null;
                                        $lastName = null;

                                        if ($aName) {
                                            $lastName = $aName;

                                            if ($sIndex = mb_strpos($aName, ' ')) {
                                                $firstName = trim(mb_substr($aName, 0, $sIndex));
                                                $lastName = trim(mb_substr($aName, $sIndex + 1));
                                            }
                                        }

                                        $contact = $this->entityManager->getEntity('Contact');
                                        $contact->set([
                                            'firstName' => $firstName,
                                            'lastName' => $lastName,
                                            'emailAddress' => $aEmailAddress,
                                            'assignedUserId' => $user->id,
                                        ]);

                                        if ($user->get('defaultTeamId')) {
                                            $contact->addLinkMultipleId('teams', $user->get('defaultTeamId'));
                                        }

                                        $this->entityManager->saveEntity($contact);

                                        $contactId = $contact->id;

                                        if ($entity->hasLinkMultipleField('contacts')) {
                                            $entity->addLinkMultipleId('contacts', $contactId);
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if ($accountId) {
                        $entity->set('accountId', $accountId);
                        $entity->set('parentId', $accountId);
                        $entity->set('parentType', 'Account');
                    }
                    else if ($leadId) {
                        $entity->set('parentId', $leadId);
                        $entity->set('parentType', 'Lead');
                    }
                    else if ($contactId) {
                        $entity->set('parentId', $contactId);
                        $entity->set('parentType', 'Contact');
                    }
                }
            } else {
                if ($entity->hasLinkMultipleField('users')) {
                    $this->entityManager
                        ->getRepository($entity->getEntityType())
                        ->relate($entity, 'users', $userId);

                    $skipSave = true;
                }
            }

            if (!empty($item->IsCancelled)) {
                $entity->set('status', 'Not Held');
            }

            if (!$skipSave) {
                $this->entityManager->saveEntity($entity, [
                    'isOutlookSync' => true,
                    'noNotifications' => true,
                    'calendarId' => $calendarUser->get('calendarId'),
                ]);
            }

            $relationEvent = $this->entityManager->createEntity('OutlookCalendarEvent', [
                'entityType' => $entity->getEntityType(),
                'entityId' => $entity->id,
                'calendarId' => $calendarUser->get('calendarId'),
                'eventId' => $eventId,
                'syncedAt' => date('Y-m-d H:i:s'),
                'isUpdated' => false,
                'iCalUId' => $iCalUId,
                'isPrimary' => $isPrimary,
                'userId' => $userId,
                'outlookUserId' => $outlookUserId,
            ]);
        }
        else {
            if (!$relationEvent->get('isPrimary')) {
                return;
            }

            $entity = $this->entityManager
                ->getRepository('OutlookCalendarEvent')
                ->getEventEntityByCalendarIdEventId(
                    $calendarUser->get('calendarId'),
                    $eventId
                );

            if ($entity) {
                if ($relationEvent->get('isEspoEvent') && $relationEvent->get('isUpdated')) {
                    return;
                }

                $relationEvent->set([
                    'syncedAt' => date('Y-m-d H:i:s'),
                    'isUpdated' => false,
                ]);

                $this->entityManager->saveEntity($relationEvent);

                if ($relationEvent->get('isEspoEvent') && !$this->aclManager->check($user, $entity, 'edit')) {
                    $GLOBALS['log']->info("Outlook sync: No access to edit event for user {$userId}.");

                    return;
                }

                if (isset($itemData->name)) {
                    $name = $itemData->name ?? '';

                    $name = $this->getRealNameFromOutlookName($name, $identLabelMap);

                    $nameMaxLength = $entity->getAttributeParam('name', 'len');

                    if ($nameMaxLength && mb_strlen($name) > $nameMaxLength) {
                        $name = mb_substr($name, 0, $nameMaxLength);
                    }

                    $entity->set('name', $name);
                }

                if ($relationEvent->get('isEspoEvent')) {
                    unset($itemData->description);
                }

                unset($itemData->name);

                $entity->set($itemData);

                $this->entityManager->saveEntity($entity, [
                    'isOutlookSync' => true,
                    'noNotifications' => true,
                    'calendarId' => $calendarUser->get('calendarId'),
                ]);
            }
        }
    }

    protected function getRealNameFromOutlookName($name, $identLabelMap)
    {
        foreach ($identLabelMap as $kEntityType => $label) {
            if (strpos($name, $label . ':') === 0) {
                $entityType = $kEntityType;
                $name = trim(substr($name, mb_strlen($label . ':')));

                break;
            }
        }
        return $name;
    }

    public function pushToOutlook(OutlookCalendarUser $calendarUser, ExternalAccount $externalAccount)
    {
        $userId = $calendarUser->get('userId');

        $user = $this->getUserById($userId);

        $entityTypeList = [];

        foreach ($externalAccount->get('calendarEntityTypes') ?? [] as $entityType) {
            if (!$this->aclManager->check($user, $entityType, 'read')) {
                continue;
            }

            if (!$this->entityManager->hasRepository($entityType)) {
                continue;
            }

            $entityTypeList[] = $entityType;
        }

        $isMain = $calendarUser->get('type') === 'main';

        $syncStartDate = $externalAccount->get('calendarStartDate');

        $maxSize = $this->config->get('outlookCalendarPushMaxPortionSize', self::PUSH_PORTION_SIZE);

        $params = [
            'defaultEntityType' => $externalAccount->get('calendarDefaultEntity'),
            'labelMap' => $this->buildIdentityLabelMap($externalAccount),
        ];

        $outlookUserId = $externalAccount->get('outlookUserId');

        $dontPushPastEvents = $externalAccount->get('calendarDontPushPastEvents') ?? false;

        $totalNewCount = 0;

        foreach ($entityTypeList as $entityType) {
            $identLabel = $externalAccount->get($entityType . 'IdentificationLabel') ?? '';

            $count = 0;

            $newEntityList = [];
            $updatedEntityList = [];
            $deletedEntityList = [];

            if ($isMain && $count < $maxSize) {
                $newEntityList = $this->getNewEntityListToPush(
                    $entityType, $userId, $calendarUser->get('calendarId'), $syncStartDate,
                    $maxSize - $count, $outlookUserId, $dontPushPastEvents
                );

                $count += count($newEntityList);

                $totalNewCount += count($newEntityList);
            }

            if ($count < $maxSize) {
                $updatedEntityList = $this->getUpdatedEntityListToPush(
                    $entityType, $userId, $calendarUser->get('calendarId'), $syncStartDate, $maxSize - $count
                );

                $count += count($updatedEntityList);
            }

            if ($count < $maxSize) {
                $deletedEntityList = $this->getDeletedEntityListToPush(
                    $entityType, $userId, $calendarUser->get('calendarId'), $syncStartDate,
                    $maxSize - $count, $externalAccount->get('removeOutlookCalendarEventIfRemovedInEspo')
                );

                $count += count($deletedEntityList);
            }

            //$requestBodyList = [];

            $batchHash = [];

            $requestItemList = [];

            $counter = 1;

            foreach ($newEntityList as $entity) {
                $item = $this->getItemFromEntity($params, $entity, true, true);

                $requestItemList[] = (object) [
                    'id' => strval($counter),
                    'method' => 'POST',
                    'url' => '/me/calendars/' . $calendarUser->get('outlookCalendarId') . '/events',
                    'headers' => (object) [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => $item,
                ];

                $batchHash[strval($counter)] = [
                    'type' => 'POST',
                    'entity' => $entity,
                ];

                $counter++;
            }

            foreach ($updatedEntityList as $entity) {
                $eventEntity = $this->entityManager
                    ->getRepository('OutlookCalendarEvent')
                    ->where([
                        'entityId' => $entity->id,
                        'entityType' => $entityType,
                        'calendarId' => $calendarUser->get('calendarId'),
                    ])
                    ->findOne();

                if (!$eventEntity) {
                    continue;
                }

                $item = $this->getItemFromEntity($params, $entity, false, $eventEntity->get('isEspoEvent'));

                $eventId = $eventEntity->get('eventId');

                $requestItemList[] = (object) [
                    'id' => strval($counter),
                    'method' => 'PATCH',
                    'url' => '/me/calendars/' . $calendarUser->get('outlookCalendarId') . '/events/' . $eventId,
                    'headers' => (object) [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => $item,
                ];

                $batchHash[strval($counter)] = [
                    'type' => 'PATCH',
                    'entity' => $entity,
                    'eventEntity' => $eventEntity,
                ];

                $counter++;
            }

            foreach ($deletedEntityList as $entity) {
                $eventEntity = $this->entityManager
                    ->getRepository('OutlookCalendarEvent')
                    ->where([
                        'entityId' => $entity->id,
                        'entityType' => $entityType,
                        'calendarId' => $calendarUser->get('calendarId'),
                    ])
                    ->findOne();

                if (!$eventEntity) {
                    continue;
                }

                $eventId = $eventEntity->get('eventId');

                $requestItemList[] = (object) [
                    'id' => strval($counter),
                    'method' => 'DELETE',
                    'url' => '/me/calendars/' . $calendarUser->get('outlookCalendarId') . '/events/' . $eventId,
                ];

                $batchHash[strval($counter)] = [
                    'type' => 'DELETE',
                    'entity' => $entity,
                    'eventEntity' => $eventEntity,
                ];

                $counter++;
            }

            if (count($requestItemList)) {
                $resultList = $this->getUserClient($userId)->batchRequest($requestItemList);

                if (count($resultList) !== count($batchHash)) {
                    throw new Error("Outlook Calendar sync: Bad batch response. Doesn't match request.");
                }

                foreach ($resultList as $i => $item) {
                    $id = $item['id'] ?? null;

                    if (!$id) {
                        $GLOBALS['log']->warning("Outlook Calendar sync: No ID in batch response item.");

                        continue;
                    }

                    $requestItem = $batchHash[$id] ?? null;

                    if (!$requestItem) {
                        $GLOBALS['log']->warning("Outlook Calendar sync: Bad ID in batch response item.");

                        continue;
                    }

                    if ($requestItem['type'] === 'POST' && $item['status'] === 201) {

                        $responseData = $item['body'];

                        if (!$responseData) {
                            continue;
                        }

                        $eventId =  $responseData['Id'] ?? $responseData['id'] ?? null;

                        if (!$eventId) {
                            continue;
                        }

                        $iCalUId = $responseData['iCalUId'];

                        $isPrimary = !$this->entityManager
                            ->getRepository('OutlookCalendarEvent')
                            ->where([
                                'entityId' => $requestItem['entity']->id,
                                'entityType' => $requestItem['entity']->getEntityType(),
                            ])
                            ->findOne();

                        $this->entityManager->createEntity('OutlookCalendarEvent', [
                            'entityId' => $requestItem['entity']->id,
                            'entityType' => $requestItem['entity']->getEntityType(),
                            'eventId' => $eventId,
                            'iCalUId' => $iCalUId,
                            'calendarId' => $calendarUser->get('calendarId'),
                            'syncedAt' => date('Y-m-d H:i:s'),
                            'isEspoEvent' => true,
                            'isPrimary' => $isPrimary,
                            'userId' => $userId,
                            'outlookUserId' => $externalAccount->get('outlookUserId'),
                        ]);
                    }
                }

                foreach ($batchHash as $item) {
                    if ($item['type'] === 'PATCH') {
                        $item['eventEntity']->set('isUpdated', false);

                        $this->entityManager->saveEntity($item['eventEntity']);
                    }

                    if ($item['type'] === 'DELETE') {
                        $this->entityManager->removeEntity($item['eventEntity']);
                    }
                }
            }
        }

        if (!$dontPushPastEvents && $totalNewCount === 0) {
            $externalAccount->set('calendarDontPushPastEvents', true);

            $externalAccountCopy = $this->entityManager->getEntity('ExternalAccount', $externalAccount->id);
            $externalAccountCopy->set('calendarDontPushPastEvents', true);

            $this->entityManager->saveEntity($externalAccountCopy);
        }
    }

    protected function getNewEntityListToPush(
        $entityType,
        $userId,
        $calendarId,
        $syncStartDate,
        $maxSize,
        $outlookUserId = null,
        $dontPushPastEvents = false
    ) {
        $user = $this->getUserById($userId);

        $selectManager = $this->selectManagerFactory->create($entityType, $user);

        $seed = $this->entityManager->getEntity($entityType);

        $selectParams = $selectManager->getEmptySelectParams();

        $selectManager->applyAccess($selectParams);

        $selectParams['whereClause'][] = [
            'status!=' => 'Not Held',
        ];

        if (!$dontPushPastEvents) {
            $selectParams['whereClause'][] = ['dateStart>=' => $syncStartDate];
        } else {
            $since = new DateTime();

            $since->modify('-' . $this->config->get('outlookCalendarPushPastPeriod', '5 days'));

            $selectParams['whereClause'][] = ['createdAt>=' => $since->format('Y-m-d H:i:s')];
        }

        $joinConditions = [
            'outlookCalendarEvent.entityId:' => 'id',
            'outlookCalendarEvent.entityType' => $entityType,
            'outlookCalendarEvent.deleted' => false,
            'outlookCalendarEvent.userId' => $userId,
        ];

        $version = $this->config->get('version');
        if ($version === 'dev' || version_compare($version, '5.6.6') >= 0) {
            if ($outlookUserId) {
                $joinConditions['OR'] = [
                    ['outlookCalendarEvent.outlookUserId' => null],
                    ['outlookCalendarEvent.outlookUserId' => $outlookUserId],
                ];
            }
        }

        $selectManager->addLeftJoin(['OutlookCalendarEvent', 'outlookCalendarEvent', $joinConditions], $selectParams);

        $selectManager->addLeftJoin(['OutlookCalendarEvent', 'outlookCalendarEventSecond', [
            'outlookCalendarEventSecond.entityId:' => 'id',
            'outlookCalendarEventSecond.entityType' => $entityType,
            'outlookCalendarEventSecond.deleted' => false,
            'outlookCalendarEventSecond.isEspoEvent' => false,
        ]], $selectParams);

        $selectManager->setDistinct(true, $selectParams);

        $selectParams['whereClause'][] = ['outlookCalendarEvent.id' => null];
        $selectParams['whereClause'][] = ['outlookCalendarEventSecond.id' => null];

        if ($seed->hasRelation('users')) {
            $selectManager->addJoin('users', $selectParams);
            $selectManager->setDistinct(true, $selectParams);

            $selectParams['whereClause'][] = [
                'usersMiddle.userId' => $userId,
            ];
        } else if ($seed->hasAttribute('assignedUserId')) {
            $selectParams['whereClause'][] = ['assignedUserId' => $userId];
        }
        else {
            $GLOBALS['log']->warning("Outlook Calendar sync: No user relationship for {$entityType}.");

            return [];
        }

        $entityList = $this->entityManager
            ->getRepository($entityType)
            ->limit(0, $maxSize)
            ->order('modifiedAt')
            ->find($selectParams);

        return $entityList;
    }

    protected function getUpdatedEntityListToPush($entityType, $userId, $calendarId, $syncStartDate, $maxSize)
    {
        $user = $this->getUserById($userId);

        $selectManager = $this->selectManagerFactory->create($entityType, $user);

        $seed = $this->entityManager->getEntity($entityType);

        $selectParams = $selectManager->getEmptySelectParams();

        $selectManager->applyAccess($selectParams);

        $selectManager->addJoin([
            'OutlookCalendarEvent',
            'outlookCalendarEvent',
            [
                'outlookCalendarEvent.entityId:' => 'id',
                'outlookCalendarEvent.entityType' => $entityType,
                'outlookCalendarEvent.deleted' => false,
                'outlookCalendarEvent.calendarId' => $calendarId,
            ]
        ], $selectParams);

        $selectManager->setDistinct(true, $selectParams);

        $selectParams['whereClause'][] = [
            'outlookCalendarEvent.isUpdated' => true,
            'outlookCalendarEvent.isDeleted' => false,
        ];

        if ($seed->hasRelation('users')) {
            $selectManager->addJoin('users', $selectParams);
            $selectManager->setDistinct(true, $selectParams);
            $selectParams['whereClause'][] = ['usersMiddle.userId' => $userId];
        }
        else if ($seed->hasAttribute('assignedUserId')) {
            $selectParams['whereClause'][] = ['assignedUserId' => $userId];
        }
        else {
            $GLOBALS['log']->warning("Outlook Calendar sync: No user relationship for {$entityType}.");
            return [];
        }

        $entityList = $this->entityManager
            ->getRepository($entityType)
            ->limit(0, $maxSize)
            ->order('modifiedAt')
            ->find($selectParams);

        return $entityList;
    }

    protected function getDeletedEntityListToPush(
        $entityType,
        $userId,
        $calendarId,
        $syncStartDate,
        $maxSize,
        $includeOutlookEvents = false
    ) {
        $user = $this->getUserById($userId);

        $selectManager = $this->selectManagerFactory->create($entityType, $user);

        $seed = $this->entityManager->getEntity($entityType);

        $selectParams = $selectManager->getEmptySelectParams();
        $selectManager->applyAccess($selectParams);
        $selectManager->addJoin([
            'OutlookCalendarEvent',
            'outlookCalendarEvent',
            [
                'outlookCalendarEvent.entityId:' => 'id',
                'outlookCalendarEvent.entityType' => $entityType,
                'outlookCalendarEvent.deleted' => false,
                'outlookCalendarEvent.calendarId' => $calendarId,
            ]
        ], $selectParams);

        $selectManager->setDistinct(true, $selectParams);

        $selectParams['whereClause'][] = [
            'outlookCalendarEvent.isDeleted' => true,
        ];

        if (!$includeOutlookEvents) {
            $selectParams['whereClause'][] = [
                'outlookCalendarEvent.isEspoEvent' => true,
            ];
        }

        if ($seed->hasRelation('users')) {
            $selectManager->addJoin('users', $selectParams);
            $selectManager->setDistinct(true, $selectParams);

            $selectParams['whereClause'][] = ['usersMiddle.userId' => $userId];
        }
        else if ($seed->hasAttribute('assignedUserId')) {
            $selectParams['whereClause'][] = ['assignedUserId' => $userId];
        }
        else {
            $GLOBALS['log']->warning("Outlook Calendar sync: No user relationship for {$entityType}.");

            return [];
        }

        $selectParams['withDeleted'] = true;

        $entityList = $this->entityManager
            ->getRepository($entityType)
            ->limit(0, $maxSize)
            ->order('modifiedAt')
            ->find($selectParams);

        return $entityList;
    }

    protected function getItemFromEntity(array $params, $entity, $toCreate = false, $isEspoEvent = false)
    {
        $isAllDay = $entity->get('isAllDay') ?? false;

        $name = $entity->get('name');

        $labelMap = $params['labelMap'] ?? [];

        if ($entity->getEntityType() !== $params['defaultEntityType']) {
            foreach ($labelMap as $kEntityType => $label) {
                if ($kEntityType === $entity->getEntityType()) {
                    if (strpos($name, $label . ':') !== 0) {
                        $entityType = $kEntityType;
                        $name = $label  . ':'. $name;

                        break;
                    }
                }
            }
        }

        $timeZone = $this->config->get('timeZone', 'UTC');

        $dateStart = $entity->get('dateStart');
        $dateEnd = $entity->get('dateEnd');

        if ($isAllDay) {
            $timeZone = 'UTC';
            $dateStart = $entity->get('dateStartDate') . ' 00:00:00';
            $dateEnd = (new DateTime($entity->get('dateEndDate')))->modify('+1 day')->format('Y-m-d H:i:s');
        } else {
            if ($timeZone !== 'UTC') {
                $tz = new DateTimeZone($timeZone);
                $dateStart = (new DateTime($dateStart))->setTimezone($tz)->format('Y-m-d H:i:s');
                $dateEnd = (new DateTime($dateEnd))->setTimezone($tz)->format('Y-m-d H:i:s');
            }
        }

        $item = [
            'Subject' => $name,
            'Start' => [
                'DateTime' => $dateStart,
                'TimeZone' => $timeZone,
            ],
            'End' => [
                'DateTime' => $dateEnd,
                'TimeZone' => $timeZone,
            ],
            'IsAllDay' => $isAllDay,
        ];

        if ($isEspoEvent) {
            if ($entity->get('description')) {
                $item['Body'] = [
                    'ContentType' => 'Text',
                    'Content' => $entity->get('description'),
                ];
            }
        }

        return $item;
    }

    protected function getDataFromItem(array $params, $item)
    {
        $isAllDay = $item->IsAllDay ?? $item->isAllDay ?? null;

        $dateStart = null;
        $dateEnd = null;

        $itemStart = $item->Start ?? $item->start ?? null;
        $itemEnd = $item->End ?? $item->end ?? null;

        if (isset($itemStart)) {
            $start = new DateTime($itemStart['DateTime'] ?? $itemStart['dateTime']);

            $dateStart = $start->format('Y-m-d H:i:s');
        }
        if (isset($itemEnd)) {
            $end = new DateTime($itemEnd['DateTime'] ?? $itemEnd['dateTime']);

            $dateEnd = $end->format('Y-m-d H:i:s');
        }

        if ($isAllDay && isset($start) && isset($end)) {
            $dateStartDate = $start->format('Y-m-d');
            $dateEndDate = $end->modify('-1 day')->format('Y-m-d');
        }

        $name = $item->Subject ?? $item->subject ?? null;

        $description = null;

        $body = $item->Body ?? $item->body ?? null;

        if ($body) {
            $bodyContentType = $body['ContentType'] ?? $body['contentType'] ?? null;

            if (strtolower($bodyContentType) === 'html') {
                $description = self::htmlToPlainText($body['Content'] ?? $body['content'] ?? '') ?? null;
            }
            else if (strtolower($bodyContentType) === 'text') {
                $description = $body['Content'] ?? $body['content'] ?? null;
            }
        }

        $data = (object) [];

        if ($dateStart) {
            $data->dateStart = $dateStart;
        }

        if ($dateEnd) {
            $data->dateEnd = $dateEnd;
        }

        if (!is_null($isAllDay)) {
            $data->isAllDay = $isAllDay;
        }

        if (!is_null($name)) {
            $data->name = $name;
        }

        if (!is_null($description)) {
            $description = trim($description) ?? null;
            $data->description = $description;
        }

        if ($isAllDay) {
            if ($dateStartDate) {
                $data->dateStartDate = $dateStartDate;
            }

            if ($dateEndDate) {
                $data->dateEndDate = $dateEndDate;
            }
        }

        $sensitivity = strtolower($item->Sensitivity ?? $item->sensitivity ?? '');

        $data->isPrivate = $sensitivity === 'private';

        return $data;
    }

    protected static function htmlToPlainText($body)
    {
        $breaks = ["<br />","<br>","<br/>","<br />","&lt;br /&gt;","&lt;br/&gt;","&lt;br&gt;"];

        $body = str_ireplace($breaks, "\r\n", $body);
        $body = strip_tags($body);

        $reList = [
            '/&(quot|#34);/i',
            '/&(amp|#38);/i',
            '/&(lt|#60);/i',
            '/&(gt|#62);/i',
            '/&(nbsp|#160);/i',
            '/&(iexcl|#161);/i',
            '/&(cent|#162);/i',
            '/&(pound|#163);/i',
            '/&(copy|#169);/i',
            '/&(reg|#174);/i',
        ];

        $replaceList = [
            '',
            '&',
            '<',
            '>',
            ' ',
            chr(161),
            chr(162),
            chr(163),
            chr(169),
            chr(174)
        ];

        $body = preg_replace($reList, $replaceList, $body);

        return $body;
    }
}
