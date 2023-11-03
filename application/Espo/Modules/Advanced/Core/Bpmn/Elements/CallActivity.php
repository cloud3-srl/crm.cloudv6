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

use Espo\Modules\Advanced\Entities\BpmnFlowchart;
use Espo\Modules\Advanced\Core\Bpmn\Utils\Helper;

use Espo\ORM\Entity;

use Throwable;
use stdClass;

class CallActivity extends Activity
{
    protected const MAX_INSTANCE_COUNT = 20;

    public function process()
    {
        if ($this->isMultiInstance()) {
            $this->processMultiInstance();

            return;
        }

        $callableType = $this->getAttributeValue('callableType');

        if (!$callableType) {
            $this->fail();

            return;
        }

        $methodName = 'process' . $callableType;

        if (!method_exists($this, $methodName)) {
            $this->fail();

            return;
        }

        $this->$methodName();
    }

    protected function processProcess()
    {
        $target = $this->getNewTargetEntity();

        if (!$target) {
            $GLOBALS['log']->info("BPM Call Activity: Could not get target for sub-process.");

            $this->fail();

            return;
        }

        $flowchartId = $this->getAttributeValue('flowchartId');

        $flowNode = $this->getFlowNode();

        $variables = $this->getClonedVariables();

        $subProcess = $this->getEntityManager()->createEntity(
            'BpmnProcess',
            [
                'status' => 'Created',
                'flowchartId' => $flowchartId,
                'targetId' => $target->id,
                'targetType' => $target->getEntityType(),
                'parentProcessId' => $this->getProcess()->id,
                'parentProcessFlowNodeId' => $flowNode->id,
                'assignedUserId' => $this->getProcess()->get('assignedUserId'),
                'teamsIds' => $this->getProcess()->getLinkMultipleIdList('teams'),
                'variables' => $variables,
            ],
            [
                'skipCreatedBy' => true,
                'skipModifiedBy' => true,
                'skipStartProcessFlow' => true,
            ]
        );

        $flowNode->set([
            'status' => 'In Process',
        ]);

        $flowNode->setDataItemValue('subProcessId', $subProcess->id);

        $this->getEntityManager()->saveEntity($flowNode);

        try {
            $this->getManager()->startCreatedProcess($subProcess);
        }
        catch (Throwable $e) {
            $GLOBALS['log']->error("BPM Call Activity: Starting sub-process failure. " . $e->getMessage());

            $this->fail();

            return;
        }
    }

    public function complete()
    {
        $subProcessId = $this->getFlowNode()->getDataItemValue('subProcessId');

        if ($subProcessId) {
            $subProcess = $this->getEntityManager()->getEntity('BpmnProcess', $subProcessId);

            if ($subProcess) {
                $spCreatedEntitiesData = $subProcess->get('createdEntitiesData') ?? (object) [];

                $createdEntitiesData = $this->getCreatedEntitiesData();

                $spVariables = $subProcess->get('variables') ?? (object) [];
                $variables = $this->getVariables() ?? (object) [];

                $isUpdated = false;

                foreach (get_object_vars($spCreatedEntitiesData) as $key => $value) {
                    if (!isset($createdEntitiesData->$key)) {
                        $createdEntitiesData->$key = $value;

                        $isUpdated = true;
                    }
                }

                $variableList = $this->getReturnVariableList();

                if ($this->isMultiInstance()) {
                    $variableList = [];

                    $returnCollectionVariable = $this->getReturnCollectionVariable();

                    $variables->$returnCollectionVariable = $spVariables->outputCollection;
                }

                foreach ($variableList as $variable) {
                    $variables->$variable = $spVariables->$variable ?? null;
                }

                if (
                    $isUpdated ||
                    count($variableList) ||
                    $this->isMultiInstance()
                ) {
                    $this->refreshProcess();

                    $this->getProcess()->set('createdEntitiesData', $createdEntitiesData);
                    $this->getProcess()->set('variables', $variables);

                    $this->getEntityManager()->saveEntity($this->getProcess());
                }
            }
        }

        $this->processNextElement();
    }

