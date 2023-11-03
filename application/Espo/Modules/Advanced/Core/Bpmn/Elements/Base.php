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

namespace Espo\Modules\Advanced\Core\Bpmn\Elements;

use Espo\Modules\Advanced\Core\Bpmn\BpmnManager;

use Espo\Modules\Advanced\Entities\BpmnProcess;
use Espo\Modules\Advanced\Entities\BpmnFlowNode;
use Espo\ORM\Entity;

use Espo\Core\Exceptions\Error;

use Espo\Core\Container;

abstract class Base
{
    protected $container;
    protected $process;
    protected $flowNode;
    protected $target;
    protected $manager;

    /**
     * @return \Espo\Core\Container
     */
    protected function getContainer()
    {
        return $this->container;
    }

    /**
     * @return \Espo\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->container->get('entityManager');
    }

    /**
     * @return \Espo\Core\Utils\Metadata
     */
    protected function getMetadata()
    {
        return $this->container->get('metadata');
    }

    /**
     * @return BpmnProcess
     */
    protected function getProcess()
    {
        return $this->process;
    }

    /**
     * @return BpmnFlowNode
     */
    protected function getFlowNode()
    {
        return $this->flowNode;
    }

    /**
     * @return \Espo\ORM\Entity
     */
    protected function getTarget()
    {
        return $this->target;
    }

    /**
     * @return BpmnManager
     */
    protected function getManager()
    {
        return $this->manager;
    }

    public function __construct(
        Container $container,
        BpmnManager $manager,
        Entity $target,
        BpmnFlowNode $flowNode,
        BpmnProcess $process
    ) {
        $this->container = $container;
        $this->manager = $manager;
        $this->target = $target;
        $this->flowNode = $flowNode;
        $this->process = $process;
    }

    protected function refresh()
    {
        $this->refreshFlowNode();
        $this->refreshProcess();
        $this->refreshTarget();
    }

    protected function refreshFlowNode()
    {
        $flowNode = $this->getEntityManager()->getEntity('BpmnFlowNode', $this->flowNode->id);

        if ($flowNode) {
            $this->flowNode->set($flowNode->getValueMap());
            $this->flowNode->setAsFetched();
        }
    }

    protected function refreshProcess()
    {
        $process = $this->getEntityManager()->getEntity('BpmnProcess', $this->process->id);

        if ($process) {
            $this->process->set($process->getValueMap());
            $this->process->setAsFetched();
        }
    }

    protected function refreshTarget()
    {
        $target = $this->getEntityManager()->getEntity($this->target->getEntityType(), $this->target->id);

        if ($target) {
            $this->target->set($target->getValueMap());
            $this->target->setAsFetched();
        }
    }

    public function isProcessable()
    {
        return true;
    }

    public function beforeProcess()
    {
    }

    abstract public function process();

    public function afterProcess()
    {
    }

    public function beforeProceedPending()
    {
    }

    public function proceedPending()
    {
        throw new Error("BPM Flow: Can't proceed element ". $flowNode->get('elementType') . " " .
            $flowNode->get('elementId') . " in flowchart " . $flowNode->get('flowchartId') . ".");
    }

    public function afterProceedPending()
    {
    }

    protected function getElementId()
    {
        $flowNode = $this->getFlowNode();
        $elementId = $flowNode->get('elementId');

        if (!$elementId) {
            throw new Error("BPM Flow: No id for element " . $flowNode->get('elementType') .
                " in flowchart " . $flowNode->get('flowchartId') . ".");
        }

        return $elementId;
    }

    /**
     * @return bool
     */
    protected function hasNextElementId()
    {
        $flowNode = $this->getFlowNode();

        $item = $flowNode->get('elementData');
        $nextElementIdList = $item->nextElementIdList;

        if (!count($nextElementIdList)) {
            return false;
        }

        return true;
    }

