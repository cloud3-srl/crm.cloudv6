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

namespace Espo\Modules\Sales\Classes\Pdf\Quote;

use Espo\ORM\EntityManager;
use Espo\ORM\Entity;

use Espo\Tools\Pdf\Data\DataLoader;
use Espo\Tools\Pdf\Params;

use stdClass;

class ItemListDataLoader implements DataLoader
{
    private $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function load(Entity $entity, Params $params): stdClass
    {
        $itemEntityType = $entity->getEntityType() . 'Item';
        $itemParentIdAttribute = lcfirst($entity->getEntityType()) . 'Id';

        $itemList = $this->entityManager
            ->getRepository($itemEntityType)
            ->where([
                $itemParentIdAttribute => $entity->id
            ])
            ->order('order')
            ->find();

        foreach ($itemList as $item) {
            $item->loadAllLinkMultipleFields();
        }

        return (object) [
            'itemList' => $itemList,
        ];
    }
}