    protected function getReturnCollectionVariable(): ?string
    {
        $variable = $this->getAttributeValue('returnCollectionVariable');

        if (!$variable) {
            return null;
        }

        if ($variable[0] === '$') {
            $variable = substr($variable, 1);
        }

        return $variable;
    }

    /**
     * @return string[]
     */
    protected function getReturnVariableList(): array
    {
        $newVariableList = [];

        $variableList = $this->getAttributeValue('returnVariableList') ?? [];

        foreach ($variableList as $variable) {
            if (!$variable) {
                continue;
            }

            if ($variable[0] === '$') {
                $variable = substr($variable, 1);
            }

            $newVariableList[] = $variable;
        }

        return $newVariableList;
    }

    /**
     * @return Entity|null
     */
    protected function getNewTargetEntity()
    {
        $target = $this->getAttributeValue('target');

        return $this->getSpecificTarget($target);
    }

    /**
     * @return bool
     */
    protected function isMultiInstance()
    {
        return (bool) $this->getAttributeValue('isMultiInstance');
    }

    /**
     * @return bool
     */
    protected function isSequential()
    {
        return (bool) $this->getAttributeValue('isSequential');
    }

    /**
     * @return ?string
     */
    protected function getLoopCollectionExpression()
    {
        $expression = $this->getAttributeValue('loopCollectionExpression');

        if (!$expression) {
            return null;
        }

        $expression = trim($expression, " \t\n\r");

        if (substr($expression, -1) === ';') {
            $expression = substr($expression, 0, -1);
        }

        return $expression;
    }

    /**
     * @return \Espo\Core\Utils\Config
     */
    protected function getConfig()
    {
        return $this->getContainer()->get('config');
    }

    protected function getMaxInstanceCount(): int
    {
        return $this->getConfig()->get('bpmnSubProcessInstanceMaxCount', self::MAX_INSTANCE_COUNT);
    }

    /**
     * @return void
     */
    protected function processMultiInstance()
    {
        $loopCollectionExpression = $this->getLoopCollectionExpression();

        if (!$loopCollectionExpression) {
            throw new Error("BPM Sub-Process: No loop-collection-expression.");
        }

        $loopCollection = $this->getFormulaManager()->run(
            $loopCollectionExpression,
            $this->getTarget(),
            $this->getVariablesForFormula()
        );

        if (!is_iterable($loopCollection)) {
            throw new Error("BPM Sub-Process: Loop-collection-expression evaluaded to a non-iterable value.");
        }

        if ($loopCollection instanceof \Traversable) {
            $loopCollection = iterator_to_array($loopCollection);
        }

        $maxCount = $this->getMaxInstanceCount();

        $returnVariableList = $this->getReturnVariableList();

        $outputCollection = [];

        for ($i = 0; $i < count($loopCollection); $i++) {
            $outputItem = (object) [];

            foreach ($returnVariableList as $variable) {
                $outputItem->$variable = null;
            }

            $outputCollection[] = $outputItem;
        }

        if ($maxCount < count($loopCollection)) {
            $loopCollection = array_slice($loopCollection, 0, $maxCount);
        }

        $count = count($loopCollection);

        $flowchart = $this->createMultiInstanceFlowchart($count);

        $flowNode = $this->getFlowNode();
        $variables = $this->getClonedVariables();

        $this->refreshProcess();

        $variables->inputCollection = $loopCollection;
        $variables->outputCollection = $outputCollection;

        $subProcess = $this->getEntityManager()->createEntity(
            'BpmnProcess',
            [
                'status' => 'Created',
                'targetId' => $this->getTarget()->id,
                'targetType' => $this->getTarget()->getEntityType(),
                'parentProcessId' => $this->getProcess()->id,
                'parentProcessFlowNodeId' => $flowNode->id,
                'assignedUserId' => $this->getProcess()->get('assignedUserId'),
                'teamsIds' => $this->getProcess()->getLinkMultipleIdList('teams'),
                'variables' => $variables,
                'createdEntitiesData' => clone $this->getCreatedEntitiesData(),
            ],
            [
                'skipCreatedBy' => true,
                'skipModifiedBy' => true,
                'skipStartProcessFlow' => true,
            ]
        );

        $flowNode->set([
            'status' => 'In Process',
        ]);

        $flowNode->setDataItemValue('subProcessId', $subProcess->id);

        $this->getEntityManager()->saveEntity($flowNode);

        try {
            $this->getManager()->startCreatedProcess($subProcess, $flowchart);
        }
        catch (Throwable $e) {
            $GLOBALS['log']->error("BPM Sub-Process: Starting sub-process failure. " . $e->getMessage());

            $this->fail();

            return;
        }
    }

