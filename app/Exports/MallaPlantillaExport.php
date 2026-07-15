<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Plantilla de importación de cursos de una malla, con dos hojas:
 *  - "Cursos": cabeceras + filas de ejemplo (las que admite el importador).
 *  - "Instrucciones": formato y reglas de validación.
 */
class MallaPlantillaExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new PlantillaCursosSheet,
            new PlantillaInstruccionesSheet,
        ];
    }
}

/** Hoja 1: datos a llenar. */
class PlantillaCursosSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Cursos';
    }

    public function headings(): array
    {
        return ['ciclo', 'codigo', 'nombre', 'creditos', 'horas_teoria', 'horas_practica', 'caracter', 'mencion'];
    }

    public function array(): array
    {
        // Filas de ejemplo (elimínelas y agregue sus cursos).
        return [
            [1, 'MAT101', 'Cálculo I', 4, 2, 4, 'Obligatorio', ''],
            [1, 'PRG101', 'Fundamentos de Programación', 3, 2, 2, 'Obligatorio', ''],
            [2, 'ELE201', 'Curso Electivo (ejemplo)', 4, 3, 2, 'Electivo', ''],
            [6, 'ESP601', 'Curso de especialidad (ejemplo)', 4, 3, 2, 'Electivo', 'Analítica de datos'],
        ];
    }
}

/** Hoja 2: instrucciones y validaciones. */
class PlantillaInstruccionesSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Instrucciones';
    }

    public function headings(): array
    {
        return ['Plantilla de importación de cursos — USIL Convalidaciones'];
    }

    public function array(): array
    {
        return array_map(fn ($l) => [$l], [
            '',
            'Cómo usar esta plantilla:',
            '1) Vaya a la hoja "Cursos".',
            '2) Borre las 3 filas de ejemplo y agregue una fila por cada curso.',
            '3) Guarde el archivo y súbalo con el botón "Importar Excel".',
            '',
            'Columnas (no cambie los nombres de la fila de cabecera):',
            'ciclo            (obligatorio) Número entero del 1 al 14.',
            'codigo           (obligatorio) Código del curso. Máx. 30 caracteres.',
            'nombre           (obligatorio) Nombre del curso. Máx. 200 caracteres.',
            'creditos         (obligatorio) Número mayor que 0 (admite decimales, ej. 2.5).',
            'horas_teoria     (opcional)    Número mayor o igual a 0.',
            'horas_practica   (opcional)    Número mayor o igual a 0.',
            'caracter         (opcional)    "Obligatorio" o "Electivo". Por defecto: Obligatorio.',
            'mencion          (opcional)    Nombre de la mención/especialidad. Vacío = curso del plan regular.',
            '',
            'Reglas de validación:',
            '- El número de ciclo debe estar entre 1 y 14; si el ciclo no existe, se crea automáticamente.',
            '- Los créditos deben ser un número mayor que 0.',
            '- No deje filas totalmente vacías entre los cursos.',
            '- Las filas con errores se omiten y se reportan indicando el número de línea.',
            '- La importación AGREGA cursos a la malla (no reemplaza los existentes).',
        ]);
    }
}
