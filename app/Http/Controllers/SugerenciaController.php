<?php

namespace App\Http\Controllers;

use App\Models\CursoExterno;
use App\Models\CursoUsil;
use App\Models\Equivalencia;
use App\Services\AuditoriaService;
use App\Services\SugerenciaIAService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * CU-11: Solicitar Sugerencias de IA. CU-12: Gestionar (aceptar/rechazar/modificar).
 * RF-43..45. La IA nunca autoconfirma: la decisión es del coordinador.
 */
class SugerenciaController extends Controller
{
    public function __construct(private SugerenciaIAService $ia) {}

    /** CU-11 / RF-43/44: devuelve sugerencias con nivel de confianza. */
    public function sugerir(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'curso_externo_id' => ['required', 'exists:cursos_externos,id'],
            'carrera_usil_id'  => ['required', 'exists:carreras,id'],
            'malla_id'         => ['required', 'exists:mallas_curriculares,id'],
        ]);

        $cursoExterno = CursoExterno::findOrFail($datos['curso_externo_id']);

        $cursosUsil = CursoUsil::whereHas('ciclo', fn ($q) => $q->where('malla_id', $datos['malla_id']))
            ->get(['id', 'nombre', 'silabo_texto'])
            ->toArray();

        $sugerencias = $this->ia->sugerir($cursoExterno, $datos['carrera_usil_id'], $cursosUsil);

        AuditoriaService::registrar('crear', 'equivalencias', null, null, [
            'accion' => 'sugerencia_ia', 'curso_externo' => $cursoExterno->id, 'n' => count($sugerencias),
        ]);

        return response()->json(['sugerencias' => $sugerencias]);
    }

    /** CU-12 / RF-45: aceptar una sugerencia crea la equivalencia (origen 'ia'). */
    public function aceptar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'carrera_externa_id' => ['required', 'exists:carreras_externas,id'],
            'carrera_usil_id'    => ['required', 'exists:carreras,id'],
            'curso_externo_id'   => ['required', 'exists:cursos_externos,id'],
            'curso_usil_id'      => ['required', 'exists:cursos_usil,id'],
            'confianza'          => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $existe = Equivalencia::where([
            'carrera_externa_id' => $datos['carrera_externa_id'],
            'carrera_usil_id'    => $datos['carrera_usil_id'],
            'curso_externo_id'   => $datos['curso_externo_id'],
            'curso_usil_id'      => $datos['curso_usil_id'],
        ])->exists();

        if (! $existe) {
            $equ = Equivalencia::create(array_merge($datos, [
                'tipo_equivalencia' => 'completa',
                'origen'            => 'ia',
                'usuario_id'        => $request->user()->id,
            ]));

            AuditoriaService::registrar('crear', 'equivalencias', $equ->id, null, ['origen' => 'ia']);
        }

        return back()->with('status', 'Sugerencia aceptada y registrada como equivalencia.');
    }
}
