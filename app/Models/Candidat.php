<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidat extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_candidat';

    protected $fillable = [
        'id_user',
        'universite_id',
        'date_naissance',
        'telephone',
        'adresse',
        'lien_portfolio',
        'photo_profil',
        'cv_url',
        'profile_mode',
    ];

    protected $casts = [
        'date_naissance' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function universite()
    {
        return $this->belongsTo(Universite::class, 'universite_id');
    }

    public function experiences()
    {
        return $this->hasMany(Experience::class, 'id_candidat');
    }

    public function formations()
    {
        return $this->hasMany(Formation::class, 'id_candidat');
    }

    public function certificats()
    {
        return $this->hasMany(Certificat::class, 'id_candidat');
    }

    public function projets()
    {
        return $this->hasMany(Projet::class, 'id_candidat');
    }

    public function competences()
    {
        return $this->belongsToMany(Competence::class, 'competence_candidat', 'id_candidat', 'id_competence');
    }

    public function postulationsFreelance()
    {
        return $this->hasMany(PostulationFreelance::class, 'id_candidat');
    }

    public function postulationsEmploi()
    {
        return $this->hasMany(PostulationEmploi::class, 'id_candidat');
    }

    public function postulationsStage()
    {
        return $this->hasMany(PostulationStage::class, 'id_candidat');
    }
}
