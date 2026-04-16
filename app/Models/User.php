<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens, \Illuminate\Auth\MustVerifyEmail;

    protected $primaryKey = 'id_user';

    public const STATUS_PENDING_EMAIL = 'pending_email';
    public const STATUS_PENDING_DOCUMENT = 'pending_document';
    public const STATUS_REVISION_REQUIRED = 'revision_required';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'nom',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function normalizedVerificationStatus(): string
    {
        $status = $this->latestVerification?->status;
        
        return match ($status) {
            self::STATUS_PENDING_EMAIL => 'Email en attente',
            self::STATUS_PENDING_DOCUMENT => 'Document en attente',
            self::STATUS_REVISION_REQUIRED => 'Révision requise',
            self::STATUS_APPROVED => 'Approuvé',
            self::STATUS_REJECTED => 'Rejeté',
            default => $status ?? 'Non initié',
        };
    }

    public function latestVerification()
    {
        return $this->hasOne(UserVerification::class, 'id_user')->latestOfMany('id_verification');
    }

    public function candidat()
    {
        return $this->hasOne(Candidat::class, 'id_user');
    }

    public function etudiant()
    {
        return $this->hasOne(Candidat::class, 'id_user');
    }

    public function entreprise()
    {
        return $this->hasOne(Entreprise::class, 'id_user');
    }

    public function verifications()
    {
        return $this->hasMany(UserVerification::class, 'id_user');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function isAdmin(): bool
    {
        return strtolower($this->role) === 'admin';
    }

    public function isStudent(): bool
    {
        return strtolower($this->role) === 'student';
    }

    public function isCompany(): bool
    {
        return in_array(strtolower($this->role), ['company', 'entreprise']);
    }

    public function isLaureat(): bool
    {
        return in_array(strtolower($this->role), ['lauriat', 'laureat']);
    }

    public function isApprovedForAccess(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $verification = $this->latestVerification;
        
        if (!$verification) {
            // If no verification record exists, create one (fallback)
            $verification = \App\Models\UserVerification::create([
                'id_user' => $this->id_user,
                'verification_code' => str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'code_expires_at' => now()->addMinutes(15),
                'status' => self::STATUS_PENDING_EMAIL,
            ]);
        }

        if ($this->isStudent() || $this->isLaureat()) {
            $this->notify(new \App\Notifications\StudentVerificationCodeNotification($verification->verification_code));
        } elseif ($this->isCompany()) {
            $this->notify(new \App\Notifications\CompanyVerificationCodeNotification($verification->verification_code));
        }
    }
}