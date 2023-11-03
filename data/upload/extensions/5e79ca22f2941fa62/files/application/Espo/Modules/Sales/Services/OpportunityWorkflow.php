<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Sales Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/sales-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 * 
 * Copyright (C) 2015-2020 Letrium Ltd.
 * 
 * License ID: 2f687f2013bc552b8556948039df639e
 ***********************************************************************************/

namespace Espo\Modules\Sales\Services;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\NotFound;

use \Espo\ORM\Entity;

class OpportunityWorkflow extends \Espo\Core\Services\Base
{
    protected $entityType = 'Opportunity';

    protected function init()
    {
        parent::init();

        $this->addDependency('entityManager');
        $this->addDependency('serviceFactory');
        $this->addDependency('metadata');
        $this->addDependency('config');
        $this->addDependency('aclManager');
    }

    public function convertCurrency($workflowId, $entity, $data)
    {
        $config = $this->getInjection('config');

        $targetCurrency = isset($data->targetCurrency) ? $data->targetCurrency : $config->get('defaultCurrency');
        $baseCurrency = $config->get('baseCurrency');
        $rates = (object) ($config->get('currencyRates', []));

        if ($targetCurrency !== $baseCurrency && !property_exists($rates, $targetCurrency))
            throw new Error("Wokrflow convert currency: targetCurrency rate is not specified.");

        $entityManager = $this->getInjection('entityManager');

        $service = $this->getInjection('serviceFactory')->create($this->entityType);

        $reloadedEntity = $entityManager->getEntity($entity->getEntityType(), $entity->id);

        if (method_exists($service, 'getConvertCurrencyValues')) {
            $user = $this->getInjection('entityManager')->getEntity('User', 'system');

            $acl = new \Espo\Core\Acl($this->getInjection('aclManager'), $user);
            $service->setAcl($acl);
            $service->setUser($user);

            $values = $service->getConvertCurrencyValues($reloadedEntity, $targetCurrency, $baseCurrency, $rates, true);
            $reloadedEntity->set($values);

            if (count(get_object_vars($values))) {
                $entityManager->saveEntity($reloadedEntity, [
                    'skipWorkflow' => true, 'addItemList' => true, 'modifiedById' => 'system',
                ]);
            }
        }
    }
}
