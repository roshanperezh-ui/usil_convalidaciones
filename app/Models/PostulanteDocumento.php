<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostulanteDocumento extends Model
{
    protected $table = 'postulante_documentos';

    protected $fillable = ['postulante_id', 'tipo', 'nombre_original', 'ruta', 'tamano'];

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(Postulante::class, 'postulante_id');
    }
}
