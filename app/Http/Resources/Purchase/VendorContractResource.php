<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

/**
 * A vendor contract as stored, with its contact sent through ContactResource.
 */
class VendorContractResource extends SupplierRecordResource
{
    protected const CONTACT_RELATIONS = ['contact'];
}
