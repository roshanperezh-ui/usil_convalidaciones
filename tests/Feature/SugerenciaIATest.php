<?php

namespace Tests\Feature;

use App\Models\CursoExterno;
use App\Services\Seudonimizador;
use App\Services\SugerenciaIAService;
use PHPUnit\Framework\TestCase;

class SugerenciaIATest extends TestCase
{
    /** RNF-09: la seudonimización elimina datos personales del texto. */
    public function test_seudonimiza_datos_personales(): void
    {
        $texto = 'Contacto: ana.perez@usil.edu.pe, DNI 12345678, cel 987654321';
        $limpio = Seudonimizador::limpiar($texto);

        $this->assertStringNotContainsString('ana.perez@usil.edu.pe', $limpio);
        $this->assertStringNotContainsString('12345678', $limpio);
        $this->assertStringContainsString('[correo]', $limpio);
        $this->assertStringContainsString('[documento]', $limpio);
    }

    /** R-03: sin API ni historial, el fallback por nombre devuelve coincidencias. */
    public function test_fallback_por_nombre_sin_ia(): void
    {
        $service = new SugerenciaIAService();

        $cursoExterno = new CursoExterno(['nombre' => 'Cálculo Diferencial']);

        $cursosUsil = [
            ['id' => 1, 'nombre' => 'Cálculo Diferencial e Integral', 'silabo_texto' => null],
            ['id' => 2, 'nombre' => 'Historia del Arte', 'silabo_texto' => null],
        ];

        // Sin OPENAI_API_KEY ni historial -> usa fallback por nombre.
        $ref = new \ReflectionMethod($service, 'fallbackPorNombre');
        $ref->setAccessible(true);
        $resultado = $ref->invoke($service, $cursoExterno, $cursosUsil);

        $this->assertNotEmpty($resultado);
        $this->assertEquals(1, $resultado[0]['curso_usil_id']); // mejor coincidencia
    }
}
