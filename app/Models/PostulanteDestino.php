<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Destino USIL solicitado por un postulante (una fila por carrera contra la
 * que se desea simular la convalidación). Lleva su propio estado de revisión.
 */
class PostulanteDestino extends Model
{
    protected $table = 'postulante_destinos';

    protected $fillable = [
        'postulante_id', 'carrera_id', 'asignado_a_id', 'estado_equivalencias',
        'equivalencias_revisado_por', 'equivalencias_revisado_en', 'observacion_flujo',
    ];

    protected $casts = [
        'equivalencias_revisado_en' => 'datetime',
    ];

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(Postulante::class, 'postulante_id');
    }

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }

    /** Coordinador al que se asignó la evaluación de este destino. */
    public function asignadoA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a_id');
    }
}
