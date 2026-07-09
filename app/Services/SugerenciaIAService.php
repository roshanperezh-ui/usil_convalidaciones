<?php

namespace App\Services;

use App\Models\CursoExterno;
use App\Models\CursoUsil;
use App\Models\Equivalencia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asistente de IA para sugerir equivalencias (RF-43..45).
 * Estrategia: (a) historial de la misma combinación; (b) similitud semántica vía LLM.
 * Aísla el proveedor externo (anti-corruption layer) y aplica fallback (R-03).
 */
class SugerenciaIAService
{
    private const UMBRAL = 60; // RF-44: confianza mínima para mostrar

    /**
     * Devuelve sugerencias de cursos USIL para un curso externo dado.
     * @return array<int,array{curso_usil_id:int,nombre:string,confianza:float,justificacion:string,origen:string}>
     */
    public function sugerir(CursoExterno $cursoExterno, int $carreraUsilId, array $cursosUsil): array
    {
        // (a) Historial: equivalencias previas del mismo curso externo.
        $historial = $this->porHistorial($cursoExterno, $carreraUsilId);
        if (! empty($historial)) {
            return $historial;
        }

        // (b) Similitud semántica vía IA, con fallback ante error/timeout.
        try {
            return $this->porSimilitudIA($cursoExterno, $cursosUsil);
        } catch (\Throwable $e) {
            Log::warning('IA no disponible, usando fallback', ['error' => $e->getMessage()]);
            return $this->fallbackPorNombre($cursoExterno, $cursosUsil);
        }
    }

    private function porHistorial(CursoExterno $cursoExterno, int $carreraUsilId): array
    {
        $previas = Equivalencia::with('cursoUsil')
            ->where('curso_externo_id', $cursoExterno->id)
            ->where('carrera_usil_id', $carreraUsilId)
            ->get()
            ->groupBy('curso_usil_id');

        return $previas->map(function ($grupo) {
            $n = $grupo->count();
            return [
                'curso_usil_id' => $grupo->first()->curso_usil_id,
                'nombre'        => $grupo->first()->cursoUsil?->nombre,
                'confianza'     => min(100, 60 + $n * 10),
                'justificacion' => "Basado en {$n} convalidación(es) previa(s).",
                'origen'        => 'historial',
            ];
        })->values()->all();
    }

    private function porSimilitudIA(CursoExterno $cursoExterno, array $cursosUsil): array
    {
        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            throw new \RuntimeException('OPENAI_API_KEY no configurada.');
        }

        // RNF-09: solo se envía contenido académico seudonimizado.
        $silaboExterno = Seudonimizador::limpiar($cursoExterno->silabo_texto ?: $cursoExterno->nombre);
        $candidatos = collect($cursosUsil)->map(fn ($c) => [
            'id'     => $c['id'],
            'nombre' => $c['nombre'],
            'silabo' => Seudonimizador::limpiar($c['silabo_texto'] ?? $c['nombre']),
        ])->values();

        $prompt = "Curso externo: {$silaboExterno}\n\nCandidatos USIL (JSON): "
            . $candidatos->toJson()
            . "\n\nDevuelve SOLO un arreglo JSON con objetos {curso_usil_id, confianza (0-100), justificacion}. "
            . "Ordena por confianza descendente.";

        $resp = Http::withToken($apiKey)
            ->timeout(20)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'    => config('services.openai.model', 'gpt-4o'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un asistente que evalúa equivalencias de cursos universitarios. Responde solo JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
            ]);

        $resp->throw();

        $contenido = $resp->json('choices.0.message.content', '[]');
        $sugerencias = json_decode(trim(preg_replace('/```json|```/', '', $contenido)), true) ?: [];

        $mapaNombres = collect($cursosUsil)->keyBy('id');

        return collect($sugerencias)
            ->filter(fn ($s) => ($s['confianza'] ?? 0) >= self::UMBRAL)
            ->map(fn ($s) => [
                'curso_usil_id' => $s['curso_usil_id'],
                'nombre'        => $mapaNombres[$s['curso_usil_id']]['nombre'] ?? '—',
                'confianza'     => (float) $s['confianza'],
                'justificacion' => $s['justificacion'] ?? 'Similitud semántica de sílabos.',
                'origen'        => 'ia',
            ])->values()->all();
    }

    /** Fallback sin IA: coincidencia simple por nombre (R-03). */
    private function fallbackPorNombre(CursoExterno $cursoExterno, array $cursosUsil): array
    {
        $objetivo = mb_strtolower($cursoExterno->nombre);

        return collect($cursosUsil)
            ->map(function ($c) use ($objetivo) {
                similar_text($objetivo, mb_strtolower($c['nombre']), $pct);
                return ['curso_usil_id' => $c['id'], 'nombre' => $c['nombre'],
                        'confianza' => round($pct, 1), 'justificacion' => 'Coincidencia aproximada de nombre (sin IA).',
                        'origen' => 'fallback'];
            })
            ->filter(fn ($s) => $s['confianza'] >= self::UMBRAL)
            ->sortByDesc('confianza')->values()->all();
    }
}
