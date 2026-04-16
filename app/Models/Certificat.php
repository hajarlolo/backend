<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificat extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_certificat';

    protected $fillable = [
        'id_candidat',
        'titre',
        'organisme',
        'date_obtention',
        'certificat_document',
    ];

    protected $casts = [
        'date_obtention' => 'date',
    ];

    public function candidat()
    {
        return $this->belongsTo(Candidat::class, 'id_candidat');
    }
}