    protected function createMultiInstanceFlowchart(int $count): BpmnFlowchart
    {
        $flowchart = $this->getEntityManager()->getEntity('BpmnFlowchart');

        $dataList = $this->isSequential() ?
            $this->generateSequentialMultiInstanceDataList($count) :
            $this->generateParallelMultiInstanceDataList($count);

        $eData = Helper::getElementsDataFromFlowchartData((object) [
            'list' => $dataList,
        ]);

        $name = $this->isSequential() ?
            'Sequential Multi-Instance' :
            'Parallel Multi-Instance';

        $flowchart->set([
            'targetType' => $this->getTarget()->getEntityType(),
            'data' => (object) [
                'createdEntitiesData' => clone $this->getCreatedEntitiesData(),
                'list' => $dataList,
            ],
            'elementsDataHash' => $eData['elementsDataHash'],
            'teamsIds' => $this->getProcess()->getLinkMultipleIdList('teams'),
            'assignedUserId' => $this->getProcess()->get('assignedUserId'),
            'name' => $name,
        ]);

        return $flowchart;
    }

    /**
     * @return stdClass[]
     */
    protected function generateParallelMultiInstanceDataList(int $count): array
    {
        $dataList = [];

        for ($i = 0; $i < $count; $i++) {
            $dataList = array_merge($dataList, $this->generateMultiInstanceIteration($i));
        }

        return $dataList;
    }

    /**
     * @return stdClass[]
     */
    protected function generateSequentialMultiInstanceDataList(int $count): array
    {
        $dataList = [];

        $groupList = [];

        for ($i = 0; $i < $count; $i++) {
            $groupList[] = $this->generateMultiInstanceIteration($i);
        }

        foreach ($groupList as $i => $itemList) {
            $dataList = array_merge($dataList, $itemList);

            if ($i == 0) {
                continue;
            }

            $previousItemList = $groupList[$i - 1];

            $dataList[] = (object) [
                'type' => 'flow',
                'id' => self::generateElementId(),
                'startId' => $previousItemList[2]->id,
                'endId' => $itemList[0]->id,
                'startDirection' => 'r',
            ];
        }

        return $dataList;
    }

    /**
     * @return stdClass[]
     */
    protected function generateMultiInstanceIteration(int $loopCounter): array
    {
        $dataList = [];

        $x = 100;
        $y = ($loopCounter + 1) * 130;

        if ($this->isSequential()) {
            $x = $x + ($loopCounter * 400);
            $y = 50;
        }

        $initElement = (object) [
            'type' => 'taskScript',
            'id' => self::generateElementId(),
            'formula' =>
                "\$loopCounter = {$loopCounter};\n" .
                "\$inputItem = array\\at(\$inputCollection, {$loopCounter});\n",
            'center' => (object) [
                'x' => $x,
                'y' => $y,
            ],
            'text' => $loopCounter . ' init',
        ];

        $subProcessElement = $this->generateSubProcessMultiInstance($loopCounter, $x, $y);

        $endScript = "\$outputItem = array\\at(\$outputCollection, {$loopCounter});\n";

        if (version_compare($this->getConfig()->get('version'), '7.1.0') > 0) {
            foreach ($this->getReturnVariableList() as $variable) {
                $endScript .= "object\set(\$outputItem, '{$variable}', \${$variable});\n";
            }
        }

        $endElement = (object) [
            'type' => 'taskScript',
            'id' => self::generateElementId(),
            'formula' => $endScript,
            'center' => (object) [
                'x' => $x + 250,
                'y' => $y,
            ],
            'text' => $loopCounter . ' out',
        ];

        $dataList[] = $initElement;
        $dataList[] = $subProcessElement;
        $dataList[] = $endElement;

        $dataList[] = (object) [
            'type' => 'flow',
            'id' => self::generateElementId(),
            'startId' => $initElement->id,
            'endId' => $subProcessElement->id,
            'startDirection' => 'r',
        ];

        $dataList[] = (object) [
            'type' => 'flow',
            'id' => self::generateElementId(),
            'startId' => $subProcessElement->id,
            'endId' => $endElement->id,
            'startDirection' => 'r',
        ];

        foreach ($this->generateBoundryMultiInstance($subProcessElement) as $item) {
            $dataList[] = $item;
        }

        return $dataList;
    }

