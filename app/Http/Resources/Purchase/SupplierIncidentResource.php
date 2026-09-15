<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchase;

/**
 * A supplier incident as stored, with its supplier sent through ContactResource.
 */
class SupplierIncidentResource extends SupplierRecordResource
{
    protected const CONTACT_RELATIONS = ['supplier'];
}
