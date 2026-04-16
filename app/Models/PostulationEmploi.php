<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostulationEmploi extends Model
{
    use HasFactory;

    protected $table = 'postulation_emplois';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = ['id_offre_emploi', 'id_candidat'];

    protected $fillable = [
        'id_offre_emploi',
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
        return $this->belongsTo(OffreEmploi::class, 'id_offre_emploi');
    }

    public function candidat()
    {
        return $this->belongsTo(Candidat::class, 'id_candidat');
    }

    protected function setKeysForSaveQuery($query)
    {
        $query->where('id_offre_emploi', '=', $this->getAttribute('id_offre_emploi'))
              ->where('id_candidat', '=', $this->getAttribute('id_candidat'));
        return $query;
    }
}
