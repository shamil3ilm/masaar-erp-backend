<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

/**
 * A consignment settlement as stored, with its vendor sent through
 * ContactResource and its bill through BillResource.
 */
class VendorConsignmentSettlementResource extends SupplierRecordResource
{
    protected const CONTACT_RELATIONS = ['vendor'];

    protected const BILL_RELATIONS = ['bill'];
}
