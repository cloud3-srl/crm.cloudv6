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

use Espo\ORM\Entity;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;

class OutlookMail extends \Espo\Core\Services\Base
{
    protected function init()
    {
        parent::init();
        $this->addDependency('externalAccountClientManager');
        $this->addDependency('acl');
    }

    public function processAccessCheck(string $entityType, string $id)
    {
        if ($this->getUser()->isAdmin()) {
            return;
        }

        if ($entityType === 'EmailAccount') {
            $record = $this->getEntityManager()->getEntity('EmailAccount', $id);

            if (!$record) {
                throw new Forbidden();
            }

            if (!$this->getInjection('acl')->check($record)) {
                throw new Forbidden();
            }

            return;
        }

        throw new Forbidden();
    }

    public function connect(string $entityType, string $id, string $code) : bool
    {
        $em = $this->getEntityManager();

        $this->getServiceFactory()
            ->create('ExternalAccount')
            ->authorizationCode('Outlook', $id, $code);

        if ($entityType === 'EmailAccount') {
            $imapHandler = 'Espo\\Modules\\Outlook\\Core\\Outlook\\ImapPersonalHandler';
            $smtpHandler = 'Espo\\Modules\\Outlook\\Core\\Outlook\\SmtpPersonalHandler';
        }
        else {
            $imapHandler = 'Espo\\Modules\\Outlook\\Core\\Outlook\\ImapGroupHandler';
            $smtpHandler = 'Espo\\Modules\\Outlook\\Core\\Outlook\\SmtpGroupHandler';
        }

        $inboundEmail = $em->getRepository($entityType)->get($id);

        if ($inboundEmail) {
            $inboundEmail->set('imapHandler', $imapHandler);
            $inboundEmail->set('smtpHandler', $smtpHandler);

            $em->saveEntity($inboundEmail);
        }

        return true;
    }

    public function disconnect(string $entityType, string $id)
    {
        $em = $this->getEntityManager();

        $ea = $em->getRepository('ExternalAccount')->get('Outlook__' . $id);

        if ($ea) {
            $ea->set([
                'accessToken' => null,
                'refreshToken' => null,
                'tokenType' => null,
                'enabled' => false,
            ]);
            $em->saveEntity($ea, ['silent' => true]);
        }

        $inboundEmail = $em->getRepository($entityType)->get($id);

        if ($inboundEmail) {
            $inboundEmail->set('imapHandler', null);
            $inboundEmail->set('smtpHandler', null);

            $em->saveEntity($inboundEmail);
        }

        return true;
    }

    public function ping(string $entityType, string $id)
    {
        $integration = $this->getEntityManager()->getEntity('ExternalAccount', 'Outlook__' . $id);

        if (!$integration) {
            return false;
        }

        try {
            $client = $this->getInjection('externalAccountClientManager')->create('Outlook', $id);

            if ($client) {
                return $client->getMailClient()->productPing();
            }
        } catch (\Exception $e) {}

        return false;
    }
}
