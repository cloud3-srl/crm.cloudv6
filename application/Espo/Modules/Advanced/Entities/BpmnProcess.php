<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2022 Letrium Ltd.
 *
 * License ID: a3ea4219cf9c3e5dee57026de28a15c1
 ***********************************************************************************/

namespace Espo\Modules\Advanced\Entities;

use Espo\Modules\Advanced\Entities\BpmnFlowNode;

class BpmnProcess extends \Espo\Core\ORM\Entity
{
    /**
     * @return bool
     */
    public function isSubProcess()
    {
        return $this->hasParentProcess();
    }

    /**
     * @return bool
     */
    public function hasParentProcess()
    {
        return $this->get('parentProcessId') && $this->get('parentProcessFlowNodeId');
    }

    /**
     * @param bool $notSorted
     * @return string[]
     */
    public function getElementIdList($notSorted = false)
    {
        $elementsDataHash = $this->get('flowchartElementsDataHash');

        if (!$elementsDataHash) {
            $elementsDataHash = (object) [];
        }

        $elementIdList = array_keys(get_object_vars($elementsDataHash));

        if ($notSorted) {
            return $elementIdList;
        }

        usort($elementIdList, function ($id1, $id2) use ($elementsDataHash) {
            $item1 = $elementsDataHash->$id1;
            $item2 = $elementsDataHash->$id2;

            if (isset($item1->center) && isset($item2->center)) {
                if ($item1->center->y > $item2->center->y) {
                    return true;
                }

                if ($item1->center->y == $item2->center->y) {
                    if ($item1->center->x > $item2->center->x) {
                        return true;
                    }
                }
            }
        });

        return $elementIdList;
    }

    /**
     * @param string $id
     * @return \stdClass
     */
    public function getElementDataById($id)
    {
        if (!$id) {
            return null;
        }

        $elementsDataHash = $this->get('flowchartElementsDataHash');

        if (!$elementsDataHash) {
            $elementsDataHash = (object) [];
        }

        if (!property_exists($elementsDataHash, $id)) {
            return null;
        }

        return $elementsDataHash->$id;
    }

    /**
     * @return string[]
     */
    public function getAttachedToFlowNodeElementIdList(BpmnFlowNode $flowNode)
    {
        $elementIdList = [];

        foreach ($this->getElementIdList() as $id) {
            $item = $this->getElementDataById($id);

            if (!isset($item->attachedToId)) {
                continue;
            }

            if ($item->attachedToId === $flowNode->get('elementId')) {
                $elementIdList[] = $id;
            }
        }

        return $elementIdList;
    }
}
