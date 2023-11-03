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

namespace Espo\Modules\Advanced\Core\Loaders;

use Espo\Modules\Advanced\Core\SignalManager as Service;

class SignalManager extends \Espo\Core\Loaders\Base
{
    public function load(): Service
    {
        return new Service(
            $this->getContainer()->get('entityManager'),
            $this->getContainer()->get('workflowManager'),
            $this->getContainer()->get('config')
        );
    }
}
