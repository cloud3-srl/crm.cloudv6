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
use Espo\Modules\Outlook\Entities\OutlookCalendarUser;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;


class OutlookCalendar extends \Espo\Services\Record
{
    private $calendarManager;

    protected $forceSelectAllAttributes = true;

    protected function init()
    {
        parent::init();
        $this->addDependency('entityManager');
        $this->addDependency('container');
        $this->addDependency('metadata');
        $this->addDependency('aclManager');
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

    protected function getLanguage()
    {
        return $this->getInjection('container')->get('defaultLanguage');
    }

    protected function getCalendarManager()
    {
        if (!$this->calendarManager) {
            $this->calendarManager = new \Espo\Modules\Outlook\Core\Outlook\CalendarManager($this->getContainer());
        }
        return $this->calendarManager;
    }

    public function usersCalendars(array $params = null)
    {
        return $this->getCalendarManager()->getCalendarList($this->getUser()->id);
    }

    public function syncCalendar(OutlookCalendarUser $calendarUser)
    {
        $userId = $calendarUser->get('userId');
        if (!$userId) return;

        $user = $this->getEntityManager()->getEntity('User', $userId);
        if (!$user) return;

        $externalAccount = $this->getEntityManager()->getEntity('ExternalAccount', 'Outlook__' . $userId);

        if (!$externalAccount) return;
        if (!$externalAccount->get('enabled') || !$externalAccount->get('outlookCalendarEnabled')) return;

        if (!$this->getInjection('aclManager')->check($user, 'OutlookCalendar')) return;

        $isConnected = $this->getServiceFactory()->create('ExternalAccount')->ping('Outlook', $userId);

        if (!$isConnected) {
            $n = $this->getEntityManager()->getRepository('Notification')->where([
                'relatedType' => $externalAccount->getEntityType(),
                'createdAt>=' => (new \DateTime())->modify('-1 day')->format('Y-m-d H:i:s'),
                'userId' => $userId,
            ])->select(['id'])->findOne();
            if (!$n) {
                $this->getEntityManager()->createEntity('Notification', [
                    'type' => 'System',
                    'message' => $this->getLanguage()->translate('calendarConnectionProblem', 'messages', 'OutlookCalendar'),
                    'userId' => $userId,
                    'relatedType' => $externalAccount->getEntityType(),
                ]);
            }

            $GLOBALS['log']->error('Outlook Calendar Sync: ' . $calendarUser->get('userName') . ' could not connect to Outlook Server while trying to sync calendar ' . $calendarUser->get('calendarName') . '.');
            return false;
        }

        if (!$this->getAclManager()->checkScope($user, 'OutlookCalendar')) {
            $GLOBALS['log']->info("Outlook Calendar Sync: Access forbidden for user ".$calendarUser->get('userId').".");
            return false;
        }

        $externalAccount = $this->getEntityManager()->getEntity('ExternalAccount', $externalAccount->id);

        $calendarManager = $this->getCalendarManager();

        $calendarManager->runSync($calendarUser, $externalAccount);
    }
}
