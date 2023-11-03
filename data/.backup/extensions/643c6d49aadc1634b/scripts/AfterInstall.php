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

class AfterInstall
{
    protected $container;

    public function run($container)
    {
        $this->container = $container;

        $entityManager = $this->container->get('entityManager');

        $pdo = $entityManager->getPDO();

        if (!$entityManager->getRepository('ScheduledJob')->where(['job' => 'SyncOutlookCalendar'])->findOne()) {
            $job = $entityManager->getEntity('ScheduledJob');
            $job->set([
               'name' => 'Outlook Calendar Sync',
               'job' => 'SyncOutlookCalendar',
               'status' => 'Active',
               'scheduling' => '*/10 * * * *',
            ]);
            $entityManager->saveEntity($job);
        }

        $config = $this->container->get('config');
        $config->set('adminPanelIframeUrl', $this->getIframeUrl('outlook-integration'));
        $config->save();

        $this->clearCache();
    }

    protected function clearCache()
    {
        try {
            $this->container->get('dataManager')->clearCache();
        } catch (\Exception $e) {}
    }

    protected function getIframeUrl($name)
    {
        $config = $this->container->get('config');

        $iframeUrl = $config->get('adminPanelIframeUrl');
        if (empty($iframeUrl) || trim($iframeUrl) == '/') {
            $iframeUrl = 'https://s.espocrm.com/';
        }
        $iframeUrl = $this->urlFixParam($iframeUrl);

        if (method_exists('\\Espo\\Core\Utils\\Util', 'urlAddParam')) {
            return \Espo\Core\Utils\Util::urlAddParam($iframeUrl, $name, '26bfa1fab74a68212506685b1b343192');
        }

        return $this->urlAddParam($iframeUrl, $name, '26bfa1fab74a68212506685b1b343192');
    }

    protected function urlAddParam($url, $paramName, $paramValue)
    {
        $urlQuery = parse_url($url, \PHP_URL_QUERY);

        if (!$urlQuery) {
            $params = [
                $paramName => $paramValue
            ];

            $url = trim($url);
            $url = preg_replace('/\/\?$/', '', $url);
            $url = preg_replace('/\/$/', '', $url);

            return $url . '/?' . http_build_query($params);
        }

        parse_str($urlQuery, $params);

        if (!isset($params[$paramName]) || $params[$paramName] != $paramValue) {
            $params[$paramName] = $paramValue;

            return str_replace($urlQuery, http_build_query($params), $url);
        }

        return $url;
    }

    protected function urlFixParam($url)
    {
        if (preg_match('/\/&(.+?)=(.+?)\//i', $url, $match)) {
            $fixedUrl = str_replace($match[0], '/', $url);
            if (!empty($match[1])) {
                if (method_exists('\\Espo\\Core\Utils\\Util', 'urlAddParam')) {
                    $url = \Espo\Core\Utils\Util::urlAddParam($fixedUrl, $match[1], $match[2]);
                } else {
                    $url = $this->urlAddParam($fixedUrl, $match[1], $match[2]);
                }
            }
        }

        $url = preg_replace('/^(\/\?)+/', 'https://s.espocrm.com/?', $url);
        $url = preg_replace('/\/\?&/', '/?', $url);
        $url = preg_replace('/\/&/', '/?', $url);

        return $url;
    }
}
