<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * RF-37: exportación de reportes de convalidaciones a Excel.
 */
class ConvalidacionesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Collection $filas) {}

    public function collection(): Collection
    {
        return $this->filas;
    }

    public function headings(): array
    {
        return ['Facultad', 'Carrera', 'Estudiante', 'Documento', 'Memorándum', 'Fecha', 'Estado'];
    }

    public function map($fila): array
    {
        return [
            $fila['facultad'], $fila['carrera'], $fila['estudiante'],
            $fila['documento'], $fila['memorandum'], $fila['fecha'], $fila['estado'],
        ];
    }
}
