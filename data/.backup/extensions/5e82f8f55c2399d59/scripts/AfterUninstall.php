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
 * Copyright (C) 2015-2020 Letrium Ltd.
 * 
 * License ID: 3c74322a3a0810d0d8923995bc50d16d
 ***********************************************************************************/

class AfterUninstall
{
    protected $container;

    public function run($container)
    {
        $this->container = $container;

        $entityManager = $this->container->get('entityManager');

        if ($job = $entityManager->getRepository('ScheduledJob')->where(array('job' => 'ReportTargetListSync'))->findOne()) {
            $entityManager->removeEntity($job);
        }
        if ($job = $entityManager->getRepository('ScheduledJob')->where(array('job' => 'ScheduleReportSending'))->findOne()) {
            $entityManager->removeEntity($job);
        }
        if ($job = $entityManager->getRepository('ScheduledJob')->where(array('job' => 'RunScheduledWorkflows'))->findOne()) {
            $entityManager->removeEntity($job);
        }
        if ($job = $entityManager->getRepository('ScheduledJob')->where(array('job' => 'ProcessPendingProcessFlows'))->findOne()) {
            $entityManager->removeEntity($job);
        }

        $config = $this->container->get('config');
        $config->set('adminPanelIframeUrl', $this->getIframeUrl('advanced-pack'));
        $config->save();
    }

    protected function getIframeUrl($name)
    {
        $config = $this->container->get('config');

        $iframeUrl = $config->get('adminPanelIframeUrl');

        if (method_exists('\\Espo\\Core\Utils\\Util', 'urlRemoveParam')) {
            return \Espo\Core\Utils\Util::urlRemoveParam($iframeUrl, $name, '/');
        }

        return $this->urlRemoveParam($iframeUrl, $name, '/');
    }

    protected function urlRemoveParam($url, $paramName, $suffix)
    {
        $urlQuery = parse_url($url, \PHP_URL_QUERY);

        if ($urlQuery) {
            parse_str($urlQuery, $params);

            if (isset($params[$paramName])) {
                unset($params[$paramName]);

                $newUrl = str_replace($urlQuery, http_build_query($params), $url);

                if (empty($params)) {
                    $newUrl = preg_replace('/\/\?$/', '', $newUrl);
                    $newUrl = preg_replace('/\/$/', '', $newUrl);
                    $newUrl .= $suffix;
                }

                return $newUrl;
            }
        }

        return $url;
    }
}
