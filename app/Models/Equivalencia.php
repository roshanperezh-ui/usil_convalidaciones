<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equivalencia extends Model
{
    use SoftDeletes;

    protected $table = 'equivalencias';
    protected $fillable = [
        'carrera_externa_id', 'carrera_usil_id', 'curso_externo_id', 'curso_usil_id',
        'tipo_equivalencia', 'confianza_ia', 'origen', 'usuario_id',
    ];
    protected $casts = ['confianza_ia' => 'decimal:2'];

    public function cursoUsil(): BelongsTo
    {
        return $this->belongsTo(CursoUsil::class, 'curso_usil_id');
    }

    public function cursoExterno(): BelongsTo
    {
        return $this->belongsTo(CursoExterno::class, 'curso_externo_id');
    }
}
