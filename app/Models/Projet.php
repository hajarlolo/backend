<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Projet extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_projet';

    protected $fillable = [
        'id_candidat',
        'titre',
        'description',
        'lien_demo',
        'lien_code',
        'image_apercu',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function candidat()
    {
        return $this->belongsTo(Candidat::class, 'id_candidat');
    }

    public function technologies()
    {
        return $this->belongsToMany(Competence::class, 'projet_technologie', 'id_projet', 'id_competence');
    }
}
