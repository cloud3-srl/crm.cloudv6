<?php

namespace Espo\Modules\Cloud3\Hooks;

use Espo\ORM\Entity;

class CaseObj extends \Espo\Core\Hooks\Base
{
    public function beforeSave(Entity $entity)
    {
        if (!$entity->has('itemList')) {
            return;
        }

        $itemList = $entity->get('itemList');

        if (!is_array($itemList)) {
            return;
        }

        if ($entity->has('amountCurrency')) {
            foreach ($itemList as $o) {
                $o->unitPriceCurrency = $entity->get('amountCurrency');
                $o->amountCurrency = $entity->get('amountCurrency');
            }
        }

        foreach ($itemList as $o) {
            if (!isset($o->quantity)) {
                $o->quantity = 1;
            }
            if (!isset($o->amount) && isset($o->unitPrice)) {
                $o->amount = $o->unitPrice * $o->quantity;
            }
        }

        if (count($itemList)) {
            $amount = 0.0;
            foreach ($itemList as $o) {
                $amount += $o->amount;
            }
            $amount = round($amount, 2);
            $entity->set('amount', $amount);
        }
    }

    public function afterSave(Entity $entity, array $options = [])
    {
        if (!empty($options['skipWorkflow']) && empty($options['addItemList'])) {
            return;
        }

        if (!$entity->has('itemList')) {
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
            $prevItemCollection = $this->getEntityManager()->getRepository('CaseItem')->where([
                'caseId' => $entity->id
            ])->order('order')->find();
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
                        $this->setItemWithData($item, $o);
                        $item->set('order', $order);
                        $item->set('caseId', $entity->id);
                        $exists = true;
                        $toUpdateList[] = $item;
                        break;
                    }
                }
            }

            if (!$exists) {
                $item = $this->getEntityManager()->getEntity('CaseItem');
                $this->setItemWithData($item, $o);
                $item->set('order', $order);
                $item->set('caseId', $entity->id);
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


        $itemCollection = $this->getEntityManager()->getRepository('CaseItem')->where([
            'caseId' => $entity->id
        ])->order('order')->find();

        $entity->set('itemList', $itemCollection->toArray());
    }

    protected function setItemWithData(Entity $item, \StdClass $o)
    {
        $data = [
            'id' => $o->id,
            'name' => $this->getAttributeFromItemObject($o, 'name'),
            'unitPrice' => $this->getAttributeFromItemObject($o, 'unitPrice'),
            'unitPriceCurrency' => $this->getAttributeFromItemObject($o, 'unitPriceCurrency'),
            'amount' => $this->getAttributeFromItemObject($o, 'amount'),
            'amountCurrency' => $this->getAttributeFromItemObject($o, 'amountCurrency'),
            'productId' => $this->getAttributeFromItemObject($o, 'productId'),
            'productName' => $this->getAttributeFromItemObject($o, 'productName'),
            'quantity' => $this->getAttributeFromItemObject($o, 'quantity', 1),
            'description' => $this->getAttributeFromItemObject($o, 'description'),
        ];

        foreach (get_object_vars($o) as $attribute => $value) {
            if (array_key_exists($attribute, $data)) continue;
            $data[$attribute] = $value;
        }

        $item->set($data);
    }

    protected function getAttributeFromItemObject($o, $attribute, $defaultValue = null)
    {
        return isset($o->$attribute) ? $o->$attribute : $defaultValue;
    }
}
