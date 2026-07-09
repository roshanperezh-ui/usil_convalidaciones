<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Convalidacion;
use App\Models\Simulacion;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * CU-06: Confirmar Convalidación. RF-30/31/33 y RF-46/47 (anulación).
 */
class ConvalidacionController extends Controller
{
    /** Responsables del memorándum (configurables en Configuración) con sus valores por defecto. */
    public const MEMO_DEFAULTS = [
        'memo_para_nombre'      => 'Erika Valdivieso Lopez',
        'memo_para_cargo'       => 'Vicerrectorado Académico',
        'memo_de_nombre'        => 'Mag. Enrique Zentner Alva',
        'memo_de_cargo'         => 'Director de CPEL - Carreras Universitarias para Personas con Experiencia Laboral',
        'memo_firma_izq_nombre' => 'Mag. Enrique Zentner Alva',
        'memo_firma_izq_cargo'  => 'Director Cpel',
        'memo_firma_der_nombre' => 'Even Deyser Perez Rojas',
        'memo_firma_der_cargo'  => 'Coordinador de la Carrera Cpel',
        'memo_asunto'           => 'Convalidación por Traslado Externo al Programa CPEL',
        'memo_unidad'           => 'CPEL-USIL',
    ];

    /** Devuelve los responsables actuales (valor guardado o el por defecto). */
    public static function responsablesMemo(): array
    {
        $r = [];
        foreach (self::MEMO_DEFAULTS as $clave => $def) {
            $r[$clave] = Configuracion::get($clave, $def);
        }

        return $r;
    }
    public function index(Request $request)
    {
        // Alcance por rol: convalidaciones cuya simulación es de una carrera visible.
        $visibles = \App\Services\AlcanceService::carrerasVisibles($request->user());

        $convalidaciones = Convalidacion::with('simulacion')
            ->when($visibles !== null, fn ($q) => $q->whereHas('simulacion',
                fn ($s) => $s->whereIn('carrera_usil_id', $visibles ?: [0])))
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (Convalidacion $c) => [
                'id'         => $c->id,
                'estudiante' => $c->simulacion ? "{$c->simulacion->nombres} {$c->simulacion->apellidos}" : '—',
                'memorandum' => $c->memorandum_numero,
                'fecha'      => optional($c->fecha_confirmacion)->format('d/m/Y'),
                'estado'     => $c->estado,
            ]);

