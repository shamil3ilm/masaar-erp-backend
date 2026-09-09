<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Sales\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRevaluationItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function revaluation(): BelongsTo
    {
        return $this->belongsTo(CurrencyRevaluation::class, 'revaluation_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
