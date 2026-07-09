<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquivalenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'carrera_externa_id' => ['required', 'exists:carreras_externas,id'],
            'carrera_usil_id'    => ['required', 'exists:carreras,id'],
            'curso_externo_id'   => ['required', 'exists:cursos_externos,id'],
            'curso_usil_id'      => ['required', 'exists:cursos_usil,id'],
            'tipo_equivalencia'  => ['required', 'in:completa,parcial'],
        ];
    }
}
