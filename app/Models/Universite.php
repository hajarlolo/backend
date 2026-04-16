<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Universite extends Model
{
    use HasFactory;

    protected $primaryKey = 'id_universite';

    protected $fillable = [
        'nom',
        'abbreviation',
        'ville',
        'pays',
    ];

    public function candidats()
    {
        return $this->hasMany(Candidat::class, 'universite_id');
    }

    public function formations()
    {
        return $this->hasMany(Formation::class, 'id_universite');
    }
}
