<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'handle',
        'default_currency',
        'payout_routes',
        'dwolla_customer_id',
        'dwolla_funding_source_id',
        'dwolla_bank_name',
        'dwolla_connected_at',
    ];

    /** True once the business has connected a bank funding source for ACH payouts. */
    public function dwollaConnected(): bool
    {
        return filled($this->dwolla_funding_source_id);
    }

    /** A unique public intake handle derived from $base (name or email). */
    public static function makeHandle(string $base): string
    {
        $slug = Str::slug($base) ?: 'user';
        $handle = $slug;
        $i = 1;
        while (static::where('handle', $handle)->exists()) {
            $handle = $slug.($i++);
        }

        return $handle;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'payout_routes' => 'array',
            'dwolla_connected_at' => 'datetime',
        ];
    }

    /** @return HasMany<Vendor, $this> */
    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<Payout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    /** @return HasOne<AutoApprovalRule, $this> */
    public function autoApprovalRule(): HasOne
    {
        return $this->hasOne(AutoApprovalRule::class);
    }
}
