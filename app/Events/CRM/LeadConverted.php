<?php

declare(strict_types=1);

namespace App\Events\CRM;

use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\Sales\Contact;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadConverted implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Lead $lead,
        public Contact $contact,
        public ?Opportunity $opportunity = null
    ) {}
}
