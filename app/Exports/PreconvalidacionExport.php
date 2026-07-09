<?php

namespace App\Exports;

use App\Exports\Sheets\FormatoErpSheet;
use App\Exports\Sheets\NoConvalidadosSheet;
use App\Exports\Sheets\PreconvalidacionSheet;
use App\Models\Simulacion;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Preconvalidación en Excel con formato institucional y 3 hojas:
 *   1. Preconvalidación   — cabecera + cursos convalidados (formato oficial).
 *   2. Cursos no convalidados — no convalidables y desaprobados (constancia).
 *   3. Formato ERP        — tabla plana para importación al ERP.
 */
class PreconvalidacionExport implements WithMultipleSheets
{
    public function __construct(private Simulacion $simulacion)
    {
        $this->simulacion->loadMissing([
            'carreraUsil.facultad', 'carreraExterna', 'postulante.institucionOrigen',
            'detalles.cursoUsil.ciclo', 'detalles.cursoExterno',
        ]);
    }

    public function sheets(): array
    {
        return [
            new PreconvalidacionSheet($this->simulacion),
            new NoConvalidadosSheet($this->simulacion),
            new FormatoErpSheet($this->simulacion),
        ];
    }
}
