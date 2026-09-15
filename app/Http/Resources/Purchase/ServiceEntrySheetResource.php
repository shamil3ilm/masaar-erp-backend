<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

/**
 * A service entry sheet as stored, with its vendor sent through ContactResource.
 */
class ServiceEntrySheetResource extends SupplierRecordResource
{
    protected const CONTACT_RELATIONS = ['vendor'];
}
