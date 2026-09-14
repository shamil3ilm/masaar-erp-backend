<?php

declare(strict_types=1);

namespace App\Exceptions\ERP;

/**
 * A request a service refuses because a business rule forbids it, carrying the
 * error code and HTTP status the API reports for that rule.
 *
 * Services throw it so the rule is checked wherever the action is started;
 * controllers turn it into their error response.
 */
final class BusinessRuleException extends ErpException
{
    public function __construct(string $message, string $errorCode, int $httpStatus = 422)
    {
        parent::__construct($message);

        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }
}
