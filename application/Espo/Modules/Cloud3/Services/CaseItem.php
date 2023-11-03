<?php

namespace Espo\Modules\Cloud3\Services;

class CaseItem extends \Espo\Services\Record
{
    protected $readOnlyAttributeList = [
        'unitPrice',
        'unitPriceCurrency',
        'quantity',
        'amount',
        'amountCurrency',
        'productId',
        'opportunityId'
    ];

}
