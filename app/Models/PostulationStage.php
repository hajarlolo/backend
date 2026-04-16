<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostulationStage extends Model
{
    use HasFactory;

    protected $table = 'postulation_stages';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id_offre_stage', 'id_candidat'];

    protected $fillable = [
        'id_offre_stage',
        'id_candidat',
        'date_postulation',
        'statut',
        'documents',
    ];

    protected $casts = [
        'date_postulation' => 'datetime',
        'documents' => 'json',
    ];

    public function offre()
    {
        return $this->belongsTo(OffreStage::class, 'id_offre_stage');
    }

    public function candidat()
    {
        return $this->belongsTo(Candidat::class, 'id_candidat');
    }

    protected function setKeysForSaveQuery($query)
    {
        $query->where('id_offre_stage', '=', $this->getAttribute('id_offre_stage'))
              ->where('id_candidat', '=', $this->getAttribute('id_candidat'));
        return $query;
    }
}
