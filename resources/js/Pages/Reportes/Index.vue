<script setup>
import { router } from '@inertiajs/vue3';
import { reactive, computed, ref } from 'vue';
const props = defineProps({ resumen: Array, convalidados: Array, noConvalidados: Array, facultades: Array, carreras: Array, filtros: Object });

const tab = ref('convalidados');   // 'convalidados' | 'no'

const filtro = reactive({
    facultad_id: props.filtros?.facultad_id ?? '',
    carrera_id: props.filtros?.carrera_id ?? '',
    desde: props.filtros?.desde ?? '',
    hasta: props.filtros?.hasta ?? '',
});
const carrerasFiltradas = computed(() =>
    filtro.facultad_id ? props.carreras.filter((c) => c.facultad_id == filtro.facultad_id) : props.carreras
);
const aplicar = () => router.get('/reportes', filtro, { preserveState: true, replace: true });
const exportar = () => {
    const qs = new URLSearchParams(Object.entries(filtro).filter(([, v]) => v)).toString();
    window.open(`/reportes/exportar?${qs}`, '_blank');
};
const totalGeneral = computed(() => props.resumen.reduce((a, r) => a + r.total, 0));
</script>

<template>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-[#1F3864]">Reportes de convalidaciones</h1>
            <button @click="exportar" class="rounded-md bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800">
                Exportar a Excel
            </button>
        </div>

        <div class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Facultad</label>
                <select v-model="filtro.facultad_id" class="rounded-md border-slate-300 text-sm">
                    <option value="">Todas</option>
                    <option v-for="f in facultades" :key="f.id" :value="f.id">{{ f.nombre }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Carrera</label>
                <select v-model="filtro.carrera_id" class="rounded-md border-slate-300 text-sm">
                    <option value="">Todas</option>
                    <option v-for="c in carrerasFiltradas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Desde</label>
                <input v-model="filtro.desde" type="date" class="rounded-md border-slate-300 text-sm" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Hasta</label>
                <input v-model="filtro.hasta" type="date" class="rounded-md border-slate-300 text-sm" />
            </div>
            <button @click="aplicar" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Aplicar</button>
        </div>

        <!-- Pestañas: cursos convalidados / no convalidados -->
        <div class="mb-3 inline-flex rounded-lg border border-slate-200 bg-white p-1 shadow-sm">
            <button @click="tab = 'convalidados'"
                    :class="tab === 'convalidados' ? 'bg-[#1F3864] text-white' : 'text-slate-600 hover:bg-slate-50'"
                    class="rounded-md px-4 py-1.5 text-sm font-medium">
                Cursos convalidados <span class="ml-1 opacity-70">({{ convalidados.length }})</span>
            </button>
            <button @click="tab = 'no'"
                    :class="tab === 'no' ? 'bg-amber-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                    class="rounded-md px-4 py-1.5 text-sm font-medium">
                Cursos no convalidados <span class="ml-1 opacity-70">({{ noConvalidados.length }})</span>
            </button>
        </div>

        <!-- Cursos convalidados -->
        <div v-show="tab === 'convalidados'" class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Estudiante</th>
                            <th class="px-4 py-2.5 font-semibold">Documento</th>
                            <th class="px-4 py-2.5 font-semibold">Carrera USIL</th>
                            <th class="px-4 py-2.5 font-semibold">Curso convalidado (origen)</th>
                            <th class="px-4 py-2.5 font-semibold">Curso USIL</th>
                            <th class="px-4 py-2.5 text-right font-semibold">Créditos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(r, i) in convalidados" :key="i" class="hover:bg-slate-50/70">
                            <td class="px-4 py-2 font-medium text-slate-800">{{ r.estudiante }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ r.documento }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ r.carrera }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ r.curso_origen }}</td>
                            <td class="px-4 py-2 font-medium text-green-700">{{ r.curso_usil }}</td>
                            <td class="px-4 py-2 text-right text-slate-600">{{ Number(r.creditos).toFixed(1) }}</td>
                        </tr>
                        <tr v-if="!convalidados.length"><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin cursos convalidados para los filtros aplicados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cursos no convalidados -->
        <div v-show="tab === 'no'" class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">Estudiante</th>
                            <th class="px-4 py-2.5 font-semibold">Documento</th>
                            <th class="px-4 py-2.5 font-semibold">Carrera USIL</th>
                            <th class="px-4 py-2.5 font-semibold">Curso de origen</th>
                            <th class="px-4 py-2.5 font-semibold">Nota</th>
                            <th class="px-4 py-2.5 font-semibold">Motivo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(r, i) in noConvalidados" :key="i" class="hover:bg-slate-50/70">
                            <td class="px-4 py-2 font-medium text-slate-800">{{ r.estudiante }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ r.documento }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ r.carrera }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ r.curso_origen }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ r.nota || '—' }}</td>
                            <td class="px-4 py-2">
                                <span :class="r.motivo === 'Desaprobado' ? 'bg-red-50 text-red-700 ring-red-200' : 'bg-amber-50 text-amber-700 ring-amber-200'"
                                      class="inline-block rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset">{{ r.motivo }}</span>
                            </td>
                        </tr>
                        <tr v-if="!noConvalidados.length"><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin cursos no convalidados para los filtros aplicados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
