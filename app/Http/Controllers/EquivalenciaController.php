<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquivalenciaRequest;
use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\CursoExterno;
use App\Models\CursoUsil;
use App\Models\Equivalencia;
use App\Models\InstitucionExterna;
use App\Models\MallaCurricular;
use App\Models\MallaExterna;
use App\Models\PostulanteDestino;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

/**
 * CU-03: Gestionar Equivalencias (RF-18..23).
 *
 * La pantalla tiene dos vistas:
 *  - Bandeja de Atención  (index)  : cola de DESTINOS (postulante × carrera USIL).
 *  - Emparejamiento de Cursos (create/store): cruce curso externo ↔ curso USIL.
 *
 * Un postulante puede solicitar una o más carreras destino; cada una
 * (postulante_destinos) se atiende y aprueba de forma independiente.
 */
class EquivalenciaController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');

        $mallas = MallaExterna::with([
            'carreraExterna:id,institucion_id,nombre',
            'carreraExterna.institucion:id,nombre',
            'cursos:id,malla_externa_id'
        ])
        ->when($q, function ($query) use ($q) {
            $query->whereHas('carreraExterna', function ($qce) use ($q) {
                $qce->where('nombre', 'like', "%{$q}%")
                    ->orWhereHas('institucion', fn ($qi) => $qi->where('nombre', 'like', "%{$q}%"));
            });
        })
        ->orderByDesc('id')
        ->paginate(15)
        ->withQueryString()
        ->through(fn (MallaExterna $m) => [
            'id'               => $m->id,
            'institucion'      => $m->carreraExterna?->institucion?->nombre ?? '—',
            'carrera'          => $m->carreraExterna?->nombre ?? '—',
            'anio'             => $m->anio,
            'version'          => $m->version,
            'activa'           => $m->activa,
            'pdf_path'         => $m->pdf_path ? Storage::url($m->pdf_path) : null,
            'total_cursos'     => $m->cursos->count(),
            'cursos_mapeados'  => Equivalencia::whereIn('curso_externo_id', $m->cursos->pluck('id'))->distinct('curso_externo_id')->count('curso_externo_id'),
        ]);

        return inertia('Equivalencias/Index', [
            'mallas'  => $mallas,
            'filtros' => ['q' => $q],
            'kpis'    => [
                'total_mallas' => MallaExterna::count(),
                'activas'      => MallaExterna::where('activa', true)->count(),
                'total_cursos' => CursoExterno::count(),
            ]
        ]);
    }

    public function create(Request $request)
    {
        $malla = null;
        if ($request->malla_id) {
            $malla = MallaExterna::with([
                'carreraExterna:id,nombre,institucion_id',
                'carreraExterna.institucion:id,nombre',
                'cursos'
            ])->findOrFail($request->malla_id);
        }

        $cursosUsil = collect();
        $previas = collect();

        // Si se selecciona la malla destino de USIL
        if ($request->carrera_usil_id && $request->malla_usil_id) {
            $cursosUsil = CursoUsil::whereHas('ciclo', fn ($q) => $q->where('malla_id', $request->malla_usil_id))
                ->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'creditos']);

            if ($malla) {
                // Equivalencias previas entre los cursos de esta malla externa y la carrera USIL seleccionada
                $previas = Equivalencia::whereIn('curso_externo_id', $malla->cursos->pluck('id'))
                    ->where('carrera_usil_id', $request->carrera_usil_id)
                    ->get(['id', 'curso_externo_id', 'curso_usil_id', 'tipo_equivalencia', 'origen']);
            }
        }

        return inertia('Equivalencias/Form', [
            'carreras'      => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'mallasUsil'    => MallaCurricular::orderByDesc('anio')->get(['id', 'carrera_id', 'anio', 'version', 'activa']),
            'malla'         => $malla ? [
                'id'          => $malla->id,
                'institucion' => $malla->carreraExterna?->institucion?->nombre,
                'carrera'     => $malla->carreraExterna?->nombre,
                'anio'        => $malla->anio,
                'version'     => $malla->version,
                'pdf_url'     => $malla->pdf_path ? Storage::url($malla->pdf_path) : null,
                'cursos'      => $malla->cursos,
            ] : null,
            'cursosUsil'     => $cursosUsil,
            'previas'        => $previas,
            'seleccion'      => $request->only(['carrera_usil_id', 'malla_usil_id']),
            'instituciones'  => InstitucionExterna::with('carreras:id,institucion_id,nombre')->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreEquivalenciaRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        if (Equivalencia::where($datos)->exists()) {
            return back()->with('status', 'La equivalencia ya estaba registrada.');
        }

        $equ = Equivalencia::create(array_merge($datos, [
            'origen'     => 'manual',
            'usuario_id' => $request->user()->id,
        ]));

        AuditoriaService::registrar('crear', 'equivalencias', $equ->id, null, $datos);

        return back()->with('status', 'Equivalencia registrada.');
    }

    public function destroy(Equivalencia $equivalencia): RedirectResponse
    {
        $equivalencia->delete();

        AuditoriaService::registrar('eliminar', 'equivalencias', $equivalencia->id);

        return back()->with('status', 'Equivalencia eliminada.');
    }
}
