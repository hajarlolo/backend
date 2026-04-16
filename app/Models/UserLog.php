<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_type',
        'action_type',
        'target_type',
        'target_id',
        'description',
        'created_at'
    ];

    /**
     * Get the user that performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id_user');
    }

    /**
     * Get the target object.
     */
    public function target()
    {
        return $this->morphTo(null, 'target_type', 'target_id');
    }
}
