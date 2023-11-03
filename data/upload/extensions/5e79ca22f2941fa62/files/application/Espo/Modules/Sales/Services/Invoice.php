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

use \Espo\ORM\Entity;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\Forbidden;

class Invoice extends Quote
{
    protected $itemEntityType = 'InvoiceItem';

    protected $itemParentIdAttribute = 'invoiceId';

    public function getAttributesFromQuote($quoteId)
    {
        return $this->getAttributesFromAnotherRecord('Quote', $quoteId);
    }

    public function getAttributesFromSalesOrder($salesOrderId)
    {
        return $this->getAttributesFromAnotherRecord('SalesOrder', $salesOrderId);
    }
}
