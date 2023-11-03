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

class ImapGroupHandler extends \Espo\Core\Injectable
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

    public function prepareProtocol(string $id, array $params)
    {
        $inboundEmail = $this->getInjection('entityManager')->getRepository($this->entityType)->get($id);

        if (!$inboundEmail) throw new Error("ImapHandler: {$this->entityType} {$id} not found.");

        $username = $inboundEmail->get('username');

        if (!$username) throw new Error("ImapHandler: No username.");

        $client = $this->getExternalAccountClientManager()->create('Outlook', $id);
        if (!$client) return;

        if (!$client->getParam('expiresAt')) {
            // for backward compatibility
            $client->getMailClient()->productPing();
            $accessToken = $client->getMailClient()->getParam('accessToken');
        } else {
            $client->handleAccessTokenActuality();
            $accessToken = $client->getParam('accessToken');
        }

        if (!$accessToken) return;

        $ssl = false;
        if (!empty($params['ssl'])) {
            $ssl = 'SSL';
        }

        if (!empty($params['security'])) {
            $ssl = $params['security'];
        }

        $protocol = new \Laminas\Mail\Protocol\Imap($params['host'], $params['port'], $ssl);

        $authString = base64_encode("user={$username}\1auth=Bearer {$accessToken}\1\1");

        $authenticateParams = ['XOAUTH2', $authString];
        $protocol->sendRequest('AUTHENTICATE', $authenticateParams);

        $i = 0;
        while (true) {
            if ($i === 10) return;

            $response = '';
            $isPlus = $protocol->readLine($response, '+', true);

            if ($isPlus) {
                $GLOBALS['log']->error("Outlook Imap: Extra server challenge: " . $response);
                $protocol->sendRequest('');
            } else {
                if (
                    preg_match('/^NO /i', $response) ||
                    preg_match('/^BAD /i', $response)
                ) {
                    $GLOBALS['log']->error("Outlook Imap: Failure: " . $response);
                    return;
                } else if (preg_match("/^OK /i", $response)) {
                    break;
                }
            }

            $i++;
        }

        return $protocol;
    }
}
