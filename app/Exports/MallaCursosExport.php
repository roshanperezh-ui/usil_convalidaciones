<?php

namespace App\Exports;

use App\Models\MallaCurricular;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exporta los cursos de una malla a Excel (RF-37). El formato coincide con el
 * que admite la importación (ciclo, codigo, nombre, creditos).
 */
class MallaCursosExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private MallaCurricular $malla) {}

    public function collection(): Collection
    {
        return $this->malla->ciclos()
            ->with('cursos')
            ->orderBy('numero')
            ->get()
            ->flatMap(fn ($ciclo) => $ciclo->cursos->map(fn ($curso) => [
                'ciclo'          => $ciclo->numero,
                'codigo'         => $curso->codigo,
                'nombre'         => $curso->nombre,
                'creditos'       => $curso->creditos,
                'horas_teoria'   => $curso->horas_teoria,
                'horas_practica' => $curso->horas_practica,
                'caracter'       => $curso->es_electivo ? 'Electivo' : 'Obligatorio',
            ]));
    }

    public function headings(): array
    {
        return ['ciclo', 'codigo', 'nombre', 'creditos', 'horas_teoria', 'horas_practica', 'caracter'];
    }

    public function map($fila): array
    {
        return [
            $fila['ciclo'], $fila['codigo'], $fila['nombre'], $fila['creditos'],
            $fila['horas_teoria'], $fila['horas_practica'], $fila['caracter'],
        ];
    }
}
