<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchingResult extends Model
{
    use HasFactory;

    protected $table = 'matching_results';

    public $timestamps = false; // We only have created_at natively

    protected $fillable = [
        'candidat_id',
        'offer_id',
        'offer_type',
        'score_total',
        'score_skills',
        'score_experience',
        'score_location',
    ];

    protected $casts = [
        'score_total' => 'float',
        'score_skills' => 'float',
        'score_experience' => 'float',
        'score_location' => 'float',
        'created_at' => 'datetime',
    ];

    public function candidat()
    {
        return $this->belongsTo(Candidat::class, 'candidat_id', 'id_candidat');
    }

    public function offer()
    {
        // Polymorphic-like relation to the respective offer tables depending on offer_type.
        // It's best resolved via custom accessor or helper method if needed.
    }
}
