<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entreprise extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_entreprise';

    protected $fillable = [
        'id_user',
        'ice',
        'email_professionnel',
        'localisation',
        'description',
        'telephone',
        'secteur_activite',
        'taille',
        'site_web',
        'logo_url',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function missions()
    {
        return $this->hasMany(MissionFreelance::class, 'id_entreprise');
    }

    public function offresEmploi()
    {
        return $this->hasMany(OffreEmploi::class, 'id_entreprise');
    }

    public function offresStage()
    {
        return $this->hasMany(OffreStage::class, 'id_entreprise');
    }
}
