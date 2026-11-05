<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_MEMBER = 'member';

    public const ROLE_AFFILIATE_KADER = 'affiliate_kader';

    public const ROLE_BACKOFFICE = 'backoffice';

    public const PARTNER_WARUNG = 'warung';

    public const PARTNER_PRIORITAS = 'prioritas';
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'numart_customer_id',
        'member_card',
        'member_cabang',
        'price_tier',
        'phone',
        'address',
        'warung_name',
        'warung_verification_status',
        'member_verification_status',
        'ktp_path',
        'business_photo_path',
        'verification_submitted_at',
        'verification_reviewed_at',
        'must_change_password',
        'wa_password_sent_at',
        'marketplace_role',
        'partner_kind',
        'affiliate_kader_id',
        'affiliate_code',
        'affiliate_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
            'must_change_password' => 'boolean',
            'wa_password_sent_at' => 'datetime',
            'verification_submitted_at' => 'datetime',
            'verification_reviewed_at' => 'datetime',
            'affiliate_active' => 'boolean',
        ];
    }

    public function affiliateKader(): BelongsTo
    {
        return $this->belongsTo(self::class, 'affiliate_kader_id');
    }

    public function referredMembers(): HasMany
    {
        return $this->hasMany(self::class, 'affiliate_kader_id');
    }

    public function affiliateCommissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'affiliate_user_id');
    }

    public function isAffiliateKader(): bool
    {
        return $this->marketplace_role === self::ROLE_AFFILIATE_KADER;
    }

    public function partnerKindLabel(): ?string
    {
        return match ($this->partner_kind) {
            self::PARTNER_WARUNG => 'Mitra warung',
            self::PARTNER_PRIORITAS => 'Mitra prioritas',
            'referral' => 'Member referral',
            default => null,
        };
    }
}
