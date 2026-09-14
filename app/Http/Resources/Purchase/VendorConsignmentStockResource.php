<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

/**
 * A consignment stock record as stored, with its vendor sent through ContactResource.
 */
class VendorConsignmentStockResource extends SupplierRecordResource
{
    protected const CONTACT_RELATIONS = ['vendor'];
}
