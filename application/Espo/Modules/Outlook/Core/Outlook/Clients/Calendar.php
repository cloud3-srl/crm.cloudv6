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

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\BadRequest;

use Espo\Core\ExternalAccount\OAuth2\Client;

class Calendar extends Outlook
{
    protected $baseUrl = 'https://graph.microsoft.com/v1.0/me/';

    protected function getPingUrl()
    {
        return $this->buildUrl('calendars');
    }

    public function getCalendarList(array $params = [])
    {
        $method = 'GET';

        $url = $this->buildUrl('calendars');

        return $this->request($url, $params, $method);
    }

    public function requestSync(string $calendarId, array $params = [])
    {
        $requestParams = [];

        $url = $this->baseUrl . "calendars('".$calendarId."')/calendarView/delta";

        $isFirstRun = true;
        $isSyncFinished = false;

        if (isset($params['url'])) {
            $url = $params['url'];
        }
        else {
            if (isset($params['start'])) {
                $dt = new \DateTime($params['start']);

                $requestParams['startDateTime'] = $dt->format('c');
            }
            if (isset($params['end'])) {
                $dt = new \DateTime($params['end']);

                $requestParams['endDateTime'] = $dt->format('c');
            }
            if (isset($params['deltaToken'])) {
                $requestParams['$deltaToken'] = $params['deltaToken'];
                $isFirstRun = false;
            }
            if (isset($params['skipToken'])) {
                $requestParams['$skipToken'] = $params['skipToken'];
                $isFirstRun = false;
            }
        }

        $headers = [
            'Prefer: odata.track-changes',
        ];

        if (isset($params['maxPageSize'])) {
            $headers[] = 'Prefer: odata.maxpagesize=' . strval($params['maxPageSize']);
        }

        $result = $this->request($url, $requestParams, Client::HTTP_METHOD_GET, null, true, $headers);

        $resultData = [];

        if (isset($result['@odata.deltaLink'])) {
            $deltaLink = $result['@odata.deltaLink'];

            $deltaLink = urldecode($deltaLink);

            $parts = parse_url($deltaLink);

            parse_str($parts['query'], $query);

            $deltaToken = $query['$deltatoken'] ?? $query['$deltaToken'] ?? null;

            $resultData['deltaToken'] = $deltaToken;

            if (!$isFirstRun) {
                $isSyncFinished = true;
            }

        } else if (isset($result['@odata.nextLink'])) {
            $nextLink = $result['@odata.nextLink'];
            $nextLink = urldecode($nextLink);
            $parts = parse_url($nextLink);

            parse_str($parts['query'], $query);
            $skipToken = $query['$skipToken'] ?? ($query['$skiptoken'] ?? null);

            $resultData['skipToken'] = $skipToken;
        }

        if (isset($result['value']) && is_array($result['value'])) {
            $resultData['itemList'] = $result['value'];
        }

        $resultData['isSyncFinished'] = $isSyncFinished;

        return $resultData;
    }
}
