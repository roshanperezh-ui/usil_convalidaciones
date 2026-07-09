<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Lee el Excel de cursos a una colección normalizada.
 * Columnas esperadas (heading row): ciclo, codigo, nombre, creditos.
 */
class MallaCursosImport implements ToCollection, WithHeadingRow
{
    public Collection $filas;

    public function __construct()
    {
        $this->filas = collect();
    }

    public function collection(Collection $rows): void
    {
        $this->filas = $rows;
    }
}
