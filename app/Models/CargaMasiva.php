<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargaMasiva extends Model
{
    protected $table = 'cargas_masivas';

    protected $fillable = [
        'usuario_id', 'malla_id', 'archivo', 'estado',
        'total', 'procesados', 'errores', 'detalle_errores',
    ];

    protected $casts = ['detalle_errores' => 'array'];

    public function porcentaje(): int
    {
        return $this->total > 0 ? (int) round(($this->procesados / $this->total) * 100) : 0;
    }

    public function malla(): BelongsTo
    {
        return $this->belongsTo(MallaCurricular::class, 'malla_id');
    }
}
