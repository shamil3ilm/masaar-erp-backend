<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

/**
 * A supplier delivery record as stored, with its supplier sent through ContactResource.
 */
class SupplierDeliveryRecordResource extends SupplierRecordResource
{
    protected const CONTACT_RELATIONS = ['supplier'];
}
