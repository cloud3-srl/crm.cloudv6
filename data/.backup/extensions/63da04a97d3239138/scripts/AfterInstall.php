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

class AfterInstall
{
    protected $container;

    public function run($container, $params = [])
    {
        $this->container = $container;

        $isUpgrade = false;
        if (!empty($params['isUpgrade'])) $isUpgrade = true;

        $metadata = $this->container->get('metadata');
        $entityManager = $this->container->get('entityManager');

        $pdo = $entityManager->getPDO();

        $template = $entityManager->getRepository('Template')->where(['entityType' => 'Report'])->findOne();
        if (!$isUpgrade && !$template) {
            $template = $entityManager->getEntity('Template');
            $template->set([
                'id' => 'Report001',
                'entityType' => 'Report',
                'name' => 'Report (default)',
                'header' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'Report', 'header']),
                'body' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'Report', 'body']),
                'footer' => $metadata->get(['entityDefs', 'Template', 'defaultTemplates', 'Report', 'footer']),
                'createdById' => 'system',
            ]);
            try {
                $entityManager->saveEntity($template, ['skipCreatedBy' => true]);
            } catch (\Exception $e) {}
        }

        if (!$entityManager->getRepository('ScheduledJob')->where(array('job' => 'ReportTargetListSync'))->findOne()) {
            $job = $entityManager->getEntity('ScheduledJob');
            $job->set(array(
               'name' => 'Sync Target Lists with Reports',
               'job' => 'ReportTargetListSync',
               'status' => 'Active',
               'scheduling' => '0 2 * * *',
            ));
            $entityManager->saveEntity($job);
        }

        if (!$entityManager->getRepository('ScheduledJob')->where(array('job' => 'ScheduleReportSending'))->findOne()) {
            $job = $entityManager->getEntity('ScheduledJob');
            $job->set(array(
               'name' => 'Schedule Report Sending',
               'job' => 'ScheduleReportSending',
               'status' => 'Active',
               'scheduling' => '0 * * * *',
            ));
            $entityManager->saveEntity($job);
        }

        if (!$entityManager->getRepository('ScheduledJob')->where(array('job' => 'RunScheduledWorkflows'))->findOne()) {
            $job = $entityManager->getEntity('ScheduledJob');
            $job->set(array(
               'name' => 'Run Scheduled Workflows',
               'job' => 'RunScheduledWorkflows',
               'status' => 'Active',
               'scheduling' => '*/10 * * * *',
            ));
            $entityManager->saveEntity($job);
        }

        if (!$entityManager->getRepository('ScheduledJob')->where(array('job' => 'ProcessPendingProcessFlows'))->findOne()) {
            $job = $entityManager->getEntity('ScheduledJob');
            $job->set(array(
               'name' => 'Process Pending Flows',
               'job' => 'ProcessPendingProcessFlows',
               'status' => 'Active',
               'scheduling' => '* * * * *',
            ));
            $entityManager->saveEntity($job);
        }

        if (!$isUpgrade) {
            $sql = "SELECT id FROM report WHERE id = '001'";
            $sth = $pdo->prepare($sql);
            $sth->execute();
            if (!$sth->fetch()) {
                foreach ($this->reportExampleDataList as $data) {
                    try {
                        $report = $entityManager->getEntity('Report');
                        $report->set($data);
                        $entityManager->saveEntity($report);
                    } catch (\Exception $e) {}
                }

                $sql = "SELECT id FROM `report_category_path` WHERE ascendor_id = 'examples'";
                $sth = $pdo->prepare($sql);
                $sth->execute();
                if (!$sth->fetch()) {
                    $sql = "INSERT INTO `report_category` (`id`, `name`, `order`) VALUES ('examples', 'Examples', 100)";
                    $pdo->query($sql);
                    $sql = "INSERT INTO `report_category_path` (`ascendor_id`, `descendor_id`) VALUES ('examples', 'examples')";
                    $pdo->query($sql);
                }
            }
        }

        $metadata = $container->get('metadata');

        $config = $this->container->get('config');
        $tabList = $config->get('tabList');
        $assignmentNotificationsEntityList = $config->get('assignmentNotificationsEntityList');

        if (!$isUpgrade) {
            if (!in_array('Report', $tabList)) {
                $tabList[] = 'Report';
                $config->set('tabList', $tabList);
            }
            if (!in_array('BpmnUserTask', $assignmentNotificationsEntityList)) {
                $assignmentNotificationsEntityList[] = 'BpmnUserTask';
                $config->set('assignmentNotificationsEntityList', $assignmentNotificationsEntityList);
            }
        }

        $config->set('adminPanelIframeUrl', $this->getIframeUrl('advanced-pack'));

        $config->save();

        $this->clearCache();
    }

    protected function clearCache()
    {
        try {
            $this->container->get('dataManager')->clearCache();
        } catch (\Exception $e) {}
    }

    private $reportExampleDataList = array(
        array(
         'id' => '001',
         'name' => 'Leads by last activity',
         'entityType' => 'Lead',
         'type' => 'Grid',
         'columns' => array(
          0 => 'COUNT:id',
        ),
         'chartColor' => '#6FA8D6',
         'chartType' => 'BarVertical',
         'depth' => 2,
         'isInternal' => true,
         'internalClassName' => 'Advanced:LeadsByLastActivity',
         'categoryId' => 'examples',
        ),
        array(
         'id' => '002',
         'name' => 'Opportunities won',
         'entityType' => 'Opportunity',
         'type' => 'List',
         'columns' => array(
          0 => 'name',
          1 => 'account',
          2 => 'closeDate',
          3 => 'amount',
        ),
         'runtimeFilters' => array(
          0 => 'closeDate',
        ),
         'filtersData' => array(
        ),
         'chartColor' => '#6FA8D6',
         'categoryId' => 'examples',
        ),
        array(
         'id' => '003',
         'name' => 'Calls by account and user',
         'entityType' => 'Call',
         'type' => 'Grid',
         'columns' => array(
          0 => 'COUNT:id',
        ),
         'groupBy' => array(
          0 => 'account',
          1 => 'assignedUser',
        ),
         'filtersDataList' => array(
          0 => array(
             'id' => '4c2388c1c4172',
             'name' => 'status',
             'params' =>
            array(
               'type' => 'in',
               'value' =>
              array (
                0 => 'Held',
              ),
               'data' => array(
                 'type' => 'anyOf',
                 'valueList' => array(
                  0 => 'Held',
                ),
              ),
               'field' => 'status',
               'attribute' => 'status',
            ),
          ),
        ),
         'runtimeFilters' => array(
          0 => 'dateStart',
        ),
         'chartColor' => '#6FA8D6',
         'chartType' => 'BarVertical',
         'categoryId' => 'examples',
      ),
        array(
         'id' => '004',
         'name' => 'Opportunities by lead source and user',
         'entityType' => 'Opportunity',
         'type' => 'Grid',
         'columns' => array(
          0 => 'COUNT:id',
          1 => 'SUM:amountWeightedConverted',
        ),
         'groupBy' => array(
          0 => 'assignedUser',
          1 => 'leadSource',
        ),
         'orderBy' => array(
          0 => 'LIST:leadSource',
          1 => 'ASC:assignedUser',
        ),
         'chartColor' => '#6FA8D6',
         'chartType' => 'BarVertical',
         'categoryId' => 'examples',
        ),
        array(
         'id' => '005',
         'name' => 'Leads by user',
         'entityType' => 'Lead',
         'type' => 'Grid',
         'columns' => array(
          0 => 'COUNT:id',
        ),
         'groupBy' => array(
          0 => 'assignedUser',
        ),
         'orderBy' => array(
          0 => 'ASC:assignedUser',
        ),
         'filtersDataList' => array(
          0 => array(
             'id' => '52566133e5c87',
             'name' => 'status',
             'params' =>
            array(
               'type' => 'in',
               'value' =>
              array (
                0 => 'New',
                1 => 'Assigned',
                2 => 'In Process',
              ),
               'data' => array(
                 'type' => 'anyOf',
                 'valueList' => array(
                  0 => 'New',
                  1 => 'Assigned',
                  2 => 'In Process',
                ),
              ),
               'field' => 'status',
               'attribute' => 'status',
            ),
          ),
        ),
         'chartColor' => '#6FA8D6',
         'chartType' => 'BarVertical',
         'categoryId' => 'examples',
        ),
        array(
         'id' => '006',
         'name' => 'Opportunities by user',
         'entityType' => 'Opportunity',
         'type' => 'Grid',
         'columns' => array(
          0 => 'COUNT:id',
          1 => 'SUM:amountWeightedConverted',
          2 => 'SUM:amountConverted',
        ),
         'groupBy' => array(
          0 => 'assignedUser',
        ),
         'orderBy' => array(
          0 => 'ASC:assignedUser',
        ),
         'filtersDataList' => array(
          0 => array(
             'id' => 'd955e51247b15',
             'name' => 'stage',
             'params' =>
            array(
               'type' => 'in',
               'value' =>
              array (
                0 => 'Prospecting',
                1 => 'Qualification',
                2 => 'Proposal/Price Quote',
                3 => 'Negotiation/Review',
              ),
               'data' => array(
                 'type' => 'anyOf',
                 'valueList' => array(
                  0 => 'Prospecting',
                  1 => 'Qualification',
                  2 => 'Proposal/Price Quote',
                  3 => 'Negotiation/Review',
                ),
              ),
               'field' => 'stage',
               'attribute' => 'stage',
            ),
          ),
        ),
         'chartColor' => '#6FA8D6',
         'chartType' => 'BarVertical',
         'categoryId' => 'examples',
        ),
        array(
         'id' => '007',
         'name' => 'Revenue by month and user',
         'entityType' => 'Opportunity',
         'type' => 'Grid',
         'columns' => array(
          0 => 'SUM:amountConverted',
        ),
         'groupBy' => array(
          0 => 'MONTH:closeDate',
          1 => 'assignedUser',
        ),
         'orderBy' => array(
          0 => 'ASC:assignedUser',
        ),
         'filtersDataList' => array(
          0 => array(
             'id' => '449f09b3eb3d',
             'name' => 'stage',
             'params' =>
            array(
               'type' => 'in',
               'value' =>
              array (
                0 => 'Closed Won',
              ),
               'data' => array(
                 'type' => 'anyOf',
                 'valueList' => array(
                  0 => 'Closed Won',
                ),
              ),
               'field' => 'stage',
               'attribute' => 'stage',
            ),
          ),
        ),
         'runtimeFilters' => array(
          0 => 'closeDate',
        ),
         'chartColor' => '#6FA8D6',
         'chartType' => 'Line',
         'categoryId' => 'examples',
        ),
        array(
         'id' => '008',
         'name' => 'Leads by status',
         'entityType' => 'Lead',
         'type' => 'Grid',
         'data' => array(
           'success' => 'Converted',
        ),
         'columns' => array(
          0 => 'COUNT:id',
        ),
         'groupBy' => array(
          0 => 'status',
        ),
         'orderBy' => array(
          0 => 'LIST:status',
        ),
         'filtersDataList' => array(
          0 => array(
             'id' => '86ca72143221d',
             'name' => 'status',
             'params' =>
            array(
               'type' => 'in',
               'value' =>
              array (
                0 => 'New',
                1 => 'Assigned',
                2 => 'In Process',
              ),
               'data' => array(
                 'type' => 'anyOf',
                 'valueList' => array(
                  0 => 'New',
                  1 => 'Assigned',
                  2 => 'In Process',
                ),
              ),
               'field' => 'status',
               'attribute' => 'status',
            ),
          ),
        ),
         'chartColor' => '#6FA8D6',
         'chartType' => 'BarHorizontal',
         'categoryId' => 'examples',
      ),
      array(
         'id' => '009',
         'name' => 'Revenue by month',
         'entityType' => 'Opportunity',
         'type' => 'Grid',
         'data' => array(
           'success' => 'Closed Won',
        ),
         'columns' => array(
          0 => 'SUM:amountConverted',
        ),
         'groupBy' => array(
          0 => 'MONTH:closeDate',
        ),
         'filtersDataList' => array(
          0 => array(
             'id' => '429ccdc389055',
             'name' => 'stage',
             'params' =>
            array(
               'type' => 'in',
               'value' =>
              array (
                0 => 'Closed Won',
              ),
               'data' => array(
                 'type' => 'anyOf',
                 'valueList' => array(
                  0 => 'Closed Won',
                ),
              ),
               'field' => 'stage',
               'attribute' => 'stage',
            ),
          ),
        ),
         'runtimeFilters' => array(
          0 => 'closeDate',
        ),
         'chartColor' => '#6FA8D6',
         'chartType' => 'BarVertical',
         'categoryId' => 'examples',
      ),
      array(
         'id' => '010',
         'name' => 'Leads by source',
         'entityType' => 'Lead',
         'type' => 'Grid',
         'columns' => array(
          0 => 'COUNT:id',
        ),
         'groupBy' => array(
          0 => 'source',
        ),
         'orderBy' => array(
          0 => 'LIST:source',
        ),
         'filtersDataList' => array(
          0 => array(
             'id' => 'af614c422212d',
             'name' => 'status',
             'params' =>
            array(
               'type' => 'in',
               'value' =>
              array (
                0 => 'New',
                1 => 'Assigned',
                2 => 'In Process',
              ),
               'data' => array(
                 'type' => 'anyOf',
                 'valueList' => array(
                  0 => 'New',
                  1 => 'Assigned',
                  2 => 'In Process',
                ),
              ),
               'field' => 'status',
               'attribute' => 'status',
            ),
          ),
        ),
         'chartColor' => '#6FA8D6',
         'chartType' => 'Pie',
         'categoryId' => 'examples',
      ),
    );

    protected function getIframeUrl($name)
    {
        $config = $this->container->get('config');

        $iframeUrl = $config->get('adminPanelIframeUrl');
        if (empty($iframeUrl) || trim($iframeUrl) == '/') {
            $iframeUrl = 'https://s.espocrm.com/';
        }
        $iframeUrl = $this->urlFixParam($iframeUrl);

        if (method_exists('\\Espo\\Core\Utils\\Util', 'urlAddParam')) {
            return \Espo\Core\Utils\Util::urlAddParam($iframeUrl, $name, 'a3ea4219cf9c3e5dee57026de28a15c1');
        }

        return $this->urlAddParam($iframeUrl, $name, 'a3ea4219cf9c3e5dee57026de28a15c1');
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
