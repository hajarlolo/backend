<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OffreEmploi extends Model
{
    use HasFactory;

    protected $table = 'offre_emplois';
    protected $primaryKey = 'id_offre_emploi';

    protected $fillable = [
        'id_entreprise',
        'poste',
        'description',
        'document_requise',
        'experience_requise',
        'city',
        'country',
        'salaire',
        'statut',
        'date_publication',
    ];

    protected $casts = [
        'salaire' => 'decimal:2',
        'date_publication' => 'datetime',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class, 'id_entreprise');
    }

    public function competences()
    {
        return $this->belongsToMany(Competence::class, 'offre_emploi_competence', 'id_offre_emploi', 'id_competence');
    }

    public function postulations()
    {
        return $this->hasMany(PostulationEmploi::class, 'id_offre_emploi');
    }
}
