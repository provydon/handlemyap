<?php

namespace App\Models;

use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'email', 'country', 'currency', 'external_ref', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<VendorPayoutMethod, $this> */
    public function payoutMethods(): HasMany
    {
        return $this->hasMany(VendorPayoutMethod::class);
    }

    /** The vendor's payout destination on a given rail, or null. */
    public function payoutMethod(string $rail): ?VendorPayoutMethod
    {
        return $this->relationLoaded('payoutMethods')
            ? $this->payoutMethods->firstWhere('rail', $rail)
            : $this->payoutMethods()->where('rail', $rail)->first();
    }

    public function hasPayoutMethod(string $rail): bool
    {
        return $this->relationLoaded('payoutMethods')
            ? $this->payoutMethods->contains('rail', $rail)
            : $this->payoutMethods()->where('rail', $rail)->exists();
    }

    /** Can we actually send this vendor money? */
    public function isPayable(): bool
    {
        return filled($this->email);
    }
}
