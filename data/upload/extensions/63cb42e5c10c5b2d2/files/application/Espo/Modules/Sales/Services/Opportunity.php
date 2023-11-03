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
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: c235cfac520a05e355b12cda9ca78531
 ***********************************************************************************/

namespace Espo\Modules\Sales\Services;

use \Espo\ORM\Entity;

class Opportunity extends \Espo\Modules\Crm\Services\Opportunity
{
    protected $itemEntityType = 'OpportunityItem';

    protected $itemParentIdAttribute = 'opportunityId';

    public function loadAdditionalFields(Entity $entity)
    {
        parent::loadAdditionalFields($entity);

        $itemList = $this->getEntityManager()->getRepository($this->itemEntityType)->where([
            $this->itemParentIdAttribute => $entity->id
        ])->order('order')->find();

		$itemDataList = $itemList->toArray();
        foreach ($itemDataList as $i => $v) {
            $itemDataList[$i] = (object) $v;
        }
        $entity->set('itemList', $itemDataList);
    }

    public function getCopiedEntityAttributeItemList(Entity $entity)
    {
        $itemEntityType = $this->getEntityType() . 'Item';
        $link = lcfirst($this->getEntityType());
        $idAttribute = $link . 'Id';
        $nameAttribute = $link . 'Name';

        $itemList = $this->getEntityManager()->getRepository($itemEntityType)->where([
            $idAttribute => $entity->id
        ])->order('order')->find();

        $copiedItemList = [];
        foreach ($itemList as $item) {
            $arr = $item->toArray();
            $copiedItem = (object) $arr;
            $copiedItem->$idAttribute = null;
            $copiedItem->$nameAttribute = null;
            $copiedItemList[] = $copiedItem;
        }
        return $copiedItemList;
    }

    public function getConvertCurrencyValues(Entity $entity, string $targetCurrency, string $baseCurrency, $rates, bool $allFields = false, ?array $fieldList = null)
    {
        $data = parent::getConvertCurrencyValues($entity, $targetCurrency, $baseCurrency, $rates, $allFields, $fieldList);

        $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit');

        if (
            $allFields && !in_array('itemList', $forbiddenFieldList) &&
            (!$fieldList || in_array('amount', $fieldList))

        ) {
            $itemList = [];

            $itemService = $this->getServiceFactory()->create($this->itemEntityType);

            $itemFieldList = [];
            foreach ($this->getFieldManagerUtil()->getEntityTypeFieldList($this->itemEntityType) as $field) {
                if ($this->getMetadata()->get(['entityDefs', $this->itemEntityType, 'fields', $field, 'type']) !== 'currency') continue;
                $itemFieldList[] = $field;
            }

            $itemCollection = $this->getEntityManager()->getRepository($this->itemEntityType)->where([
                $this->itemParentIdAttribute => $entity->id
            ])->order('order')->find();

            foreach ($itemCollection as $item) {
                $values = $itemService->getConvertCurrencyValues($item, $targetCurrency, $baseCurrency, $rates, true, $itemFieldList);

                $item->set($values);
                $o = $item->getValueMap();
                $itemList[] = $o;
            }

            $data->itemList = $itemList;
        }

        return $data;
    }
}
