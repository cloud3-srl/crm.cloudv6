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

namespace Espo\Modules\Sales\Core;

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

        $query = "SELECT * FROM extension WHERE name='Sales Pack' AND deleted=0 ORDER BY created_at DESC LIMIT 0,1";
        $sth = $pdo->prepare($query);
        $sth->execute();

        $data = $sth->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($data)) {
            $data = array();
        }

        $data['lid'] = '2f687f2013bc552b8556948039df639e';

        $query = "SELECT * FROM extension WHERE name='Sales Pack' ORDER BY created_at ASC LIMIT 0,1";
        $sth = $pdo->prepare($query);
        $sth->execute();
        $row = $sth->fetch(\PDO::FETCH_ASSOC);
        if (isset($row['created_at'])) {
            $data['installedAt'] = $row['created_at'];
        }

        return $data;
    }
}
