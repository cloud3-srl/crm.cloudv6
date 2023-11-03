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
 * Copyright (C) 2015-2022 Letrium Ltd.
 *
 * License ID: c235cfac520a05e355b12cda9ca78531
 ***********************************************************************************/

namespace Espo\Modules\Sales\Repositories;

use Espo\ORM\Entity;

use Espo\Core\ORM\Repositories\RDB;

class Quote extends RDB
{
    protected $itemEntityType = 'QuoteItem';

    protected $itemParentIdAttribute = 'quoteId';

    protected function beforeSave(Entity $entity, array $options = [])
    {
        $this->processItemsBeforeSave($entity, $options);

        parent::beforeSave($entity, $options);

        if (!$entity->get('accountId')) {
            $opportunityId = $entity->get('opportunityId');

            if ($opportunityId) {
                $opportunity = $this->getEntityManager()->getEntity('Opportunity', $opportunityId);

                if ($opportunity) {
                    $accountId = $opportunity->get('accountId');

                    if ($accountId) {
                        $entity->set('accountId', $accountId);
                    }
                }
            }
        }

        if (
            $entity->isNew() &&
            $this->getMetadata()->get(['entityDefs', $this->entityType, 'fields', 'number', 'useAutoincrement'])
        ) {
            if (!$entity->get('number')) {
                $entity->set('number', $entity->get('numberA'));
            }
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        $this->processItemsAfterSave($entity, $options);

        parent::afterSave($entity, $options);
    }

    protected function processItemsBeforeSave(Entity $entity, array $options = [])
    {
        $itemList = $entity->get('itemList');

        if ($entity->has('itemList') && is_array($itemList)) {
            foreach ($itemList as $i => $o) {
                if (is_array($o)) {
                    $o = (object) $o;

                    $itemList[$i] = $o;

                    $entity->set('itemList', $itemList);
                }
            }

            $this->calculateItems($entity, $options);

            return;
        }

        if ($entity->isAttributeChanged('shippingCost')) {
            $this->loadItemListField($entity);

            $this->calculateItems($entity);
        }
    }

    public function loadItemListField(Entity $entity): void
    {
        $items = $this->getEntityManager()
            ->getRepository($this->itemEntityType)
            ->where([
                $this->itemParentIdAttribute => $entity->id,
            ])
            ->order('order')
            ->find();

        foreach ($items as $item) {
            $item->loadAllLinkMultipleFields();
        }

        $entity->set('itemList', $items->getValueMapList());
    }

    protected function calculateItems(Entity $entity, array $options = [])
    {
        $itemList = $entity->get('itemList');

        if ($entity->has('amountCurrency')) {
            foreach ($itemList as $o) {
                $o->listPriceCurrency = $entity->get('amountCurrency');
                $o->unitPriceCurrency = $entity->get('amountCurrency');
                $o->amountCurrency = $entity->get('amountCurrency');
            }
        }

        foreach ($itemList as $i => $o) {
            if (!isset($o->quantity)) {
                $o->quantity = 1;
            }

            if (!isset($o->amount) && isset($o->unitPrice)) {
                $o->amount = $o->unitPrice * $o->quantity;
            }
        }

        $accountId = $entity->get('accountId');
        $accountName = $entity->get('accountName');

        $discountAmount = 0.0;
        $taxAmount = 0.0;

        foreach ($itemList as $o) {
            if (!property_exists($o, 'unitWeight')) {
                $o->unitWeight = null;
            }

            if ($o->unitWeight === null && $entity->isNew()) {
                if (!empty($o->productId)) {
                    $product = $this->getEntityManager()->getEntity('Product', $o->productId);

                    if ($product) {
                        $o->unitWeight = $product->get('weight');
                    }
                }
            }

            if ($o->unitWeight !== null) {
                $o->weight = $o->unitWeight * $o->quantity;
            } else {
                $o->weight = null;
            }

            $o->accountId = $accountId;
            $o->accountName = $accountName;

            $o->discount = 0.0;

            if (isset($o->unitPrice) && isset($o->listPrice)) {
                if ($o->listPrice) {
                    $o->discount = (($o->listPrice - $o->unitPrice) / $o->listPrice) * 100.0;
                    $o->discount = round($o->discount, 2);

                    $discountAmount += ($o->listPrice - $o->unitPrice) * $o->quantity;
                }

                if (!empty($o->taxRate)) {
                    $taxAmount += $o->unitPrice * $o->quantity * $o->taxRate / 100.0;
                }
            }
        }

        if (count($itemList)) {
            $amount = 0.0;
            $weight = 0.0;

            foreach ($itemList as $o) {
                $amount += $o->amount;

                if (!is_null($o->weight)) {
                    $weight += $o->weight;
                }
            }

            $entity->set('amount', $amount);
            $entity->set('weight', $weight);

            $shippingCost = $entity->get('shippingCost');

            if (!$shippingCost) {
                $shippingCost = 0;
            }

            $entity->set('grandTotalAmount', $amount + $taxAmount + $shippingCost);
        }
        else {
            $entity->set('grandTotalAmount', $entity->get('amount'));
        }

        $entity->set('itemList', $itemList);

        $entity->set('discountAmount', $discountAmount);
        $entity->set('taxAmount', $taxAmount);
        $entity->set('preDiscountedAmount', $entity->get('amount') + $discountAmount);

        if ($entity->has('amountCurrency')) {
            $entity->set('discountAmountCurrency', $entity->get('amountCurrency'));
            $entity->set('grandTotalAmountCurrency', $entity->get('amountCurrency'));
            $entity->set('taxAmountCurrency', $entity->get('amountCurrency'));
            $entity->set('preDiscountedAmountCurrency', $entity->get('amountCurrency'));
        }
    }

    protected function processItemsAfterSave(Entity $entity, array $options = [])
    {
        if (!empty($options['skipWorkflow']) && empty($options['addItemList'])) {
            return;
        }

        if (!$entity->has('itemList')) {
            if ($entity->isAttributeChanged('accountId')) {
                $quoteItemList = $this->getEntityManager()
                    ->getRepository($this->itemEntityType)
                    ->where([
                        $this->itemParentIdAttribute => $entity->id
                    ])
                    ->find();

                foreach ($quoteItemList as $item) {
                    $item->set('accountId', $entity->get('accountId'));

                    $this->getEntityManager()->saveEntity($item);
                }
            }

            return;
        }

        $itemList = $entity->get('itemList');

        if (!is_array($itemList)) {
            return;
        }

        $toCreateList = [];
        $toUpdateList = [];
        $toRemoveList = [];

        if (!$entity->isNew()) {
            $prevItemCollection = $this->getEntityManager()
                ->getRepository($this->itemEntityType)
                ->where([
                    $this->itemParentIdAttribute => $entity->id
                ])
                ->order('order')
                ->find();

            foreach ($prevItemCollection as $item) {
                $exists = false;

                foreach ($itemList as $data) {
                    if ($item->id === $data->id) {
                        $exists = true;
                    }
                }

                if (!$exists) {
                    $toRemoveList[] = $item;
                }
            }
        }

        $order = 0;

        foreach ($itemList as $o) {
            $order++;
            $exists = false;

            if (!$entity->isNew()) {
                foreach ($prevItemCollection as $item) {
                    if ($o->id === $item->id) {
                        $isChanged = false;

                        foreach (get_object_vars($o) as $k => $v) {
                            if (
                                is_numeric($v) && is_numeric($item->get($k)) && abs($v - $item->get($k)) > 0.00001
                                ||
                                (!is_numeric($v) || !is_numeric($item->get($k))) && $v !== $item->get($k)
                            ) {
                                $isChanged = true;
                                break;
                            }
                        }

                        if (!$isChanged && $item->get('order') !== $order) {
                            $isChanged = true;
                        }

                        $exists = true;

                        if (!$isChanged) {
                            break;
                        }

                        $this->setItemWithData($item, $o);
                        $item->set('order', $order);
                        $item->set($this->itemParentIdAttribute, $entity->id);

                        $toUpdateList[] = $item;

                        break;
                    }
                }
            }

            if (!$exists) {
                $item = $this->getEntityManager()->getEntity($this->itemEntityType);

                $this->setItemWithData($item, $o);

                $item->set('order', $order);
                $item->set($this->itemParentIdAttribute, $entity->id);

                $item->id = null;

                $toCreateList[] = $item;
            }
        }

        if ($entity->isNew()) {
            foreach ($toUpdateList as $item) {
                $item->id = null;

                $toCreateList[] = $item;
            }

            $toUpdateList = [];
        }

        foreach ($toRemoveList as $item) {
            $this->getEntityManager()->removeEntity($item);
        }

        foreach ($toUpdateList as $item) {
            $this->getEntityManager()->saveEntity($item);
        }

        foreach ($toCreateList as $item) {
            $this->getEntityManager()->saveEntity($item);
        }

        $itemCollection = $this->getEntityManager()
            ->getRepository($this->itemEntityType)
            ->where([
                $this->itemParentIdAttribute => $entity->id
            ])
            ->order('order')
            ->find();

        foreach ($itemCollection as $item) {
            $item->loadAllLinkMultipleFields();
        }

        if (method_exists($itemCollection, 'getValueMapList')) {
            $entity->set('itemList', $itemCollection->getValueMapList());
        }
        else {
            $entity->set('itemList', $itemCollection->toArray());
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $quoteItemList = $this->getEntityManager()
            ->getRepository($this->itemEntityType)
            ->where([
                $this->itemParentIdAttribute => $entity->id
            ])
            ->find();

        foreach ($quoteItemList as $item) {
            $this->getEntityManager()->removeEntity($item);
        }
    }

    protected function setItemWithData(Entity $item, \StdClass $o)
    {
        $data = [
            'id' => isset($o->id) ? $o->id : null,
            'name' => $this->getAttributeFromItemObject($o, 'name'),
            'listPrice' => $this->getAttributeFromItemObject($o, 'listPrice'),
            'listPriceCurrency' => $this->getAttributeFromItemObject($o, 'listPriceCurrency'),
            'unitPrice' => $this->getAttributeFromItemObject($o, 'unitPrice'),
            'unitPriceCurrency' => $this->getAttributeFromItemObject($o, 'unitPriceCurrency'),
            'amount' => $this->getAttributeFromItemObject($o, 'amount'),
            'amountCurrency' => $this->getAttributeFromItemObject($o, 'amountCurrency'),
            'taxRate' => $this->getAttributeFromItemObject($o, 'taxRate'),
            'productId' => $this->getAttributeFromItemObject($o, 'productId'),
            'productName' => $this->getAttributeFromItemObject($o, 'productName'),
            'quantity' => $this->getAttributeFromItemObject($o, 'quantity', 1),
            'unitWeight' => $this->getAttributeFromItemObject($o, 'unitWeight'),
            'weight' => $this->getAttributeFromItemObject($o, 'weight'),
            'description' => $this->getAttributeFromItemObject($o, 'description'),
            'discount' => $this->getAttributeFromItemObject($o, 'discount'),
            'accountId' => $this->getAttributeFromItemObject($o, 'accountId'),
            'accountName' => $this->getAttributeFromItemObject($o, 'accountName'),
        ];

        $ignoreAttributeList = [
            'id',
            'name',
            'createdAt',
            'modifiedAt',
            'createdById',
            'createdByName',
            'modifiedById',
            'modifiedByName',
            $this->itemParentIdAttribute,
            'listPriceConverted',
            'unitPriceConverted',
            'amountConverted',
            'deleted',
        ];

        $productAttributeList = $this->getEntityManager()
            ->getEntity('Product')
            ->getAttributeList();

        foreach ($productAttributeList as $attribute) {
            if (in_array($attribute, $ignoreAttributeList) || array_key_exists($attribute, $data)) {
                continue;
            }

            if (!$item->hasAttribute($attribute)) {
                continue;
            }

            $item->set($attribute, $this->getAttributeFromItemObject($o, $attribute));

            if (
                $item->getAttributeType($attribute) === Entity::BOOL &&
                $item->get($attribute) === null
            ) {
                $item->set($attribute, false);
            }
        }

        foreach (get_object_vars($o) as $attribute => $value) {
            if (array_key_exists($attribute, $data)) {
                continue;
            }

            if (in_array($attribute, $ignoreAttributeList)) {
                continue;
            }

            $data[$attribute] = $value;
        }

        $item->set($data);
    }

    protected function getAttributeFromItemObject($o, $attribute, $defaultValue = null)
    {
        return isset($o->$attribute) ? $o->$attribute : $defaultValue;
    }
}
