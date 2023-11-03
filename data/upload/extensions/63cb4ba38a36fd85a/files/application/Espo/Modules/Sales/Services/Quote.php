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

namespace Espo\Modules\Sales\Services;

use Espo\ORM\Entity;

use Espo\Core\Utils\Util;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;

class Quote extends \Espo\Services\Record
{
    protected $itemEntityType = 'QuoteItem';

    protected $itemParentIdAttribute = 'quoteId';

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('language');
        $this->addDependency('htmlizerFactory');
    }

    protected function getDateTime()
    {
        return $this->getInjection('container')->get('dateTime');
    }

    protected function getNumber()
    {
        return $this->getInjection('container')->get('number');
    }

    protected function getFileManager()
    {
        return $this->getInjection('container')->get('fileManager');
    }

    protected function getTemplateFileManager()
    {
        return $this->getInjection('container')->get('templateFileManager');
    }

    protected function createHtmlizer()
    {
        return $this->getInjection('htmlizerFactory')->create();
    }

    public function loadAdditionalFields(Entity $entity)
    {
        parent::loadAdditionalFields($entity);
        $this->loadItemListField($entity);
    }

    public function loadItemListField(Entity $entity)
    {
        $itemList = $this->getEntityManager()->getRepository($this->itemEntityType)->where([
            $this->itemParentIdAttribute => $entity->id
        ])->order('order')->find();

        foreach ($itemList as $item) {
            $item->loadAllLinkMultipleFields();
        }

        $itemDataList = $itemList->toArray();
        foreach ($itemDataList as $i => $v) {
            $itemDataList[$i] = (object) $v;
        }
        $entity->set('itemList', $itemDataList);
    }

    public function loadAdditionalFieldsForExport(Entity $entity)
    {
        parent::loadAdditionalFieldsForExport($entity);
        $this->loadItemListField($entity);
    }

    public function loadAdditionalFieldsForPdf(Entity $entity)
    {
        $version = $this->getConfig()->get('version');
        if ($version != 'dev' && \Composer\Semver\Comparator::lessThan($version, '5.5.0')) {
            $itemList = $entity->get('itemList');
            if (is_array($itemList)) {
                foreach ($itemList as $i => &$item) {
                    if (floor($item->quantity) === $item->quantity) {
                        $item->quantity = intval($item->quantity);
                    }

                    $item->product = (object) [];
                    if (empty($item->productId)) {
                        continue;
                    }

                    $product = $this->getEntityManager()->getEntity('Product', $item->productId);
                    if (!$product) continue;

                    $item->product = (object) $product->toArray();
                }
                $jsonEncodeParam = 0;
                if (defined('\JSON_PRESERVE_ZERO_FRACTION')) {
                    $jsonEncodeParam = \JSON_PRESERVE_ZERO_FRACTION;
                }
                $itemList = json_decode(json_encode($itemList, $jsonEncodeParam), true);
                $entity->set('itemList', $itemList);
            }
            return;
        }

        $itemList = $this->getEntityManager()->getRepository($this->itemEntityType)->where([
            $this->itemParentIdAttribute => $entity->id
        ])->order('order')->find();

        foreach ($itemList as $item) {
            $quantity = $item->get('quantity');
            if (floor($quantity) === $quantity) {
                $quantity = intval($quantity);
                $item->set('quantity', $quantity);
            }
        }

        foreach ($itemList as $item) {
            $item->loadAllLinkMultipleFields();
        }

        $entity->set('itemList', $itemList);
    }

    protected function getAttributesFromAnotherRecord($sourceType, $sourceId)
    {
        $source = $this->getEntityManager()->getEntity($sourceType, $sourceId);

        $sourceItemType = $sourceType . 'Item';
        $idAttribute = lcfirst($sourceType) . 'Id';

        if (!$source) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($source, 'read')) {
            throw new Forbidden();
        }

        $sourceItemList = $this->getEntityManager()->getRepository($sourceItemType)->where([
            $idAttribute => $source->id
        ])->order('order')->find();

        $itemList = [];

        $itemSeed = $this->getEntityManager()->getEntity($this->itemEntityType);

        $defaultTaxRate = 0;
        $defaultTaxId = $this->getMetadata()->get(
            ['entityDefs', $this->entityType, 'fields', 'tax', 'defaultAttributes', 'taxId']
        );
        if ($defaultTaxId) {
            $defaultTax = $this->getEntityManager()->getEntity('Tax', $defaultTaxId);
            if ($defaultTax) {
                $defaultTaxRate = $defaultTax->get('rate');
            }
        }

        foreach ($sourceItemList as $item) {
            if (method_exists($item, 'loadAllLinkMultipleFields')) {
                $item->loadAllLinkMultipleFields();
            }

            $itemAttributes = [
                'name' => $item->get('name'),
                'productId' => $item->get('productId'),
                'productName' => $item->get('productName'),
                'unitPrice' => $item->get('unitPrice'),
                'unitPriceCurrency' =>$item->get('unitPriceCurrency'),
                'amount' => $item->get('amount'),
                'amountCurrency' => $item->get('amountCurrency'),
                'quantity' => $item->get('quantity'),
                'taxRate' => $item->get('taxRate') ?? $defaultTaxRate,
                'listPrice' => $item->get('listPrice') ?? $item->get('unitPrice'),
                'listPriceCurrency' => $item->get('amountCurrency'),
                'description' => $item->get('description'),
            ];

            $productId = $item->get('productId');
            if ($productId && $item->get('listPrice') === null) {
                $product = $this->getEntityManager()->getEntity('Product', $productId);
                if ($product) {
                    $listPrice = $product->get('listPrice');
                    $listPriceCurrency = $product->get('listPriceCurrency');
                    if ($listPriceCurrency != $source->get('amountCurrency')) {
                        $rates = $this->getConfig()->get('currencyRates', []);
                        $targetCurrency = $source->get('amountCurrency');

                        $value = $listPrice;

                        $rate1 = 1.0;
                        if (array_key_exists($listPriceCurrency, $rates)) {
                            $rate1 = $rates[$listPriceCurrency];
                        }
                        $rate2 = 1.0;
                        if (array_key_exists($targetCurrency, $rates)) {
                            $rate2 = $rates[$targetCurrency];
                        }
                        $value = $value * ($rate1);
                        $value = $value / ($rate2);

                        $listPrice = round($value, 2);
                        $listPriceCurrency = $targetCurrency;
                    }

                    $itemAttributes['listPrice'] = $listPrice;
                    $itemAttributes['listPriceCurrency'] = $listPriceCurrency;

                    if ($product->get('isTaxFree') && $item->get('taxRate') === null) {
                        $itemAttributes['taxRate'] = 0;
                    }
                }
            }

            foreach ($itemSeed->getAttributeList() as $attribute) {
                if (!$item->hasAttribute($attribute)) continue;
                if (array_key_exists($attribute, $itemAttributes)) continue;
                if (in_array($attribute, [
                    'id',
                    'createdById',
                    'createdByName',
                    'modifiedById',
                    'modifiedByName',
                    'createdAt',
                    'modifiedAt',
                ])) continue;

                $itemAttributes[$attribute] = $item->get($attribute);
            }

            $itemList[] = $itemAttributes;
        }

        $source->loadLinkMultipleField('teams');

        $attributes = [
            'name' => $source->get('name'),
            'teamsIds' => $source->get('teamsIds'),
            'teamsNames' => $source->get('teamsNames'),
            $idAttribute => $sourceId,
            'itemList' => $itemList,
            'amount' => $source->get('amount'),
            'amountCurrency' => $source->get('amountCurrency'),
            'preDiscountedAmountCurrency' => $source->get('amountCurrency'),
            'taxAmountCurrency' => $source->get('amountCurrency'),
            'grandTotalAmountCurrency' => $source->get('amountCurrency'),
            'discountAmountCurrency' => $source->get('amountCurrency'),
            'shippingCostCurrency' => $source->get('amountCurrency')
        ];

        if ($sourceType === 'Quote' || $sourceType === 'SalesOrder') {
            $attributes['billingContactId'] = $source->get('billingContactId');
            $attributes['billingContactName'] = $source->get('billingContactName');
            $attributes['shippingContactId'] = $source->get('shippingContactId');
            $attributes['shippingContactName'] = $source->get('shippingContactName');
        }

        if ($source->hasAttribute('quoteId')) {
            $attributes['quoteId'] = $source->get('quoteId');
            $attributes['quoteName'] = $source->get('quoteName');
        }

        if ($source->hasAttribute('salesOrderId')) {
            $attributes['salesOrderId'] = $source->get('salesOrderId');
            $attributes['salesOrderName'] = $source->get('salesOrderName');
        }

        if ($source->hasAttribute('opportunityId')) {
            $attributes['opportunityId'] = $source->get('opportunityId');
            $attributes['opportunityName'] = $source->get('opportunityName');
        }

        $amount = $source->get('amount');
        if (empty($amount)) {
            $amount = 0;
        }

        $preDiscountedAmount = 0;
        foreach ($itemList as $item) {
            $preDiscountedAmount += $item['listPrice'] * ($item['quantity']);
        }
        $preDiscountedAmount = round($preDiscountedAmount, 2);
        $attributes['preDiscountedAmount'] = $preDiscountedAmount;

        $attributes['taxAmount'] = 0;
        $attributes['shippingCost'] = 0;

        $discountAmount = $preDiscountedAmount - $amount;
        $attributes['discountAmount'] = $discountAmount;

        $grandTotalAmount = $amount + $attributes['taxAmount'] + $attributes['shippingCost'];
        $attributes['grandTotalAmount'] = $grandTotalAmount;

        $attributes['accountId'] = $source->get('accountId');
        $attributes['accountName'] = $source->get('accountName');

        $accountId = $source->get('accountId');

        if ($accountId) {

            if ($sourceType === 'Opportunity') {
                $account = $this->getEntityManager()->getEntity('Account', $accountId);
                if ($account) {
                    $attributes['billingAddressStreet'] = $account->get('billingAddressStreet');
                    $attributes['billingAddressCity'] = $account->get('billingAddressCity');
                    $attributes['billingAddressState'] = $account->get('billingAddressState');
                    $attributes['billingAddressCountry'] = $account->get('billingAddressCountry');
                    $attributes['billingAddressPostalCode'] = $account->get('billingAddressPostalCode');
                    $attributes['shippingAddressStreet'] = $account->get('shippingAddressStreet');
                    $attributes['shippingAddressCity'] = $account->get('shippingAddressCity');
                    $attributes['shippingAddressState'] = $account->get('shippingAddressState');
                    $attributes['shippingAddressCountry'] = $account->get('shippingAddressCountry');
                    $attributes['shippingAddressPostalCode'] = $account->get('shippingAddressPostalCode');
                }
            } else {
                $attributes['billingAddressStreet'] = $source->get('billingAddressStreet');
                $attributes['billingAddressCity'] = $source->get('billingAddressCity');
                $attributes['billingAddressState'] = $source->get('billingAddressState');
                $attributes['billingAddressCountry'] = $source->get('billingAddressCountry');
                $attributes['billingAddressPostalCode'] = $source->get('billingAddressPostalCode');
                $attributes['shippingAddressStreet'] = $source->get('shippingAddressStreet');
                $attributes['shippingAddressCity'] = $source->get('shippingAddressCity');
                $attributes['shippingAddressState'] = $source->get('shippingAddressState');
                $attributes['shippingAddressCountry'] = $source->get('shippingAddressCountry');
                $attributes['shippingAddressPostalCode'] = $source->get('shippingAddressPostalCode');
            }
        }

        return $attributes;
    }

    public function getAttributesForEmail($sourceId, $templateId, array $params = [])
    {
        $quote = $this->getEntityManager()->getEntity($this->getEntityType(), $sourceId);
        $template = $this->getEntityManager()->getEntity('Template', $templateId);
        if (!$quote || !$template) {
            throw new NotFound();
        }

        if (!$this->getAcl()->checkEntity($quote, 'read') || !$this->getAcl()->checkEntity($template, 'read')) {
            throw new Forbidden();
        }

        $data = [];
        $data['templateName'] = $template->get('name');

        $subjectTpl = $this->getTemplateFileManager()->getTemplate('salesEmailPdf', 'subject', $quote->getEntityType(), 'Sales');
        $bodyTpl = $this->getTemplateFileManager()->getTemplate('salesEmailPdf', 'body', $quote->getEntityType(), 'Sales');

        $htmlizer = $this->createHtmlizer();

        $subject = $htmlizer->render($quote, $subjectTpl, 'sales-email-pdf-subject-' . $quote->getEntityType(), $data, true);
        $body = $htmlizer->render($quote, $bodyTpl, 'sales-email-pdf-body-' . $quote->getEntityType(), $data, false);

        $attributes = [];

        $attributes['name'] = $subject;
        $attributes['body'] = $body;

        $attributes['nameHash'] = (object) [];

        $toList = [];

        if ($quote->get('opportunityId') && empty($params['skipOtherRecipients'])) {
            $attributes['parentId'] = $quote->get('opportunityId');
            $attributes['parentType'] = 'Opportunity';
            $attributes['parentName'] = $quote->get('opportunityName');

            $opportunity = $this->getEntityManager()->getEntity('Opportunity', $quote->get('opportunityId'));

            if ($opportunity) {
                $contactList = $opportunity->get('contacts');
                foreach ($contactList as $contact) {
                    $emailAddress = $contact->get('emailAddress');
                    if (!$emailAddress) continue;
                    $toList[] = $emailAddress;
                    $attributes['nameHash']->$emailAddress = $contact->get('name');
                }
            }
        }

        if ($quote->get('accountId')) {
            if (empty($attributes['parentId'])) {
                $attributes['parentId'] = $quote->get('accountId');
                $attributes['parentType'] = 'Account';
                $attributes['parentName'] = $quote->get('accountName');
            }

            $account = $this->getEntityManager()->getEntity('Account', $quote->get('accountId'));

            if ($account && $account->get('emailAddress')) {
                $emailAddress = $account->get('emailAddress');
                if (empty($toList)) {
                    $toList[] = $emailAddress;
                    $attributes['nameHash']->$emailAddress = $account->get('name');
                }
            }
        }

        $attributes['to'] = implode(';', $toList);

        if ($quote->get('billingContactId')) {
            $contact = $this->getEntityManager()->getEntity('Contact', $quote->get('billingContactId'));
            if ($contact && $contact->get('emailAddress')) {
                $emailAddress = $contact->get('emailAddress');
                $attributes['to'] = $emailAddress;
                $attributes['nameHash']->$emailAddress = $contact->get('name');
            }
        }

        $contents = $this->getServiceFactory()->create('Pdf')->buildFromTemplate($quote, $template);

        $attachment = $this->getEntityManager()->getEntity('Attachment');
        $attachment->set([
            'name' => \Espo\Core\Utils\Util::sanitizeFileName($template->get('name') . ' ' . $quote->get('name')) . '.pdf',
            'type' => 'application/pdf',
            'role' => 'Attachment',
            'contents' => $contents,
            'relatedId' => $quote->id,
            'relatedType' => $quote->getEntityType(),
        ]);

        $this->getEntityManager()->saveEntity($attachment);

        $attributes['attachmentsIds'] = [$attachment->id];
        $attributes['attachmentsNames'] = array(
            $attachment->id => $attachment->get('name')
        );
        $attributes['relatedId'] = $sourceId;
        $attributes['relatedType'] = $this->getEntityType();

        return $attributes;
    }

    public function getAttributesFromOpportunity($opportunityId)
    {
        return $this->getAttributesFromAnotherRecord('Opportunity', $opportunityId);
    }

    public function getConvertCurrencyValues(
        Entity $entity,
        string $targetCurrency,
        string $baseCurrency,
        $rates,
        bool $allFields = false,
        ?array $fieldList = null
    ) {
        $data = parent::getConvertCurrencyValues($entity, $targetCurrency, $baseCurrency, $rates, $allFields, $fieldList);

        $forbiddenFieldList = $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit');

        if (
            $allFields && !in_array('itemList', $forbiddenFieldList) &&
            (!$fieldList || in_array('amount', $fieldList))
        ) {
            $itemList = [];

            $itemService = $this->getServiceFactory()->create($this->itemEntityType);

            $itemFieldList = [];

            foreach ($this->getFieldManagerUtil()->getEntityTypeFieldList($this->itemEntityType) as $field) {
                if ($this->getMetadata()->get(['entityDefs', $this->itemEntityType, 'fields', $field, 'type']) !== 'currency') {
                    continue;
                }

                $itemFieldList[] = $field;
            }

            $itemCollection = $this->getEntityManager()
                ->getRepository($this->itemEntityType)
                ->where([
                    $this->itemParentIdAttribute => $entity->id
                ])
                ->order('order')
                ->find();

            foreach ($itemCollection as $item) {
                $values = $itemService->getConvertCurrencyValues($item, $targetCurrency, $baseCurrency, $rates, true, $itemFieldList);

                $item->set($values);
                $o = $item->getValueMap();
                $itemList[] = $o;
            }

            $data->itemList = $itemList;
        }

        return $data;
    }
}
