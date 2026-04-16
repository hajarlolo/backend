<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Formation extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_formation';

    protected $fillable = [
        'id_candidat',
        'diplome',
        'filiere',
        'id_universite',
        'niveau',
        'date_debut',
        'date_fin',
        'en_cours',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'en_cours' => 'boolean',
    ];

    public function candidat()
    {
        return $this->belongsTo(Candidat::class, 'id_candidat');
    }

    public function universite()
    {
        return $this->belongsTo(Universite::class, 'id_universite');
    }
}
