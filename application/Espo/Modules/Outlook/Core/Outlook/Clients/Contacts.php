<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Outlook Integration
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/outlook-extension-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2022 Letrium Ltd.
 *
 * License ID: 26bfa1fab74a68212506685b1b343192
 ***********************************************************************************/

namespace Espo\Modules\Outlook\Core\Outlook\Clients;

class Contacts extends Outlook
{
    protected $baseUrl = 'https://graph.microsoft.com/v1.0/me/';

    protected function getPingUrl()
    {
        return $this->buildUrl('calendars');
    }

    public function getContactFolderList(array $params = [])
    {
        $method = 'GET';

        $url = $this->buildUrl('contactfolders');

        return $this->request($url, $params, $method);
    }
}
