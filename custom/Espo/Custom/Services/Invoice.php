<?php

namespace Espo\Custom\Services;

use \Espo\ORM\Entity;

class Invoice extends \Espo\Modules\Sales\Services\Invoice
{
    public function getAttributesFromCase($caseId)
    {
        $source = $this->getEntityManager()->getEntity('Case', $caseId);

        if (!$source) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($source, 'read')) {
            throw new Forbidden();
        }

        $sourceItemList = $this->getEntityManager()->getRepository('CaseItem')->where(['caseId' => $source->id])->order('order')->find();
        $itemList = [];
        $itemNotClosed = false;
        $itemSeed = $this->getEntityManager()->getEntity($this->itemEntityType);

        foreach ($sourceItemList as $item)
        {
            if($item->get('status') == 'Closed') {
                if (method_exists($item, 'loadAllLinkMultipleFields')) {
                    $item->loadAllLinkMultipleFields();
                }

                $itemAttributes = [
                    'name' => $item->get('name'),
                    'productId' => $item->get('productId'),
                    'productName' => $item->get('productName'),
                    'unitPrice' => $item->get('unitPrice'),
                    'unitPriceCurrency' => $item->get('unitPriceCurrency'),
                    'amount' => $item->get('amount'),
                    'amountCurrency' => $item->get('amountCurrency'),
                    'quantity' => $item->get('quantity'),
                    'taxRate' => $item->get('taxRate') ?? 0,
                    'listPrice' => $item->get('listPrice'),
                    'listPriceCurrency' => $item->get('amountCurrency'),
                    'description' => $item->get('description'),
                    'createdById' => $item->get('createById'),
                    'createdByName' => $item->get('createByName')
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
                    }
                }

                foreach ($itemSeed->getAttributeList() as $attribute) {
                    if (!$item->hasAttribute($attribute)) continue;
                    if (array_key_exists($attribute, $itemAttributes)) continue;
                    if (in_array($attribute, [
                        'id',
                        'modifiedById',
                        'modifiedByName',
                        'createdAt',
                        'modifiedAt',
                    ])) continue;

                    $itemAttributes[$attribute] = $item->get($attribute);
                }

                $itemList[] = $itemAttributes;
            }
            else {
                $itemNotClosed = true;
            }
        }

        $source->loadLinkMultipleField('teams');

        $attributes = [
            'name' => $source->get('name'),
            'teamsIds' => $source->get('teamsIds'),
            'teamsNames' => $source->get('teamsNames'),
            'caseId' => $caseId,
            'itemList' => $itemList,
            'amount' => $source->get('amount'),
            'amountCurrency' => $source->get('amountCurrency'),
            'preDiscountedAmountCurrency' => $source->get('amountCurrency'),
            'taxAmountCurrency' => $source->get('amountCurrency'),
            'grandTotalAmountCurrency' => $source->get('amountCurrency'),
            'discountAmountCurrency' => $source->get('amountCurrency'),
            'shippingCostCurrency' => $source->get('amountCurrency'),
            'itemNotClosed' => $itemNotClosed
        ];

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
        }

        return $attributes;
    }
}