    protected function generateSubProcessMultiInstance(int $loopCounter, int $x, int $y): stdClass
    {
        return (object) [
            'type' => $this->getAttributeValue('type'),
            'id' => self::generateElementId(),
            'center' => (object) [
                'x' => $x + 125,
                'y' => $y,
            ],
            'callableType' => $this->getAttributeValue('callableType'),
            'flowchartId' => $this->getAttributeValue('flowchartId'),
            'flowchartName' => $this->getAttributeValue('flowchartName'),
            'returnVariableList' => $this->getAttributeValue('returnVariableList'),
            'target' => $this->getAttributeValue('target'),
            'targetType' => $this->getAttributeValue('targetType'),
            'targetIdExpression' => $this->getAttributeValue('targetIdExpression'),
            'isMultiInstance' => false,
            'isSequential' => false,
            'loopCollectionExpression' => null,
            'text' => (string) $loopCounter,
        ];
    }

    /**
     * @return stdClass[]
     */
    protected function generateBoundryMultiInstance(stdClass $element): array
    {
        $dataList = [];

        $attachedElementIdList = array_filter(
            $this->getProcess()->getAttachedToFlowNodeElementIdList($this->getFlowNode()),
            function (string $id): bool {
                $data = $this->getProcess()->getElementDataById($id);

                return in_array(
                    $data->type,
                    [
                        'eventIntermediateErrorBoundary',
                        'eventIntermediateEscalationBoundary',
                    ]
                );
            }
        );

        foreach ($attachedElementIdList as $i => $id) {
            $boundaryElementId = self::generateElementId();
            $throwElementId = self::generateElementId();

            $originalData = $this->getProcess()->getElementDataById($id);

            $o1 = (object) [
                'type' => $originalData->type,
                'id' => $boundaryElementId,
                'attachedToId' => $element->id,
                'cancelActivity' => $originalData->cancelActivity ?? false,
                'center' => (object) [
                    'x' => $element->center->x - 20 + $i * 25,
                    'y' => $element->center->y - 35,
                ],
                'attachPosition' => $originalData->attachPosition,
            ];

            $o2 = (object) [
                'type' => 'eventEndError',
                'id' => $throwElementId,
                'errorCode' => $originalData->errorCode ?? null,
                'center' => (object) [
                    'x' => $element->center->x - 20 + $i * 25 + 80,
                    'y' => $element->center->y - 35 - 25,
                ],
            ];

            if ($originalData->type === 'eventIntermediateErrorBoundary') {
                $o2->type = 'eventEndError';
                $o1->errorCode = $originalData->errorCode ?? null;
                $o2->errorCode = $originalData->errorCode ?? null;
                $o1->cancelActivity = true;
            }
            else if ($originalData->type === 'eventIntermediateEscalationBoundary') {
                $o2->type = 'eventEndEscalation';
                $o1->escalationCode = $originalData->escalationCode ?? null;
                $o2->escalationCode = $originalData->escalationCode ?? null;
            }

            $dataList[] = $o1;
            $dataList[] = $o2;

            $dataList[] = (object) [
                'type' => 'flow',
                'id' => self::generateElementId(),
                'startId' => $boundaryElementId,
                'endId' => $throwElementId,
                'startDirection' => 'r',
            ];
        }

        return $dataList;
    }

    protected static function generateElementId(): string
    {
        return \Espo\Core\Utils\Util::generateId();
    }
}
