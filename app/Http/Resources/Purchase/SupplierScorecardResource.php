<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

/**
 * A supplier scorecard as stored, with its supplier sent through ContactResource.
 */
class SupplierScorecardResource extends SupplierRecordResource
{
    protected const CONTACT_RELATIONS = ['supplier'];
}
