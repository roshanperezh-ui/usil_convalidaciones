<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({
    postulantes: Object,
    kpis: { type: Object, default: () => ({}) },
    instituciones: { type: Array, default: () => [] },
    coordinadores: { type: Array, default: () => [] },
    filtros: { type: Object, default: () => ({}) },
});

// RBAC: permisos del usuario para gatear acciones del flujo.
const permisos = computed(() => usePage().props.auth?.user?.permisos ?? []);
const puede = (c) => permisos.value.includes('*') || permisos.value.includes(c);

// Acciones del flujo de aprobación.
const asignar = (p) => {
    const id = window.prompt('ID del coordinador a asignar:\n' + props.coordinadores.map((c) => `${c.id} = ${c.nombre}`).join('\n'));
    if (id) router.post(`/equivalencias/asignar/${p.id}`, { asignado_a_id: id }, { preserveScroll: true });
};
const observar = (p, devolver) => {
    const motivo = window.prompt(devolver ? 'Motivo de devolución:' : 'Motivo de observación:');
    if (motivo) router.post(`/equivalencias/observar/${p.id}`, { motivo, devolver }, { preserveScroll: true });
};
const aprobar = (p) => {
    if (confirm('¿Aprobar esta evaluación?')) router.post(`/equivalencias/aprobar/${p.id}`, {}, { preserveScroll: true });
};

const filtro = reactive({
    q: props.filtros?.q ?? '',
    estado: props.filtros?.estado ?? '',
    institucion_id: props.filtros?.institucion_id ?? '',
});

const institucionesOpts = computed(() => props.instituciones.map((i) => ({ value: i.id, label: i.nombre })));

const aplicar = () => router.get('/equivalencias', filtro, { preserveState: true, preserveScroll: true, replace: true });

const atender = (p) => router.post(`/equivalencias/atender/${p.id}`);

