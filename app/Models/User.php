<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
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
        ];
    }
}
