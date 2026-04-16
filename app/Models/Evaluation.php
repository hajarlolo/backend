<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $primaryKey = ['id_entreprise', 'id_candidat', 'date_evaluation', 'evaluator_role'];
    public $timestamps = false; // Using custom date_evaluation

    protected $fillable = [
        'id_entreprise',
        'id_candidat',
        'evaluator_role',
        'note',
        'commentaire',
        'date_evaluation',
        'statut_mission',
    ];

    protected $casts = [
        'note' => 'decimal:2',
        'date_evaluation' => 'datetime',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class, 'id_entreprise');
    }

    public function candidat()
    {
        return $this->belongsTo(Candidat::class, 'id_candidat');
    }

    protected function setKeysForSaveQuery($query)
    {
        $query->where('id_entreprise', '=', $this->getAttribute('id_entreprise'))
              ->where('id_candidat', '=', $this->getAttribute('id_candidat'))
              ->where('date_evaluation', '=', $this->getAttribute('date_evaluation'))
              ->where('evaluator_role', '=', $this->getAttribute('evaluator_role'));
        return $query;
    }
}
