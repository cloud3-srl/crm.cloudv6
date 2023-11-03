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

namespace Espo\Modules\Advanced\Core;

class Helper
{
    private $container;

    public function __construct(\Espo\Core\Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    public function getInfo()
    {
        $pdo = $this->getContainer()->get('entityManager')->getPDO();

        $query = "SELECT * FROM extension WHERE name='Advanced Pack' AND deleted=0 ORDER BY created_at DESC LIMIT 0,1";
        $sth = $pdo->prepare($query);
        $sth->execute();

        $data = $sth->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($data)) {
            $data = array();
        }

        $data['lid'] = 'a3ea4219cf9c3e5dee57026de28a15c1';

        $query = "SELECT * FROM extension WHERE name='Advanced Pack' ORDER BY created_at ASC LIMIT 0,1";
        $sth = $pdo->prepare($query);
        $sth->execute();
        $row = $sth->fetch(\PDO::FETCH_ASSOC);
        if (isset($row['created_at'])) {
            $data['installedAt'] = $row['created_at'];
        }

        return $data;
    }
}