    /**
     * @return ?string
     */
    protected function getNextElementId()
    {
        $flowNode = $this->getFlowNode();

        if (!$this->hasNextElementId()) {
            return null;
        }

        $item = $flowNode->get('elementData');
        $nextElementIdList = $item->nextElementIdList;

        return $nextElementIdList[0];
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getAttributeValue($name)
    {
        $item = $this->getFlowNode()->get('elementData');

        if (!property_exists($item, $name)) {
            return null;
        }

        return $item->$name;
    }

    /**
     * @return ?\stdClass
     */
    protected function getVariables()
    {
        return $this->getProcess()->get('variables');
    }

    protected function getClonedVariables(): \stdClass
    {
        return clone ($this->getVariables() ?? (object) []);
    }

    /**
     * @return \stdClass
     */
    protected function getVariablesForFormula()
    {
        $variables = $this->getClonedVariables();

        $variables->__createdEntitiesData = $this->getCreatedEntitiesData();
        $variables->__processEntity = $this->getProcess();
        $variables->__targetEntity = $this->getTarget();

        return $variables;
    }

    protected function sanitizeVariables($variables)
    {
        unset($variables->__createdEntitiesData);
        unset($variables->__processEntity);
        unset($variables->__targetEntity);
    }

    protected function setProcessed()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => 'Processed',
            'processedAt' => date('Y-m-d H:i:s')
        ]);
        $this->getEntityManager()->saveEntity($flowNode);
    }

    protected function setInterrupted()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => 'Interrupted',
        ]);
        $this->getEntityManager()->saveEntity($flowNode);

        $this->endProcessFlow();
    }

    protected function setFailed()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => 'Failed',
            'processedAt' => date('Y-m-d H:i:s'),
        ]);
        $this->getEntityManager()->saveEntity($flowNode);

        $this->endProcessFlow();
    }

    protected function setRejected()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => 'Rejected',
        ]);
        $this->getEntityManager()->saveEntity($flowNode);

        $this->endProcessFlow();
    }

    public function fail()
    {
        $this->setFailed();
    }

    public function interrupt()
    {
        $this->setInterrupted();
    }

    public function cleanupInterrupted()
    {
    }

    public function complete()
    {
        throw new Error("Can't complete " . $this->getFlowNode()->get('elementType') . ".");
    }

    /**
     * @return ?BpmnFlowNode
     */
    protected function prepareNextFlowNode($nextElementId = null, $divergentFlowNodeId = false)
    {
        $flowNode = $this->getFlowNode();

        if (!$nextElementId) {
            if (!$this->hasNextElementId()) {
                $this->endProcessFlow();

                return null;
            }

            $nextElementId = $this->getNextElementId();
        }

        if ($divergentFlowNodeId === false) {
            $divergentFlowNodeId = $flowNode->get('divergentFlowNodeId');
        }

        return $this->getManager()->prepareFlow(
            $this->getTarget(),
            $this->getProcess(),
            $nextElementId,
            $flowNode->id,
            $flowNode->get('elementType'),
            $divergentFlowNodeId
        );
    }

    /**
     * @param ?string $nextElementId
     * @param bool $divergentFlowNodeId
     * @param bool $dontSetProcessed
     * @return ?BpmnFlowNode
     */
    protected function processNextElement(
        $nextElementId = null,
        $divergentFlowNodeId = false,
        $dontSetProcessed = false
    ) {
        $nextFlowNode = $this->prepareNextFlowNode($nextElementId, $divergentFlowNodeId);

        if (!$dontSetProcessed) {
            $this->setProcessed();
        }

        if ($nextFlowNode) {
            $this->getManager()->processPreparedFlowNode(
                $this->getTarget(),
                $nextFlowNode,
                $this->getProcess()
            );
        }

        return $nextFlowNode;
    }

    protected function processPreparedNextFlowNode(BpmnFlowNode $flowNode)
    {
        $this->getManager()->processPreparedFlowNode($this->getTarget(), $flowNode, $this->getProcess());
    }

    protected function endProcessFlow()
    {
        $this->getManager()->endProcessFlow($this->getFlowNode(), $this->getProcess());
    }

    /**
     * @return \stdClass
     */
    protected function getCreatedEntitiesData()
    {
        $createdEntitiesData = $this->getProcess()->get('createdEntitiesData');

        if (!$createdEntitiesData) {
            $createdEntitiesData = (object) [];
        }

        return $createdEntitiesData;
    }

    /**
     * @param string $target
     * @return ?Entity
     */
    protected function getCreatedEntity($target)
    {
        $createdEntitiesData = $this->getCreatedEntitiesData();

        if (strpos($target, 'created:') === 0) {
            $alias = substr($target, 8);
        } else {
            $alias = $target;
        }

        if (!$createdEntitiesData) {
            return null;
        }

        if (!property_exists($createdEntitiesData, $alias)) {
            return null;
        }

        if (empty($createdEntitiesData->$alias->entityId) || empty($createdEntitiesData->$alias->entityType)) {
            return null;
        }

        $entityType = $createdEntitiesData->$alias->entityType;
        $entityId = $createdEntitiesData->$alias->entityId;

        $targetEntity = $this->getEntityManager()->getEntity($entityType, $entityId);

        return $targetEntity;
    }

    /**
     * @param string $target
     * @return ?Entity
     */
    protected function getSpecificTarget($target)
    {
        $entity = $this->getTarget();

        if (!$target || $target == 'targetEntity') {
            return $entity;
        }

        if (strpos($target, 'created:') === 0) {
            return $this->getCreatedEntity($target);
        }

        if (strpos($target, 'record:') === 0) {
            $entityType = substr($target, 7);

            $targetIdExpression = $this->getAttributeValue('targetIdExpression');

            if (!$targetIdExpression) {
                return null;
            }

            if (substr($targetIdExpression, -1) === ';') {
                $targetIdExpression = substr($targetIdExpression, 0, -1);
            }

            $id = $this->getFormulaManager()->run(
                $targetIdExpression,
                $this->getTarget(),
                $this->getVariablesForFormula()
            );

            if (!$id) {
                return null;
            }

            if (!is_string($id)) {
                throw new Error("BPM: Target-ID evaluated not to string.");
            }

            return $this->getEntityManager()->getEntity($entityType, $id);
        }

        if (strpos($target, 'link:') === 0) {
            $link = substr($target, 5);

            $linkList = explode('.', $link);

            $pointerEntity = $entity;

            $notFound = false;

            foreach ($linkList as $link) {
                $type = $this->getMetadata()
                    ->get(['entityDefs', $pointerEntity->getEntityType(), 'links', $link, 'type']);

                if (empty($type)) {
                    $notFound = true;

                    break;
                }

                $pointerEntity = $pointerEntity->get($link);

                if (!$pointerEntity || !($pointerEntity instanceof Entity)) {
                    $notFound = true;

                    break;
                }
            }

            if (!$notFound) {
                return $pointerEntity;
            }
        }

        return null;
    }

    /**
     * @return \Espo\Core\Formula\Manager
     */
    protected function getFormulaManager()
    {
        return $this->getContainer()->get('formulaManager');
    }
}
