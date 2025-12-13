<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'balance' => 'decimal:2',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->balance >= $amount;
    }

    public function doseNotHaveSufficientBalance(float $amount): bool
    {
        return ! $this->hasSufficientBalance($amount);
    }

    public function hasSufficientAsset(string $symbol, float $amount): bool
    {
        $asset = $this->assets()->where('symbol', $symbol)->first();

        if (! $asset) {
            return false;
        }

        return $asset->amount >= $amount;
    }

    public function doseNotHaveSufficientAsset(string $symbol, float $amount): bool
    {
        return ! $this->hasSufficientAsset($symbol, $amount);
    }

    public function incrementAsset(string $symbol, float $amount): void
    {
        $asset = $this->assets()->firstOrCreate(
            ['symbol' => $symbol],
            ['amount' => 0, 'locked_amount' => 0]
        );

        $asset->increment('amount', $amount);
    }

    public function decrementAsset(string $symbol, float $amount): void
    {
        $asset = $this->assets()->where('symbol', $symbol)->firstOrFail();

        $asset->decrement('amount', $amount);
        $asset->increment('locked_amount', $amount);
    }
}
