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

namespace Espo\Modules\Sales\Services;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;

use Espo\ORM\Entity;

class QuoteWorkflow extends \Espo\Core\Services\Base
{
    protected $entityType = 'Quote';

    protected function init()
    {
        parent::init();

        $this->addDependency('entityManager');
        $this->addDependency('serviceFactory');
        $this->addDependency('user');
        $this->addDependency('metadata');
        $this->addDependency('config');
        $this->addDependency('language');
        $this->addDependency('mailSender');

        $this->addDependency('aclManager');
    }

    public function addItemList($workflowId, $entity, $data)
    {
        if (is_array($data)) {
            $data = (object) $data;
        }

        if (!isset($data->itemList) || !is_array($data->itemList)) {
            throw new Error('Bad itemList provided in addQuoteItemList.');
        }

        if (empty($data->itemList)) return;

        $newItemList = $data->itemList;

        $entity = $this->getEntityManager()->getEntity($entity->getEntityType(), $entity->id);

        if (!$entity->has('itemList')) {
            $quoteService = $this->getInjection('serviceFactory')->create($this->entityType);
            $quoteService->loadItemListField($entity);
        }

        $itemList = $entity->get('itemList');

        foreach ($newItemList as $item) {
            $itemList[] = (object) $item;
        }

        $entity->set('itemList', $itemList);

        if (!$entity->has('modifiedById')) {
            $entity->set('modifiedById', 'system');
            $entity->set('modifiedByName', 'System');
        }

        $this->getEntityManager()->saveEntity($entity, ['skipWorkflow' => true, 'modifiedById' => 'system', 'addItemList' => true]);
    }

    public function convertCurrency($workflowId, $entity, $data)
    {
        $config = $this->getInjection('config');

        $targetCurrency = isset($data->targetCurrency) ? $data->targetCurrency : $config->get('defaultCurrency');
        $baseCurrency = $config->get('baseCurrency');
        $rates = (object) ($config->get('currencyRates', []));

        if ($targetCurrency !== $baseCurrency && !property_exists($rates, $targetCurrency))
            throw new Error("Wokrflow convert currency: targetCurrency rate is not specified.");

        $entityManager = $this->getInjection('entityManager');

        $service = $this->getInjection('serviceFactory')->create($this->entityType);

        $reloadedEntity = $entityManager->getEntity($entity->getEntityType(), $entity->id);

        if (method_exists($service, 'getConvertCurrencyValues')) {
            $user = $this->getInjection('entityManager')->getEntity('User', 'system');

            $acl = new \Espo\Core\Acl($this->getInjection('aclManager'), $user);
            $service->setAcl($acl);
            $service->setUser($user);

            $values = $service->getConvertCurrencyValues($reloadedEntity, $targetCurrency, $baseCurrency, $rates, true);
            $reloadedEntity->set($values);

            if (count(get_object_vars($values))) {
                $entityManager->saveEntity($reloadedEntity, [
                    'skipWorkflow' => true, 'addItemList' => true, 'modifiedById' => 'system',
                ]);
            }
        }
    }

    public function sendInEmail($workflowId, $entity, $data)
    {
        $templateId = $data->templateId ?? null;
        $emailTemplateId = $data->emailTemplateId ?? null;

        if (!$templateId) throw new Error("QuoteWorkflow sendInEmail: No templateId");

        $template = $this->getEntityManager()->getEntity('Template', $templateId);
        if (!$template) throw new NotFound("QuoteWorkflow sendInEmail: Template doesn't exist");

        $subject = $template->get('name') . ': ' . $entity->get('name');

        $entityType = $entity->getEntityType();

        $service = $this->getInjection('serviceFactory')->create($entityType);

        $attributes = $service->getAttributesForEmail($entity->id, $templateId, [
             'skipOtherRecipients' => true,
        ]);

        if ($emailTemplateId) {
            $emailTemplate = $this->getEntityManager()->getEntity('EmailTemplate', $emailTemplateId);
            if (!$emailTemplate) throw new NotFound("QuoteWorkflow sendInEmail: Email Template doesn't exist");

            $etParams = [
                'entityHash' => [
                    $entityType => $entity,
                ]
            ];

            $etService = $this->getInjection('serviceFactory')->create('EmailTemplate');
            $etData = $etService->parseTemplate($emailTemplate, $etParams, true, true);

            $attributes['name'] = $etData['subject'];
            $attributes['body'] = $etData['body'];
            $attributes['isHtml'] = $etData['isHtml'];

            foreach ($etData['attachmentsIds'] as $attachmentId) {
                $attributes['attachmentsIds'][] = $attachmentId;
            }
        }

        $to = $data->to ?? null;

        if ($to) {
            if (strpos($to, 'link:') === 0) {
                $linkPath = substr($to, 5);
                $arr = explode('.', $linkPath);
                $target = $entity;

                foreach ($arr as $link) {
                    $linkType = $target->getRelationType($link);
                    if ($linkType !== 'belongsTo' && $linkType !== 'belongsToParent' && $linkType !== 'hasOne') {
                        throw new Error("QuoteWorkflow sendInEmail: Bad TO link");
                    }
                    $target = $target->get($link);
                    if (!$target) throw new Error("QuoteWorkflow sendInEmail: Could not find TO recipient");
                }

                $emailAddress = $target->get('emailAddress');

                if (!$emailAddress) throw new Error("QuoteWorkflow sendInEmail: Recipient doesn't have email address");

                $attributes['to'] = $emailAddress;
            }
        }

        if (empty($attributes['to'])) {
            throw new Error("QuoteWorkflow sendInEmail: Not recipient found");
        }

        $email = $this->getEntityManager()->getEntity('Email');
        $email->set($attributes);

        $attachmentList = [];
        foreach ($attributes['attachmentsIds'] as $attachmentId) {
            $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
            if ($attachment) {
                $attachmentList[] = $attachment;
            }
        }

        $message = new \Zend\Mail\Message();

        $this->getInjection('mailSender')->send($email, [], $message, $attachmentList);

        $this->getEntityManager()->saveEntity($email);

        return true;
    }
}
