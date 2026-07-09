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
use App\Models\PostulanteDestino;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

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
    /** Documentos requeridos para considerar el expediente completo. */
    private const DOCUMENTOS = ['certificado', 'silabos', 'constancia'];

    public function index(Request $request)
    {
        // Alcance por rol: el Coordinador/Director ve solo sus carreras; el Decano su facultad.
        $visibles = \App\Services\AlcanceService::carrerasVisibles($request->user());

        $base = PostulanteDestino::query()->whereHas('postulante')
            ->when($visibles !== null, fn ($q) => $q->whereIn('carrera_id', $visibles ?: [0]));

        $conteos = (clone $base)
            ->selectRaw('estado_equivalencias, COUNT(*) as total')
            ->groupBy('estado_equivalencias')
            ->pluck('total', 'estado_equivalencias');

        $total      = (int) $conteos->sum();
        $pendientes = (int) ($conteos['pendiente'] ?? 0);
        $enRevision = (int) ($conteos['en_revision'] ?? 0);
        $aprobadas  = (int) ($conteos['aprobada'] ?? 0);

        $destinos = (clone $base)
            ->with([
                'carrera:id,nombre,facultad_id',
                'carrera.facultad:id,nombre',
                'postulante:id,nombres,apellido_paterno,apellido_materno,tipo_documento,numero_documento,institucion_origen_id,carrera_externa_id',
                'postulante.institucionOrigen:id,nombre',
                'postulante.carreraExterna:id,nombre',
                'asignadoA:id,nombre',
            ])
            ->when($request->q, fn ($x, $v) => $x->whereHas('postulante', fn ($w) =>
                $w->where('nombres', 'like', "%{$v}%")
                    ->orWhere('apellido_paterno', 'like', "%{$v}%")
                    ->orWhere('apellido_materno', 'like', "%{$v}%")
                    ->orWhere('numero_documento', 'like', "%{$v}%")))
            ->when($request->estado, fn ($x, $v) => $x->where('estado_equivalencias', $v))
            ->when($request->institucion_id, fn ($x, $v) => $x->whereHas('postulante',
                fn ($w) => $w->where('institucion_origen_id', $v)))
            ->orderByRaw("FIELD(estado_equivalencias, 'pendiente', 'en_revision', 'aprobada')")
            ->orderByDesc('id')
            ->paginate(12)->withQueryString()
            ->through(function (PostulanteDestino $d) {
                $p = $d->postulante;
                $docs = $p ? $p->documentos()->count() : 0;

                return [
                    'id'              => $d->id,
                    'postulante'      => $p ? trim("{$p->nombres} {$p->apellido_paterno} {$p->apellido_materno}") : '—',
                    'documento'       => $p ? "{$p->tipo_documento}: {$p->numero_documento}" : '—',
                    'institucion'     => $p?->institucionOrigen?->nombre ?? '—',
                    'sigla'           => $this->sigla($p?->institucionOrigen?->nombre),
                    'carrera_origen'  => $p?->carreraExterna?->nombre ?? '—',
                    'carrera_destino' => $d->carrera?->nombre ?? '—',
                    'facultad'        => $d->carrera?->facultad?->nombre,
                    'docs_completos'  => $docs >= count(self::DOCUMENTOS),
                    'estado'          => $d->estado_equivalencias,
                    'asignado'        => $d->asignadoA?->nombre,
                    'observacion'     => $d->observacion_flujo,
                ];
            });

        return inertia('Equivalencias/Index', [
            'postulantes' => $destinos,
            'kpis'        => [
                'total'      => $total,
                'pendientes' => $pendientes,
                'en_revision' => $enRevision,
                'aprobadas'  => $aprobadas,
                'tasa_aprob' => $total ? round($aprobadas / $total * 100) : 0,
            ],
            'instituciones' => InstitucionExterna::whereIn('id',
                PostulanteDestino::query()->whereHas('postulante')
                    ->join('postulantes', 'postulantes.id', '=', 'postulante_destinos.postulante_id')
                    ->whereNotNull('postulantes.institucion_origen_id')
                    ->distinct()->pluck('postulantes.institucion_origen_id')
            )->orderBy('nombre')->get(['id', 'nombre']),
            // Coordinadores/Directores a los que se puede asignar una evaluación.
            'coordinadores' => \App\Models\User::where('activo', true)
                ->whereHas('rol', fn ($q) => $q->whereIn('nombre', [\App\Models\Role::COORDINADOR, \App\Models\Role::DIRECTOR]))
                ->orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => $request->only(['q', 'estado', 'institucion_id']),
        ]);
    }

    /** Atender un destino: pasa a 'en_revision' y abre el emparejamiento. */
    public function atender(Request $request, PostulanteDestino $destino): RedirectResponse
    {
        if ($destino->estado_equivalencias === 'pendiente') {
            $destino->update([
                'estado_equivalencias'       => 'en_revision',
                'equivalencias_revisado_por' => $request->user()->id,
                'equivalencias_revisado_en'  => now(),
            ]);
            AuditoriaService::registrar('editar', 'postulante_destinos', $destino->id, null, ['estado_equivalencias' => 'en_revision']);
        }

        return redirect()->route('equivalencias.create', ['destino' => $destino->id]);
    }

    /** Aprobar el emparejamiento de un destino (Director de Escuela — RF-22). */
    public function aprobar(Request $request, PostulanteDestino $destino): RedirectResponse
    {
        abort_unless($request->user()->puede('evaluacion.aprobar'), 403, 'No tiene permiso para aprobar evaluaciones.');

        $destino->update([
            'estado_equivalencias'       => 'aprobada',
            'observacion_flujo'          => null,
            'equivalencias_revisado_por' => $request->user()->id,
            'equivalencias_revisado_en'  => now(),
        ]);
        AuditoriaService::registrar('editar', 'postulante_destinos', $destino->id, null, ['estado_equivalencias' => 'aprobada']);

        return redirect()->route('equivalencias.index')->with('status', 'Evaluación aprobada.');
    }

    /** Servicios Académicos: asigna el destino a un coordinador (pendiente → asignada). */
    public function asignar(Request $request, PostulanteDestino $destino): RedirectResponse
    {
        abort_unless($request->user()->puede('solicitudes.asignar'), 403, 'No tiene permiso para asignar solicitudes.');
        $datos = $request->validate(['asignado_a_id' => ['required', 'exists:usuarios,id']]);

        $destino->update([
            'asignado_a_id'        => $datos['asignado_a_id'],
            'estado_equivalencias' => in_array($destino->estado_equivalencias, ['pendiente', 'devuelta'], true)
                ? 'asignada' : $destino->estado_equivalencias,
        ]);
        AuditoriaService::registrar('editar', 'postulante_destinos', $destino->id, null, ['asignado_a_id' => $datos['asignado_a_id']]);

        return back()->with('status', 'Solicitud asignada al coordinador.');
    }

    /** Director de Escuela: reasigna la evaluación a otro coordinador. */
    public function reasignar(Request $request, PostulanteDestino $destino): RedirectResponse
    {
        abort_unless($request->user()->puede('evaluacion.reasignar'), 403, 'No tiene permiso para reasignar.');
        $datos = $request->validate(['asignado_a_id' => ['required', 'exists:usuarios,id']]);

        $destino->update(['asignado_a_id' => $datos['asignado_a_id']]);
        AuditoriaService::registrar('editar', 'postulante_destinos', $destino->id, null, ['reasignado_a' => $datos['asignado_a_id']]);

        return back()->with('status', 'Evaluación reasignada.');
    }

    /** Director de Escuela: observa/devuelve la evaluación para corrección. */
    public function observar(Request $request, PostulanteDestino $destino): RedirectResponse
    {
        abort_unless($request->user()->puede('evaluacion.observar'), 403, 'No tiene permiso para observar evaluaciones.');
        $datos = $request->validate([
            'motivo'   => ['required', 'string', 'max:300'],
            'devolver' => ['boolean'],
        ]);

        $destino->update([
            'estado_equivalencias' => $request->boolean('devolver') ? 'devuelta' : 'observada',
            'observacion_flujo'    => $datos['motivo'],
        ]);
        AuditoriaService::registrar('editar', 'postulante_destinos', $destino->id, null, [
            'estado_equivalencias' => $destino->estado_equivalencias, 'motivo' => $datos['motivo'],
        ]);

        return back()->with('status', $request->boolean('devolver') ? 'Evaluación devuelta para corrección.' : 'Evaluación observada.');
    }

    public function create(Request $request)
    {
        // Prefill desde un destino (Bandeja → Atender).
        $destino = null;
        $postulante = null;
        if ($request->destino) {
            $destino = PostulanteDestino::with([
                'carrera:id,nombre',
                'postulante.institucionOrigen:id,nombre',
                'postulante.carreraExterna:id,nombre',
                'postulante.carreraDestino:id,nombre',
            ])->find($request->destino);

            if ($destino) {
                $postulante = $destino->postulante;
                $request->merge([
                    'carrera_usil_id'    => $request->carrera_usil_id ?? $destino->carrera_id,
                    'carrera_externa_id' => $request->carrera_externa_id ?? $postulante?->carrera_externa_id,
                    'malla_id'           => $request->malla_id ?? MallaCurricular::where('carrera_id', $destino->carrera_id)
                        ->where('activa', true)->orderByDesc('anio')->value('id'),
                ]);
            }
        }

        // RF-20: al fijar carrera USIL+malla y carrera externa, se cargan los cursos.
        $cursosUsil = collect();
        $cursosExternos = collect();
        $previas = collect();

        if ($request->carrera_usil_id && $request->malla_id) {
            $cursosUsil = CursoUsil::whereHas('ciclo', fn ($q) => $q->where('malla_id', $request->malla_id))
                ->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'creditos']);
        }

        if ($request->carrera_externa_id) {
            $cursosExternos = CursoExterno::where('carrera_externa_id', $request->carrera_externa_id)
                ->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'creditos']);

            // RF-22: detectar equivalencias previas para precargar.
            $previas = Equivalencia::where('carrera_externa_id', $request->carrera_externa_id)
                ->when($request->carrera_usil_id, fn ($q, $v) => $q->where('carrera_usil_id', $v))
                ->get(['curso_externo_id', 'curso_usil_id', 'tipo_equivalencia']);
        }

        return inertia('Equivalencias/Form', [
            'carreras'      => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'instituciones' => InstitucionExterna::where('activa', true)->orderBy('nombre')->get(['id', 'nombre', 'pais']),
            'mallas'        => MallaCurricular::orderByDesc('anio')->get(['id', 'carrera_id', 'anio', 'version', 'activa']),
            'cursosUsil'     => $cursosUsil,
            'cursosExternos' => $cursosExternos,
            'previas'        => $previas,
            'postulante'     => $destino && $postulante ? [
                'destino_id'         => $destino->id,
                'id'                 => $postulante->id,
                'nombre'             => trim("{$postulante->nombres} {$postulante->apellido_paterno} {$postulante->apellido_materno}"),
                'documento'          => $postulante->numero_documento,
                'tipo_documento'     => $postulante->tipo_documento,
                'institucion'        => $postulante->institucionOrigen?->nombre,
                'institucion_pais'   => $postulante->institucionOrigen?->pais,
                'carrera_origen'     => $postulante->carreraExterna?->nombre,
                'carrera_externa_id' => $postulante->carrera_externa_id,
                'carrera_destino'    => $destino->carrera?->nombre,
                'carrera_destino_id' => $destino->carrera_id,
                'estado'             => $destino->estado_equivalencias,
            ] : null,
            'destinosSelector' => PostulanteDestino::with(['postulante:id,nombres,apellido_paterno,apellido_materno,numero_documento', 'carrera:id,nombre'])
                ->whereHas('postulante')->orderByDesc('id')->limit(300)->get()
                ->map(fn (PostulanteDestino $d) => [
                    'id'    => $d->id,
                    'label' => trim("{$d->postulante->apellido_paterno} {$d->postulante->apellido_materno}, {$d->postulante->nombres}")
                        . " · {$d->postulante->numero_documento} → {$d->carrera?->nombre}",
                ]),
            'seleccion'      => array_merge($request->only(['carrera_usil_id', 'carrera_externa_id', 'malla_id']), [
                'institucion_id' => $request->carrera_externa_id
                    ? CarreraExterna::where('id', $request->carrera_externa_id)->value('institucion_id')
                    : null,
            ]),
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
        $equivalencia->delete(); // RF-23: soft delete

        AuditoriaService::registrar('eliminar', 'equivalencias', $equivalencia->id);

        return back()->with('status', 'Equivalencia eliminada.');
    }

    /** Sigla corta para mostrar bajo el nombre de la institución. */
    private function sigla(?string $nombre): string
    {
        if (! $nombre) {
            return '—';
        }
        $palabras = preg_split('/\s+/', preg_replace('/[^\p{L}\s]/u', ' ', $nombre), -1, PREG_SPLIT_NO_EMPTY);
        $stop = ['de', 'del', 'la', 'el', 'los', 'las', 'y', 'e'];
        $sig = '';
        foreach ($palabras as $p) {
            if (! in_array(mb_strtolower($p), $stop, true)) {
                $sig .= mb_strtoupper(mb_substr($p, 0, 1));
            }
        }
        if (mb_strlen($sig) <= 1) {
            $sig = mb_strtoupper(mb_substr($palabras[0] ?? $nombre, 0, 3));
        }

        return mb_substr($sig, 0, 4);
    }
}
