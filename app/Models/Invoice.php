<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    // Lifecycle
    public const STATUS_RECEIVED = 'received';       // just arrived, not yet read
    public const STATUS_EXTRACTING = 'extracting';   // AI is reading it
    public const STATUS_NEEDS_REVIEW = 'needs_review'; // waiting on a human
    public const STATUS_APPROVED = 'approved';       // cleared, queued to pay
    public const STATUS_SCHEDULED = 'scheduled';     // will pay on due date
    public const STATUS_PAYING = 'paying';           // payout in flight
    public const STATUS_PAID = 'paid';               // done
    public const STATUS_HELD = 'held';               // human parked it
    public const STATUS_REJECTED = 'rejected';       // human declined it
    public const STATUS_FAILED = 'failed';           // payout failed

    protected $fillable = [
        'user_id', 'vendor_id', 'source', 'status',
        'original_filename', 'file_path',
        'vendor_name', 'invoice_number', 'amount', 'currency',
        'issue_date', 'due_date', 'po_ref', 'line_items',
        'confidence', 'review_reasons',
        'external_ref', 'metadata', 'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'issue_date' => 'date',
            'due_date' => 'date',
            'line_items' => 'array',
            'review_reasons' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return HasMany<Payout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function isPayable(): bool
    {
        return $this->amount > 0
            && filled($this->currency)
            && $this->vendor?->isPayable();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_PAID, self::STATUS_REJECTED, self::STATUS_FAILED,
        ], true);
    }
}
