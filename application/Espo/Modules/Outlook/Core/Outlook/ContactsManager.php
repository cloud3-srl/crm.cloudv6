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
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\Container;

use Espo\Entities\ExternalAccount;

class ContactsManager
{
    protected $container;

    protected $entityManager;

    protected $metadata;

    protected $config;

    protected $selectManagerFactory;

    private $userClientMap = [];

    private $userMap = [];

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
                ->getContactsClient();
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

    public function getContactFolderList($userId, $params = [])
    {
        $list = [];
        $response = $this->getUserClient($userId)->getContactFolderList($params);

        if (is_array($response) && isset($response['value'])) {
            foreach ($response['value'] as $item) {
                $list[] = [
                    'id' => $item['Id'] ?? $item['id'] ,
                    'name' => $item['DisplayName'] ?? $item['displayName'],
                ];
            }
         }

         return $list;
    }

    /**
     * @return stdClass
     */
    public function pushContacts($collection, string $userId, ExternalAccount $externalAccount)
    {
        $folderId = $externalAccount->get('contactFolderId') ?? null;

        $outlookUserId = $externalAccount->get('outlookUserId');

        $this->getUserClient($userId)->ping();

        $count = 0;

        $dataList = [];

        foreach ($collection as $entity) {
            $item = [];

            $relationEntity = $this->entityManager
                ->getRepository('OutlookContactsEntity')
                ->where([
                    'entityId' => $entity->id,
                    'entityType' => $entity->getEntityType(),
                    'outlookUserId' => $outlookUserId,
                ])
                ->findOne();

            $item['toUpdate'] = !!$relationEntity;

            if ($relationEntity) {
                $item['relationEntity'] = $relationEntity;
            }

            if ($relationEntity) {
                $item['contactId'] = $relationEntity->get('contactId');
            }

            if (!$relationEntity) {
                $item['contactFolderId'] = $folderId;
            }

            $item['entity'] = $entity;

            $dataList[] = $item;
        }

        $batchHash = [];

        $requestItemList = [];

        $counter = 1;

        foreach ($dataList as $item) {
            $entity = $item['entity'];

            $payloadItem = (object) [
                'GivenName' => $entity->get('firstName'),
                'Surname' => $entity->get('lastName'),
            ];

            if ($entity->get('accountName')) {
                $payloadItem->CompanyName = $entity->get('accountName');
            }

            if ($entity->get('emailAddress')) {
                $payloadItem->EmailAddresses = [
                    [
                        'Address' => $entity->get('emailAddress'),
                        'Name' => $entity->get('name'),
                    ]
                ];
            }

            if ($entity->get('phoneNumber')) {
                $payloadItem->BusinessPhones = [
                    $entity->get('phoneNumber')
                ];
            }

            if (!$item['toUpdate']) {
                if (empty($item['contactFolderId'])) {
                    $url = 'contacts';
                } else {
                    $url = "contactFolders('" . $item['contactFolderId'] ."')/contacts";
                }

                $requestItemList[] = (object) [
                    'id' => strval($counter),
                    'method' => 'POST',
                    'url' => '/me/' . $url,
                    'headers' => (object) [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => $payloadItem,
                ];

                $batchHash[strval($counter)] = [
                    'type' => 'POST',
                    'entity' => $item['entity'],
                ];
            }
            else {
                $url = 'contacts/' . $item['contactId'];

                $requestItemList[] = (object) [
                    'id' => strval($counter),
                    'method' => 'PATCH',
                    'url' => '/me/' . $url,
                    'headers' => (object) [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => $payloadItem,
                ];

                $batchHash[strval($counter)] = [
                    'type' => 'PATCH',
                    'entity' => $item['entity'],
                    'relationEntity' => $item['relationEntity'],
                ];
            }

            $counter++;
        }

        if (!count($requestItemList)) {
            return (object) [
                'count' => 0,
                'leftIdList' => [],
            ];
        }

        $resultList = $this->getUserClient($userId)->batchRequest($requestItemList);

        $leftIdList = [];

        //$reCreateList = [];

        if (count($resultList) !== count($requestItemList)) {
            throw new Error("Outlook Contacts sync: Bad batch response. Doesn't match request.");
        }

        foreach ($resultList as $i => $item) {
            $id = $item['id'] ?? null;

            if (!$id) {
                $GLOBALS['log']->warning("Outlook Contacts sync: No ID in batch response item.");

                continue;
            }

            $requestItem = $batchHash[$id] ?? null;

            if (!$requestItem) {
                $GLOBALS['log']->warning("Outlook Contacts sync: Bad ID in batch response item.");

                continue;
            }

            if ($item['status'] === 429) {
                $leftIdList[] = $requestItem['entity']->id;

                continue;
            }

            if ($requestItem['type'] === 'POST') {
                if ($item['status'] === 201) {
                    $count++;

                    $responseData = $item['body'] ?? null;

                    if (!$responseData) {
                        $GLOBALS['log']->warning("Outlook Contacts sync: No body returned.");

                        continue;
                    }

                    if (isset($responseData['id'])) {
                        $this->entityManager->createEntity('OutlookContactsEntity', [
                            'entityId' => $requestItem['entity']->id,
                            'entityType' => $requestItem['entity']->getEntityType(),
                            'outlookUserId' => $outlookUserId,
                            'contactId' => $responseData['id'],
                            'userId' => $userId,
                        ]);
                    }
                }

                continue;
            }

            if ($requestItem['type'] === 'PATCH') {
                if ($item['status'] === 404) {
                    $this->entityManager->removeEntity($requestItem['relationEntity']);

                    //$reCreateList[] = $requestItem['entity'];

                    $leftIdList[] = $requestItem['entity']->id;

                    continue;
                }

                if ($item['status'] === 200) {
                    $count++;
                }

                continue;
            }
        }

        /*if (count($reCreateList)) {
             $count += $this->pushContacts($reCreateList, $userId, $externalAccount);
        }*/

        return (object) [
            'count' => $count,
            'leftIdList' => $leftIdList,
        ];
    }
}
