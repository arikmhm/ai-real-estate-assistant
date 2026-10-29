<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property bool $is_active
 * @property string|null $whatsapp_number
 * @property string|null $contact_email
 * @property bool $public_contact_approved
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'whatsapp_number', 'contact_email', 'public_contact_approved'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->isDirty(['whatsapp_number', 'contact_email'])) {
                $user->public_contact_approved = false;
            }
        });
    }

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
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'public_contact_approved' => 'boolean',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /** Public WhatsApp link, or null when the contact is not approved or the number is invalid. */
    public function whatsappUrl(?Listing $listing = null): ?string
    {
        if (! $this->public_contact_approved || ! preg_match('/^\+[1-9]\d{7,14}$/', $this->whatsapp_number ?? '')) {
            return null;
        }
        $message = $listing
            ? 'Halo, saya ingin bertanya tentang '.$listing->title.' ('.$listing->code.'): '.route('properties.show', $listing)
            : 'Halo, saya ingin bertanya tentang properti di '.config('app.name').'.';

        return 'https://wa.me/'.substr($this->whatsapp_number, 1).'?text='.rawurlencode($message);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** @return HasMany<Prospect, $this> */
    public function ownedProspects(): HasMany
    {
        return $this->hasMany(Prospect::class, 'owner_id');
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActiveSales(Builder $query): Builder
    {
        return $query->where('role', UserRole::Sales->value)->where('is_active', true);
    }
}
