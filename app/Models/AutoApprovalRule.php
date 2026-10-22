<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoApprovalRule extends Model
{
    /** @use HasFactory<\Database\Factories\AutoApprovalRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'enabled', 'max_amount', 'known_vendors_only',
        'block_duplicates', 'allowed_currencies', 'daily_cap',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'max_amount' => 'decimal:2',
            'known_vendors_only' => 'boolean',
            'block_duplicates' => 'boolean',
            'allowed_currencies' => 'array',
            'daily_cap' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
