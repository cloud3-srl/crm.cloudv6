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
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: c235cfac520a05e355b12cda9ca78531
 ***********************************************************************************/

class AfterInstall
{
    protected $container;

    public function run($container, $params = [])
    {
        $this->container = $container;

        $isUpgrade = false;
        if (!empty($params['isUpgrade'])) $isUpgrade = true;

        $entityManager = $this->container->get('entityManager');

        $pdo = $entityManager->getPDO();

        $metadata = $this->container->get('metadata');

        $template = $entityManager->getEntity('Template', '001');
        if (!$isUpgrade && !$template) {
            $template = $entityManager->getEntity('Template');
            $template->set([
                'id' => '001',
                'entityType' => 'Quote',
                'name' => 'Quote (example)',
                'header' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'Quote', 'header']),
                'body' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'Quote', 'body']),
                'footer' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'Quote', 'footer']),
                'createdById' => 'system'
            ]);
            try {
                $entityManager->saveEntity($template, ['skipCreatedBy' => true]);
            } catch (\Exception $e) {}

            $template = $entityManager->getEntity('Template');
            $template->set([
                'id' => '011',
                'entityType' => 'SalesOrder',
                'name' => 'Sales Order (example)',
                'header' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'SalesOrder', 'header']),
                'body' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'SalesOrder', 'body']),
                'footer' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'SalesOrder', 'footer']),
                'createdById' => 'system'
            ]);
            try {
                $entityManager->saveEntity($template, ['skipCreatedBy' => true]);
            } catch (\Exception $e) {}

            $template = $entityManager->getEntity('Template');
            $template->set([
                'id' => '021',
                'entityType' => 'Invoice',
                'name' => 'Invoice (example)',
                'header' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'Invoice', 'header']),
                'body' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'Invoice', 'body']),
                'footer' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'Invoice', 'footer']),
                'createdById' => 'system'
            ]);
            try {
                $entityManager->saveEntity($template, ['skipCreatedBy' => true]);
            } catch (\Exception $e) {}
        }

        $config = $this->container->get('config');
        $tabList = $config->get('tabList');

        if (!$isUpgrade) {
            if (!in_array('Quote', $tabList)) {
                $tabList[] = 'Quote';
                $config->set('tabList', $tabList);
            }
            if (!in_array('SalesOrder', $tabList)) {
                $tabList[] = 'SalesOrder';
                $config->set('tabList', $tabList);
            }
            if (!in_array('Invoice', $tabList)) {
                $tabList[] = 'Invoice';
                $config->set('tabList', $tabList);
            }
            if (!in_array('Product', $tabList)) {
                $tabList[] = 'Product';
                $config->set('tabList', $tabList);
            }
        }

        $config->set('adminPanelIframeUrl', $this->getIframeUrl('sales-pack'));

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
            return \Espo\Core\Utils\Util::urlAddParam($iframeUrl, $name, 'c235cfac520a05e355b12cda9ca78531');
        }

        return $this->urlAddParam($iframeUrl, $name, 'c235cfac520a05e355b12cda9ca78531');
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
