<?php

namespace Espo\Custom\Services;

use \Espo\ORM\Entity;

class CaseObj extends \Espo\Modules\Crm\Services\CaseObj
{
    protected $itemEntityType = 'CaseItem';

    protected $itemParentIdAttribute = 'caseId';

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
        return $itemEntityType;
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

    public function getAttributesFromAnotherRecord($sourceType, $sourceId)
    {
        $source = $this->getEntityManager()->getEntity($sourceType, $sourceId);

        if (!$source) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($source, 'read')) {
            throw new Forbidden();
        }

        $source->loadLinkMultipleField('teams');

        $attributes = [
            'name' => $source->get('name'),
            'accountId' => $source->get('accountId'),
            'accountName' => $source->get('accountName'),
            'teamsIds' => $source->get('teamsIds'),
            'teamsNames' => $source->get('teamsNames'),
            'description' => $source->get('description'),
            'contactId' => $source->get('contactId'),
            'contactName' => $source->get('contactName'),
            'callId' => $sourceId,
            'callName' => $source->get('name')
        ];
        return $attributes;
    }
}