const ESTADOS = {
    pendiente:   { label: 'Pendiente', clase: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500' },
    asignada:    { label: 'Asignada', clase: 'bg-indigo-50 text-indigo-700 ring-indigo-200', dot: 'bg-indigo-500' },
    en_revision: { label: 'En revisión', clase: 'bg-blue-50 text-blue-700 ring-blue-200', dot: 'bg-blue-500' },
    observada:   { label: 'Observada', clase: 'bg-orange-50 text-orange-700 ring-orange-200', dot: 'bg-orange-500' },
    devuelta:    { label: 'Devuelta', clase: 'bg-red-50 text-red-700 ring-red-200', dot: 'bg-red-500' },
    aprobada:    { label: 'Aprobada', clase: 'bg-green-50 text-green-700 ring-green-200', dot: 'bg-green-500' },
};
const estado = (e) => ESTADOS[e] ?? { label: e ?? '—', clase: 'bg-slate-100 text-slate-600 ring-slate-200', dot: 'bg-slate-400' };
</script>

<template>
    <div>
        <!-- Encabezado -->
        <div class="mb-5">
            <h1 class="text-2xl font-semibold text-[#1F3864]">Gestión de Equivalencias</h1>
            <p class="mt-1 text-sm text-slate-500">Diccionario académico: cruce de cursos externos con cursos USIL (memoria institucional).</p>
        </div>

        <!-- Pestañas -->
        <div class="mb-6 flex gap-6 border-b border-slate-200 text-sm font-medium">
            <span class="-mb-px border-b-2 border-[#1F3864] pb-2 text-[#1F3864]">Bandeja de Atención</span>
            <Link href="/equivalencias/crear" class="-mb-px border-b-2 border-transparent pb-2 text-slate-500 hover:text-[#2E75B6]">
                Emparejamiento de Cursos
            </Link>
        </div>

        <!-- KPIs -->
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total</p>
                <p class="mt-1 text-3xl font-bold text-slate-800">{{ kpis.total ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Pendientes</p>
                <p class="mt-1 text-3xl font-bold text-amber-600">{{ kpis.pendientes ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Aprobadas</p>
                <p class="mt-1 text-3xl font-bold text-green-600">{{ kpis.aprobadas ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">En revisión</p>
                <p class="mt-1 text-3xl font-bold text-blue-600">{{ kpis.en_revision ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Tasa aprob.</p>
                <p class="mt-1 text-3xl font-bold text-[#1F3864]">{{ kpis.tasa_aprob ?? 0 }}%</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm text-slate-500">Buscar postulante</label>
                <input v-model="filtro.q" @keyup.enter="aplicar" placeholder="Nombre o DNI del postulante…"
                       class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-500">Estado</label>
                <select v-model="filtro.estado" @change="aplicar"
                        class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                    <option value="">Todos</option>
                    <option value="pendiente">Pendientes</option>
                    <option value="en_revision">En revisión</option>
                    <option value="aprobada">Aprobadas</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-500">Institución</label>
                <Autocomplete v-model="filtro.institucion_id" :options="institucionesOpts"
                              placeholder="Todas" @update:modelValue="aplicar" />
            </div>
        </div>

        <!-- Tabla -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Postulante</th>
                            <th class="px-4 py-3 font-semibold">Inst. Origen</th>
                            <th class="px-4 py-3 font-semibold">Carrera Origen</th>
                            <th class="px-4 py-3 font-semibold">Carrera USIL Destino</th>
                            <th class="px-4 py-3 font-semibold">Documentos</th>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 text-right font-semibold">Atención</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in postulantes.data" :key="p.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ p.postulante }}</p>
                                <p class="text-xs text-slate-400">{{ p.documento }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-slate-700">{{ p.institucion }}</p>
                                <p class="text-xs text-slate-400">{{ p.sigla }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ p.carrera_origen }}</td>
                            <td class="px-4 py-3">
                                <p class="font-semibold uppercase text-[#1F3864]">{{ p.carrera_destino }}</p>
                                <p v-if="p.facultad" class="text-xs text-slate-400">{{ p.facultad }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="p.docs_completos" class="inline-flex items-center gap-1 text-xs font-medium text-green-600">✓ Completos</span>
                                <span v-else class="inline-flex items-center gap-1 text-xs font-medium text-amber-600">⚠ Incompletos</span>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="estado(p.estado).clase"
                                      class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">
                                    <span :class="estado(p.estado).dot" class="h-1.5 w-1.5 rounded-full"></span>
                                    {{ estado(p.estado).label }}
                                </span>
                                <p v-if="p.asignado" class="mt-1 text-xs text-slate-400">👤 {{ p.asignado }}</p>
                                <p v-if="p.observacion" class="mt-0.5 text-xs italic text-orange-600" :title="p.observacion">📝 {{ p.observacion }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    <button v-if="puede('solicitudes.asignar')" @click="asignar(p)"
                                            class="rounded-md border border-indigo-300 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-50">Asignar</button>
                                    <button v-if="puede('evaluacion.editar')" @click="atender(p)"
                                            class="rounded-md bg-[#1F3864] px-3 py-1 text-xs font-medium text-white hover:bg-[#2E75B6]">
                                        {{ p.estado === 'aprobada' ? 'Revisar' : 'Atender' }}
                                    </button>
                                    <button v-if="puede('evaluacion.observar')" @click="observar(p, false)"
                                            class="rounded-md border border-orange-300 px-2.5 py-1 text-xs font-medium text-orange-700 hover:bg-orange-50">Observar</button>
                                    <button v-if="puede('evaluacion.aprobar') && p.estado !== 'aprobada'" @click="aprobar(p)"
                                            class="rounded-md bg-green-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-green-700">Aprobar</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!postulantes.data.length">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                                No hay postulantes en la bandeja con los filtros seleccionados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="postulantes.data.length && postulantes.last_page > 1"
                 class="flex flex-wrap items-center justify-end gap-1 border-t border-slate-200 px-4 py-3">
                <template v-for="(link, i) in postulantes.links" :key="i">
                    <Link v-if="link.url" :href="link.url" preserve-scroll
                          :class="link.active ? 'bg-[#1F3864] text-white' : 'text-slate-600 hover:bg-slate-100'"
                          class="min-w-[34px] rounded-md px-2.5 py-1.5 text-center text-sm" v-html="link.label" />
                    <span v-else class="min-w-[34px] cursor-not-allowed rounded-md px-2.5 py-1.5 text-center text-sm text-slate-300"
                          v-html="link.label" />
                </template>
            </div>
        </div>
    </div>
</template>
