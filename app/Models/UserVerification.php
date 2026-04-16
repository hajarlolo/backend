<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVerification extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_verification';

    protected $fillable = [
        'id_user',
        'verification_code',
        'code_expires_at',
        'verification_document',
        'status',
        'status_note',
    ];

    protected $casts = [
        'code_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
