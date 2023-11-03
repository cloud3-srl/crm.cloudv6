<?php

namespace Espo\Modules\Cloud3\Services;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\NotFound;

use \Espo\ORM\Entity;

class CaseWorkflow extends \Espo\Core\Services\Base
{
    protected $entityType = 'Case';

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
