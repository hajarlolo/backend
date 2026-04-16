<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostulationFreelance extends Model
{
    use HasFactory;

    protected $table = 'postulation_freelances';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id_mission', 'id_candidat'];

    protected $fillable = [
        'id_mission',
        'id_candidat',
        'date_postulation',
        'statut',
        'documents',
    ];

    protected $casts = [
        'date_postulation' => 'datetime',
        'documents' => 'json',
    ];

    public function mission()
    {
        return $this->belongsTo(MissionFreelance::class, 'id_mission');
    }

    public function candidat()
    {
        return $this->belongsTo(Candidat::class, 'id_candidat');
    }

    // Since Laravel doesn't support composite keys well for Eloquent, 
    // we might need a workaround for find() if needed, but for now this is fine.
    protected function setKeysForSaveQuery($query)
    {
        $query->where('id_mission', '=', $this->getAttribute('id_mission'))
              ->where('id_candidat', '=', $this->getAttribute('id_candidat'));
        return $query;
    }
}
