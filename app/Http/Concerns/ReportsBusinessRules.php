<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Exceptions\ERP\BusinessRuleException;
use Illuminate\Http\JsonResponse;

/**
 * Turns a business rule a service refused into the controller's standard
 * error response, with the rule's own code and HTTP status.
 */
trait ReportsBusinessRules
{
    protected function ruleError(BusinessRuleException $e): JsonResponse
    {
        return $this->error($e->getMessage(), $e->getErrorCode(), $e->getHttpStatus());
    }
}
