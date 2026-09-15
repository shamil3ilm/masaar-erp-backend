<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

/**
 * An outline agreement as stored, with its vendor sent through ContactResource.
 */
class OutlineAgreementResource extends SupplierRecordResource
{
    protected const CONTACT_RELATIONS = ['vendor'];
}
