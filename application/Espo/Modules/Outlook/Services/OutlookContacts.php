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

namespace Espo\Modules\Outlook\Services;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;

use DateTime;

class OutlookContacts extends \Espo\Core\Services\Base
{
    private $contactsManager;

    protected $forceSelectAllAttributes = true;

    const PUSH_PORTION_SIZE = 5;

    protected function init()
    {
        parent::init();
        $this->addDependency('entityManager');
        $this->addDependency('container');
        $this->addDependency('metadata');
        $this->addDependency('selectManagerFactory');
        $this->addDependency('acl');
    }

    protected function getSelectManagerFactory()
    {
        return $this->getInjection('selectManagerFactory');
    }

    protected function getEntityManager()
    {
        return $this->getInjection('entityManager');
    }

    protected function getContainer()
    {
        return $this->getInjection('container');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function getContactsManager()
    {
        if (!$this->contactsManager) {
            $this->contactsManager = new \Espo\Modules\Outlook\Core\Outlook\ContactsManager($this->getContainer());
        }

        return $this->contactsManager;
    }

    public function contactFolders(array $params = null)
    {
        return $this->getContactsManager()->getContactFolderList($this->getUser()->id);
    }

    public function push($entityType, array $params)
    {
        $integrationEntity = $this->getEntityManager()->getEntity('Integration', 'Outlook');

        if (
            !$integrationEntity ||
            !$integrationEntity->get('enabled')
        ) {
            throw new Forbidden();
        }

        if (!$this->getInjection('acl')->checkScope('OutlookContacts')) {
            throw new Forbidden();
        }

        $userId = $this->getUser()->id;
        $externalAccount = $this->getEntityManager()->getEntity('ExternalAccount', 'Outlook__' . $userId);

        if (!$externalAccount->get('enabled') || !$externalAccount->get('outlookContactsEnabled')) {
            throw new Forbidden();
        }

        $portion = $this->getConfig()->get('outlookContactsPushPortionSize', self::PUSH_PORTION_SIZE);

        $p = [];

        $resultCount = 0;

        if (array_key_exists('ids', $params)) {
            $ids = $params['ids'];
            $where = [
                [
                    'type' => 'in',
                    'field' => 'id',
                    'value' => $ids
                ]
            ];
        } else if (array_key_exists('where', $params)) {
            $where = $params['where'];
        } else {
            throw new BadRequest();
        }

        $selectManger = $this->getSelectManagerFactory()->create($entityType);

        $p['where'] = $where;

        $selectParams = $selectManger->getSelectParams($p, true, true);

        $total = $this->getEntityManager()->getRepository($entityType)->count($selectParams);

        if (!$total || !$portion) {
            return 0;
        }

        $runNow = true;

        $offset = 0;
        $p['maxSize'] = $portion;

        $now = new DateTime();

        while ($offset <= $total) {
            $p['offset'] = $offset;

            $selectParams = $selectManger->getSelectParams($p, true, true);

            $collection = $this->getEntityManager()
                ->getRepository($entityType)
                ->find($selectParams);

            if ($runNow) {
                $manager = $this->getContactsManager();

                $result = $manager->pushContacts($collection, $userId, $externalAccount);

                $resultCount += $result->count;

                $runNow = false;
            }
            else {
                $ids = [];

                foreach ($collection as $entity) {
                    $ids[] = $entity->id;
                }

                $data = [
                    'ids' => $ids,
                    'userId' => $userId,
                    'entityType' => $entityType,
                ];

                $now->modify('+30 seconds');

                $job = $this->getEntityManager()->getEntity('Job');

                $job->set([
                    'methodName' => 'pushPortion',
                    'serviceName' => 'OutlookContacts',
                    'executeTime' => $now->format('Y-m-d H:i:s'),
                    'data' => $data,
                ]);

                $this->getEntityManager()->saveEntity($job);
            }

            $offset += $portion;
        }

        if ($result && count($result->leftIdList)) {
            $now->modify('+30 seconds');

            $this->getEntityManager()->createEntity('Job', [
                'methodName' => 'pushPortion',
                'serviceName' => 'OutlookContacts',
                'executeTime' => $now->format('Y-m-d H:i:s'),
                'data' => [
                    'ids' => $result->leftIdList,
                    'userId' => $userId,
                    'entityType' => $entityType,
                ],
            ]);
        }

        return $resultCount;
    }

    public function pushPortion($data)
    {
        if (is_array($data)) {
            $data = (object) $data;
        }

        $integrationEntity = $this->getEntityManager()->getEntity('Integration', 'Outlook');

        if (
            !$integrationEntity ||
            !$integrationEntity->get('enabled')
        ) {

            $GLOBALS['log']->error('Outlook Contacts Pushing : Integration Disabled');
            throw new Forbidden();
        }

        $userId = $data->userId;
        $entityType = $data->entityType;
        $ids = $data->ids;

        $externalAccount = $this->getEntityManager()->getEntity('ExternalAccount', 'Outlook__' . $userId);

        if (
            !$externalAccount ||
            !$externalAccount->get('enabled') ||
            !$externalAccount->get('outlookContactsEnabled')
        ) {
            $GLOBALS['log']->error('Outlook Contacts Pushing : Integration Disabled for User ' . $userId);

            throw new Forbidden();
        }

        $where = [
            [
                'type' => 'in',
                'field' => 'id',
                'value' => $ids,
            ]
        ];

        $user = $this->getEntityManager()->getEntity('User', $userId);

        if (!$user) {
            throw new Error();
        }

        $selectManger = $this->getSelectManagerFactory()->create($entityType, $user);

        $selectParams = $selectManger->getSelectParams(['where' => $where]);

        $collection = $this->getEntityManager()
            ->getRepository($entityType)
            ->find($selectParams);

        $manager = $this->getContactsManager();

        $result = $manager->pushContacts($collection, $userId, $externalAccount);

        if (count($result->leftIdList)) {
            $now = new DateTime();

            $now->modify('+60 seconds');

            $this->getEntityManager()->createEntity('Job', [
                'methodName' => 'pushPortion',
                'serviceName' => 'OutlookContacts',
                'executeTime' => $now->format('Y-m-d H:i:s'),
                'data' => [
                    'ids' => $result->leftIdList,
                    'userId' => $userId,
                    'entityType' => $entityType,
                ],
            ]);
        }

        return $result->count;
    }
}
