<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MallaExterna extends Model
{
    protected $table = 'mallas_externas';

    protected $fillable = [
        'carrera_externa_id',
        'anio',
        'version',
        'activa',
        'pdf_path',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    public function carreraExterna()
    {
        return $this->belongsTo(CarreraExterna::class, 'carrera_externa_id');
    }

    public function cursos()
    {
        return $this->hasMany(CursoExterno::class, 'malla_externa_id');
    }
}
