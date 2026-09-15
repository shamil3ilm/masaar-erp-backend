<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

/**
 * A scheduling agreement as stored, with its vendor sent through ContactResource.
 */
class SchedulingAgreementResource extends SupplierRecordResource
{
    protected const CONTACT_RELATIONS = ['vendor'];
}
