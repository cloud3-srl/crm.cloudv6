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

class SmtpGroupHandler extends \Espo\Core\Injectable
{
    protected $entityType = 'InboundEmail';

    protected function init()
    {
        $this->addDependency('externalAccountClientManager');
        $this->addDependency('entityManager');
    }

    protected function getExternalAccountClientManager()
    {
        return $this->getInjection('externalAccountClientManager');
    }

    public function applyParams(string $id, array &$params)
    {
        $inboundEmail = $this->getInjection('entityManager')->getRepository($this->entityType)->get($id);

        if (!$inboundEmail) {
            throw new Error("SmtpHandler: {$this->entityType} {$id} not found.");
        }

        $username = $inboundEmail->get('smtpUsername');

        if (!$username) {
            throw new Error("SmtpHandler: No 'smtpUsername'.");
        }

        $client = $this->getExternalAccountClientManager()->create('Outlook', $id);

        if (!$client) {
            return;
        }

        if (!$client->getParam('expiresAt')) {
            // for backward compatibility
            $client->getMailClient()->productPing();
            $accessToken = $client->getMailClient()->getParam('accessToken');
        } else {
            $client->handleAccessTokenActuality();
            $accessToken = $client->getParam('accessToken');
        }

        if (!$accessToken) {
            return;
        }

        $authString = base64_encode("user={$username}\1auth=Bearer {$accessToken}\1\1");

        $params['smtpAuthClassName'] = '\\Espo\\Modules\\Outlook\\Core\\Outlook\\Smtp\\Auth\\Xoauth';
        $params['connectionOptions'] = [
            'authString' => $authString
        ];
    }
}
