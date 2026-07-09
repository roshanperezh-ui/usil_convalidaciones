<?php

namespace App\Http\Controllers;

use App\Models\Carrera;
use App\Models\InstitucionExterna;
use App\Models\Postulante;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Gestión de Postulantes (solicitantes de convalidación por traslado externo).
 * Registro mediante asistente de 6 pasos, con guardado de borrador y documentos.
 */
class PostulanteController extends Controller
{
    private const ESTADOS = ['nuevo', 'en_evaluacion', 'admitido', 'rechazado', 'matriculado'];
    private const DOCUMENTOS = ['certificado', 'silabos', 'constancia'];

    public function index(Request $request)
    {
        // Alcance por rol: solo postulantes con un destino dentro de las carreras visibles.
        $visibles = \App\Services\AlcanceService::carrerasVisibles($request->user());

        $postulantes = Postulante::with(['carreraDestino', 'institucionOrigen'])
            ->when($visibles !== null, fn ($x) => $x->whereHas('destinos',
                fn ($d) => $d->whereIn('carrera_id', $visibles ?: [0])))
            ->when($request->q, fn ($x, $v) => $x->where(fn ($w) =>
                $w->where('nombres', 'like', "%{$v}%")
                    ->orWhere('apellido_paterno', 'like', "%{$v}%")
                    ->orWhere('apellido_materno', 'like', "%{$v}%")
                    ->orWhere('numero_documento', 'like', "%{$v}%")
                    ->orWhere('codigo', 'like', "%{$v}%")
                    ->orWhere('email', 'like', "%{$v}%")))
            ->when($request->estado, fn ($x, $v) => $x->where('estado', $v))
            ->when($request->carrera_destino_id, fn ($x, $v) => $x->where('carrera_destino_id', $v))
            ->orderByDesc('id')
            ->paginate(10)->withQueryString()
            ->through(fn (Postulante $p) => [
                'id'              => $p->id,
                'codigo'          => $p->codigo,
                'documento'       => "{$p->tipo_documento} {$p->numero_documento}",
                'nombre'          => $p->nombre_completo,
                'email'           => $p->email,
                'carrera_destino' => $p->carreraDestino?->nombre,
                'procedencia'     => $p->institucionOrigen?->nombre,
                'estado'          => $p->estado,
            ]);

        return inertia('Postulantes/Index', [
            'postulantes' => $postulantes,
            'total'       => Postulante::count(),
            'carreras'    => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'estados'     => self::ESTADOS,
            'filtros'     => $request->only(['q', 'estado', 'carrera_destino_id']),
        ]);
    }

    public function create()
    {
        return inertia('Postulantes/Form', $this->opciones() + ['postulante' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $borrador = $request->boolean('borrador');
        $datos = $this->validar($request, null, $borrador);
        $destinoIds = $this->extraerDestinos($datos);

        $siguiente = (Postulante::withTrashed()->max('id') ?? 0) + 1;

        // Postulante sin documento → identificador temporal único.
        if ($request->boolean('sin_documento')) {
            $datos['tipo_documento']   = 'TEMP';
            $datos['numero_documento'] = 'TMP-' . now()->year . '-' . str_pad((string) $siguiente, 5, '0', STR_PAD_LEFT);
        }

        $datos['codigo']     = 'POST-' . now()->year . '-' . str_pad((string) $siguiente, 5, '0', STR_PAD_LEFT);
        $datos['usuario_id'] = $request->user()->id;
        $datos['estado']     = 'nuevo';

        // Acceso al portal solo si hay correo.
        $temporal = null;
        if (! empty($datos['email'])) {
            $temporal = Str::password(10);
            $datos['password_hash']     = Hash::make($temporal);
            $datos['acceso_habilitado'] = true;
        }

        $postulante = Postulante::create($datos);
        $this->guardarDocumentos($request, $postulante);
        $this->syncDestinos($postulante, $destinoIds);

        AuditoriaService::registrar('crear', 'postulantes', $postulante->id, null, ['documento' => $postulante->numero_documento]);

        $msg = $borrador
            ? "Borrador guardado ({$postulante->codigo})."
            : "Postulante registrado ({$postulante->codigo}).";
        if ($temporal) {
            $url = route('portal.login');
            $msg .= " Acceso al portal ({$url}) → usuario: {$postulante->email} · contraseña temporal: {$temporal}";
        }

        return redirect()->route('postulantes.index')->with('status', $msg);
    }

    public function edit(Postulante $postulante)
    {
        $postulante->load('documentos');

        return inertia('Postulantes/Form', $this->opciones() + [
            'postulante' => $postulante->only([
                'id', 'codigo', 'tipo_documento', 'numero_documento', 'nombres', 'apellido_paterno',
                'apellido_materno', 'fecha_nacimiento', 'genero', 'nacionalidad', 'email', 'telefono',
                'pais_residencia', 'direccion', 'institucion_origen_id', 'carrera_externa_id',
                'carrera_destino_id', 'ciclo_postulacion', 'estado', 'observaciones',
            ]) + [
                'sin_documento'       => $postulante->tipo_documento === 'TEMP',
                'carrera_destino_ids' => $postulante->destinos()->pluck('carrera_id')->all(),
                'documentos'          => $postulante->documentos->map(fn ($d) => ['tipo' => $d->tipo, 'nombre' => $d->nombre_original])->values(),
            ],
        ]);
    }

    public function update(Request $request, Postulante $postulante): RedirectResponse
    {
        $borrador = $request->boolean('borrador');
        $datos = $this->validar($request, $postulante->id, $borrador);
        $destinoIds = $this->extraerDestinos($datos);
        $antes = $postulante->only(['estado', 'carrera_destino_id', 'email']);

        if ($request->boolean('sin_documento') && $postulante->tipo_documento !== 'TEMP') {
            $datos['tipo_documento'] = 'TEMP';
            $datos['numero_documento'] = 'TMP-' . now()->year . '-' . str_pad((string) $postulante->id, 5, '0', STR_PAD_LEFT);
        }

        $postulante->update($datos);
        $this->guardarDocumentos($request, $postulante);
        $this->syncDestinos($postulante, $destinoIds);

        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, $antes, $datos);

        return redirect()->route('postulantes.index')->with('status', 'Postulante actualizado.');
    }

    public function estado(Request $request, Postulante $postulante): RedirectResponse
    {
        $datos = $request->validate(['estado' => ['required', Rule::in(self::ESTADOS)]]);
        $postulante->update($datos);
        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, null, ['estado' => $postulante->estado]);

        return back()->with('status', 'Estado del postulante actualizado.');
    }

