<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ simulacion: Object, detalles: Array, creditos_total: Number });

const toggle = (id) => router.patch(`/simulaciones/${props.simulacion.id}/detalle/${id}`, {}, { preserveScroll: true });

// Descarga robusta: enlace temporal en la misma pestaña (evita bloqueo de pop-ups y pestañas en blanco).
const descargarArchivo = (url) => {
    const a = document.createElement('a');
    a.href = url;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
};
const descargarPdf = () => descargarArchivo(`/simulaciones/${props.simulacion.id}/pdf`);
const descargarExcel = () => descargarArchivo(`/simulaciones/${props.simulacion.id}/excel`);
const confirmar = () => {
    if (confirm('¿Confirmar la convalidación? Esta acción genera el memorándum oficial.'))
        router.post(`/simulaciones/${props.simulacion.id}/confirmar`);
};

// En el detalle solo se muestran los cursos a convalidar (con equivalencia USIL).
const filasConvalidadas = computed(() => props.detalles.filter((d) => d.curso_usil));
const convalidados = computed(() => props.detalles.filter((d) => d.curso_usil && !d.excluido).length);
</script>

<template>
    <div class="max-w-5xl">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">{{ simulacion.estudiante }}</h1>
                <p class="text-sm text-slate-500">
                    {{ simulacion.documento }} · Destino: {{ simulacion.carrera }}
                    <span v-if="simulacion.origen"> · Origen: {{ simulacion.origen }}</span>
                </p>
                <p v-if="simulacion.documento_fuente" class="mt-0.5 text-xs text-slate-400">
                    📄 Extraído del documento: {{ simulacion.documento_fuente }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="rounded-full px-3 py-1 text-xs font-medium capitalize"
                      :class="simulacion.metodo === 'ia' ? 'bg-violet-100 text-violet-700' : 'bg-slate-100 text-slate-600'">
                    {{ simulacion.metodo === 'ia' ? 'Con IA' : 'Manual' }}
                </span>
                <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium capitalize text-blue-700">{{ simulacion.estado }}</span>
            </div>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-[#1F3864]">{{ detalles.length }}</p>
                <p class="text-xs text-slate-500">Cursos evaluados</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-green-600">{{ convalidados }}</p>
                <p class="text-xs text-slate-500">Convalidados</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-[#2E75B6]">{{ Number(creditos_total).toFixed(1) }}</p>
                <p class="text-xs text-slate-500">Créditos reconocidos</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-2xl font-bold text-slate-700">{{ simulacion.carrera }}</p>
                <p class="text-xs text-slate-500">Carrera destino</p>
            </div>
        </div>

        <div class="mb-2 flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-400">Cursos a convalidar</h2>
            <Link :href="`/simulaciones/${simulacion.id}/editar`"
                  class="inline-flex items-center gap-1 rounded-md border border-[#2E75B6] px-3 py-1.5 text-xs font-medium text-[#2E75B6] hover:bg-blue-50">
                ✎ Editar mapeo
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Curso de origen</th>
                        <th class="px-4 py-3 font-semibold">Nota</th>
                        <th class="px-4 py-3 font-semibold">Convalida con (USIL)</th>
                        <th class="px-4 py-3 text-right font-semibold">Créditos</th>
                        <th class="px-4 py-3 text-center font-semibold">Incluir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="d in filasConvalidadas" :key="d.id" :class="d.excluido ? 'opacity-40' : ''" class="hover:bg-slate-50/70">
                        <td class="px-4 py-2 text-slate-700">{{ d.curso_externo }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ d.nota || '—' }}</td>
                        <td class="px-4 py-2 font-medium text-slate-800">
                            {{ d.curso_usil }}
                            <span v-if="d.confianza" class="ml-1 text-xs text-slate-400">({{ Number(d.confianza).toFixed(0) }}%)</span>
                        </td>
                        <td class="px-4 py-2 text-right text-slate-600">{{ Number(d.creditos).toFixed(1) }}</td>
                        <td class="px-4 py-2 text-center">
                            <input type="checkbox" :checked="!d.excluido" @change="toggle(d.id)"
                                   class="rounded border-slate-300 text-[#2E75B6]" />
                        </td>
                    </tr>
                    <tr v-if="!filasConvalidadas.length">
                        <td colspan="5" class="px-4 py-6 text-center text-slate-400">
                            No hay cursos convalidados. Usa «Editar mapeo» para asignar cursos USIL.
                        </td>
                    </tr>
                </tbody>
                <tfoot v-if="filasConvalidadas.length">
                    <tr class="bg-slate-50">
                        <td colspan="3" class="px-4 py-2 text-right font-medium text-slate-600">Créditos reconocidos</td>
                        <td class="px-4 py-2 text-right font-semibold text-[#1F3864]">{{ Number(creditos_total).toFixed(1) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button @click="descargarPdf" class="inline-flex items-center gap-2 rounded-md border border-[#2E75B6] px-4 py-2 text-sm font-medium text-[#2E75B6] hover:bg-blue-50">
                ⬇️ Descargar preconvalidación (PDF)
            </button>
            <button @click="descargarExcel" class="inline-flex items-center gap-2 rounded-md border border-green-600 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-50">
                ⬇️ Descargar preconvalidación (Excel)
            </button>
            <button @click="confirmar" class="rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E75B6]">
                Confirmar convalidación
            </button>
            <Link href="/simulaciones" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Volver</Link>
        </div>
    </div>
</template>