        return inertia('Convalidaciones/Index', ['convalidaciones' => $convalidaciones]);
    }

    /** RF-30/31: confirmar la convalidación definitiva (1:1 con la simulación). */
    public function confirmar(Request $request, Simulacion $simulacion): RedirectResponse
    {
        if ($simulacion->convalidacion()->exists()) {
            throw ValidationException::withMessages(['simulacion' => 'La simulación ya fue convalidada.']);
        }

        $simulacion->update(['estado' => 'aceptada']);

        $convalidacion = Convalidacion::create([
            'simulacion_id'     => $simulacion->id,
            'fecha_confirmacion'=> now()->toDateString(),
            'memorandum_numero' => 'MEMO-' . now()->format('Y') . '-' . str_pad($simulacion->id, 5, '0', STR_PAD_LEFT),
            'estado'            => Convalidacion::CONFIRMADA,
            'usuario_id'        => $request->user()->id,
        ]);

        // RF-33: generar el memorándum oficial.
        $this->generarMemorandum($convalidacion);

        AuditoriaService::registrar('crear', 'convalidaciones', $convalidacion->id);

        return redirect()->route('convalidaciones.index')->with('status', 'Convalidación confirmada.');
    }

    /** RF-46/47: anular una convalidación confirmada (sin eliminar el registro). */
    public function anular(Request $request, Convalidacion $convalidacion): RedirectResponse
    {
        $request->validate(['motivo' => ['required', 'string', 'max:300']]);

        if ($convalidacion->estado === Convalidacion::ANULADA) {
            return back()->with('status', 'La convalidación ya estaba anulada.');
        }

        $previos = $convalidacion->only(['estado']);

        $convalidacion->update([
            'estado'           => Convalidacion::ANULADA,
            'motivo_anulacion' => $request->motivo,
        ]);

        AuditoriaService::registrar('editar', 'convalidaciones', $convalidacion->id, $previos, [
            'estado' => Convalidacion::ANULADA, 'motivo' => $request->motivo,
        ]);

        return back()->with('status', 'Convalidación anulada.');
    }

    public function memorandumPdf(Convalidacion $convalidacion)
    {
        $contenido = $this->renderMemorandum($convalidacion);

        $nombre = 'Memorandum_' . $convalidacion->memorandum_numero . '.pdf';

        return response($contenido, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
        ]);
    }

    private function generarMemorandum(Convalidacion $convalidacion): void
    {
        $contenido = $this->renderMemorandum($convalidacion);
        $ruta = "convalidaciones/memo_{$convalidacion->id}.pdf";
        Storage::put($ruta, $contenido);

        $convalidacion->update(['memorandum_pdf_path' => $ruta]);
    }

    /** Renderiza el memorándum a bytes PDF (silenciando avisos de PHP 8.5). */
    private function renderMemorandum(Convalidacion $convalidacion): string
    {
        $nivelPrevio = error_reporting();
        error_reporting($nivelPrevio & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

        try {
            return Pdf::loadView('pdf.memorandum', $this->datosMemorandum($convalidacion))->output();
        } finally {
            error_reporting($nivelPrevio);
        }
    }

    /** Prepara los datos del memorándum en formato oficial CPEL-USIL. */
    private function datosMemorandum(Convalidacion $convalidacion): array
    {
        $convalidacion->load([
            'simulacion.detalles' => fn ($q) => $q->where('excluido', false)->whereNotNull('curso_usil_id'),
            'simulacion.detalles.cursoUsil.ciclo', 'simulacion.detalles.cursoExterno',
            'simulacion.carreraUsil.facultad', 'simulacion.carreraExterna',
            'simulacion.postulante.institucionOrigen',
        ]);

        $resp = self::responsablesMemo();
        $sim  = $convalidacion->simulacion;

        $detalles = $sim
            ? $sim->detalles->sortBy(fn ($d) => $d->cursoUsil?->nombre)->values()
            : collect();

        $periodo = $sim?->ciclo_postulacion ?? '';

        // Evita "Facultad de: Facultad de ..." (el blade ya antepone "Facultad de:").
        $facultad = preg_replace('/^facultad\s+de\s+/i', '', $sim?->carreraUsil?->facultad?->nombre ?? 'Ingeniería');

        return [
            'convalidacion' => $convalidacion,
            'facultad'      => $facultad,
            'carrera'       => $sim?->carreraUsil?->nombre,
            'estudiante'    => mb_strtoupper(trim(($sim?->apellidos ?? '') . ', ' . ($sim?->nombres ?? ''))),
            'codigo'        => $sim?->postulante?->codigo ?? $sim?->numero_documento,
            'procedencia'   => $sim?->universidad_origen
                ?? $sim?->postulante?->institucionOrigen?->nombre
                ?? $sim?->carreraExterna?->nombre,
            'periodo'       => $periodo,
            'codigoMemo'    => str_pad((string) $convalidacion->id, 4, '0', STR_PAD_LEFT)
                . ' - ' . $periodo . ' / ' . $resp['memo_unidad'],
            'fecha'         => $this->fechaLarga($convalidacion->fecha_confirmacion),
            'detalles'      => $detalles,
            'total'         => (float) $detalles->sum('creditos_reconocidos'),
            'resp'          => $resp,
        ];
    }

    /** Fecha en formato "12 de Marzo 2026". */
    private function fechaLarga($fecha): string
    {
        if (! $fecha) {
            return '';
        }
        $f = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
        $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return $f->day . ' de ' . $meses[$f->month] . ' ' . $f->year;
    }
}