    public function resetAcceso(Postulante $postulante): RedirectResponse
    {
        abort_if(empty($postulante->email), 422, 'El postulante no tiene correo para habilitar acceso.');

        $temporal = Str::password(10);
        $postulante->update(['password_hash' => Hash::make($temporal), 'acceso_habilitado' => true]);
        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, null, ['reset_acceso' => true]);

        return back()->with('status', "Acceso restablecido para {$postulante->email}. Contraseña temporal: {$temporal}");
    }

    public function destroy(Postulante $postulante): RedirectResponse
    {
        $postulante->delete();
        AuditoriaService::registrar('eliminar', 'postulantes', $postulante->id);

        return redirect()->route('postulantes.index')->with('status', 'Postulante eliminado.');
    }

    /**
     * Extrae y normaliza los ids de carreras destino del arreglo validado,
     * fijando el primero como destino primario (postulantes.carrera_destino_id).
     */
    private function extraerDestinos(array &$datos): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $datos['carrera_destino_ids'] ?? []))));
        unset($datos['carrera_destino_ids']);
        $datos['carrera_destino_id'] = $ids[0] ?? null;

        return $ids;
    }

    /** Sincroniza la tabla postulante_destinos con las carreras solicitadas. */
    private function syncDestinos(Postulante $postulante, array $ids): void
    {
        $postulante->destinos()->whereNotIn('carrera_id', $ids ?: [0])->delete();

        foreach ($ids as $carreraId) {
            $postulante->destinos()->firstOrCreate(['carrera_id' => $carreraId]);
        }
    }

    private function guardarDocumentos(Request $request, Postulante $postulante): void
    {
        foreach (self::DOCUMENTOS as $tipo) {
            if ($request->hasFile($tipo)) {
                $archivo = $request->file($tipo);
                $ruta = $archivo->store("postulantes/{$postulante->id}");
                $postulante->documentos()->create([
                    'tipo'            => $tipo,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'ruta'            => $ruta,
                    'tamano'          => $archivo->getSize(),
                ]);
            }
        }
    }

    private function opciones(): array
    {
        return [
            'instituciones' => InstitucionExterna::where('activa', true)->orderBy('nombre')->get(['id', 'nombre']),
            'carreras'      => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'estados'       => self::ESTADOS,
        ];
    }

    private function validar(Request $request, ?int $id, bool $borrador): array
    {
        $sinDoc = $request->boolean('sin_documento');

        $rules = [
            'nombres'               => ['required', 'string', 'max:100'],
            'apellido_paterno'      => ['required', 'string', 'max:100'],
            'apellido_materno'      => ['nullable', 'string', 'max:100'],
            'fecha_nacimiento'      => ['nullable', 'date', 'before:today'],
            'genero'                => ['nullable', 'in:masculino,femenino,otro,no_especifica'],
            'nacionalidad'          => ['nullable', 'string', 'max:60'],
            'email'                 => [$borrador ? 'nullable' : 'required', 'email', 'max:150'],
            'telefono'              => ['nullable', 'string', 'max:20'],
            'pais_residencia'       => ['nullable', 'string', 'max:60'],
            'direccion'             => ['nullable', 'string', 'max:200'],
            'institucion_origen_id' => ['nullable', 'exists:instituciones_externas,id'],
            'carrera_externa_id'    => ['nullable', 'exists:carreras_externas,id'],
            'carrera_destino_ids'   => [$borrador ? 'nullable' : 'required', 'array'],
            'carrera_destino_ids.*' => ['integer', 'exists:carreras,id'],
            'ciclo_postulacion'     => [$borrador ? 'nullable' : 'required', 'regex:/^\d{4}-\d$/'],
            'observaciones'         => ['nullable', 'string', 'max:1000'],
            'certificado'           => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'silabos'               => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,zip', 'max:10240'],
            'constancia'            => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        if (! $sinDoc) {
            $rules['tipo_documento']   = ['required', 'in:DNI,CE,PASAPORTE,PTP'];
            $rules['numero_documento'] = ['required', 'string', 'max:20',
                Rule::unique('postulantes', 'numero_documento')
                    ->where(fn ($q) => $q->where('tipo_documento', $request->tipo_documento))
                    ->ignore($id)->whereNull('deleted_at')];
        }

        $datos = $request->validate($rules, [
            'numero_documento.unique' => 'Ya existe un postulante con ese tipo y número de documento.',
            'ciclo_postulacion.regex' => 'El ciclo debe tener el formato AAAA-N (por ejemplo, 2026-1).',
            'carrera_destino_id.required' => 'Selecciona la carrera destino en USIL.',
            'email.required'          => 'El correo es obligatorio para registrar al postulante.',
        ]);

        // Solo columnas persistibles (los archivos se procesan aparte).
        return collect($datos)->except(self::DOCUMENTOS)->all();
    }
}
