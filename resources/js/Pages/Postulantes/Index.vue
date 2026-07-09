<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({ postulantes: Object, total: Number, carreras: Array, estados: Array, filtros: Object });
const carrerasOpts = computed(() => props.carreras.map((c) => ({ value: c.id, label: c.nombre })));

const filtro = reactive({
    q: props.filtros?.q ?? '',
    estado: props.filtros?.estado ?? '',
    carrera_destino_id: props.filtros?.carrera_destino_id ?? '',
});
const aplicar = () => router.get('/postulantes', filtro, { preserveState: true, preserveScroll: true, replace: true });
const limpiar = () => { filtro.q = ''; filtro.estado = ''; filtro.carrera_destino_id = ''; router.get('/postulantes', {}, { preserveScroll: true, replace: true }); };
const eliminar = (p) => { if (confirm(`¿Eliminar al postulante "${p.nombre}"?`)) router.delete(`/postulantes/${p.id}`, { preserveScroll: true }); };
const resetearAcceso = (p) => { if (confirm(`¿Restablecer el acceso al portal de "${p.nombre}"? Se generará una contraseña temporal.`)) router.patch(`/postulantes/${p.id}/reset-acceso`, {}, { preserveScroll: true }); };

const ESTADO = {
    nuevo: { label: 'Nuevo', clase: 'bg-slate-100 text-slate-600 ring-slate-200' },
    en_evaluacion: { label: 'En evaluación', clase: 'bg-amber-50 text-amber-700 ring-amber-200' },
    admitido: { label: 'Admitido', clase: 'bg-green-50 text-green-700 ring-green-200' },
    rechazado: { label: 'Rechazado', clase: 'bg-red-50 text-red-700 ring-red-200' },
    matriculado: { label: 'Matriculado', clase: 'bg-indigo-50 text-indigo-700 ring-indigo-200' },
};
const estadoLabel = (e) => ESTADO[e]?.label ?? e;
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">Postulantes</h1>
                <p class="mt-1 text-sm text-slate-500">Solicitantes de convalidación por traslado externo.</p>
            </div>
            <Link href="/postulantes/crear"
                  class="inline-flex items-center gap-2 rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#2E75B6]">
                <span class="text-base leading-none">+</span> Nuevo postulante
            </Link>
        </div>

        <div class="mb-6 grid gap-4 lg:grid-cols-[1fr_auto]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Buscar</label>
                        <input v-model="filtro.q" type="text" placeholder="Nombre, documento, código o correo…" @keyup.enter="aplicar"
                               class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Estado</label>
                        <select v-model="filtro.estado" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="">Todos</option>
                            <option v-for="e in estados" :key="e" :value="e">{{ estadoLabel(e) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Carrera destino</label>
                        <Autocomplete v-model="filtro.carrera_destino_id" :options="carrerasOpts" placeholder="Todas las carreras" />
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button @click="aplicar" class="rounded-md bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white hover:bg-[#1F3864]">Filtrar</button>
                    <button @click="limpiar" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Limpiar</button>
                </div>
            </div>
            <div class="flex min-w-[160px] flex-col justify-center rounded-xl bg-gradient-to-br from-[#1F3864] to-[#2E75B6] p-5 text-white shadow-sm">
                <span class="text-xs font-medium uppercase tracking-wide text-blue-100">Postulantes</span>
                <span class="mt-1 text-4xl font-bold leading-none">{{ total }}</span>
                <span class="mt-1 text-xs text-blue-100">registrados</span>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Código</th>
                            <th class="px-4 py-3 font-semibold">Documento</th>
                            <th class="px-4 py-3 font-semibold">Postulante</th>
                            <th class="px-4 py-3 font-semibold">Carrera destino</th>
                            <th class="px-4 py-3 font-semibold">Procedencia</th>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in postulantes.data" :key="p.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ p.codigo }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ p.documento }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-800">{{ p.nombre }}</p>
                                <p class="text-xs text-slate-400">{{ p.email }}</p>
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ p.carrera_destino || '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ p.procedencia || '—' }}</td>
                            <td class="px-4 py-3">
                                <span :class="ESTADO[p.estado]?.clase" class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">{{ estadoLabel(p.estado) }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/postulantes/${p.id}/editar`"
                                          class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-[#2E75B6] hover:border-[#2E75B6] hover:bg-slate-50">Editar</Link>
                                    <button @click="resetearAcceso(p)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-amber-700 hover:border-amber-300 hover:bg-amber-50">Resetear acceso</button>
                                    <button @click="eliminar(p)"
                                            class="rounded-md border border-slate-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:border-red-300 hover:bg-red-50">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!postulantes.data.length">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-400">No se encontraron postulantes.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="postulantes.data.length" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
                <p class="text-xs text-slate-500">Mostrando {{ postulantes.from }}–{{ postulantes.to }} de {{ postulantes.total }}</p>
                <nav v-if="postulantes.last_page > 1" class="flex flex-wrap items-center gap-1">
                    <template v-for="(link, i) in postulantes.links" :key="i">
                        <Link v-if="link.url" :href="link.url" preserve-scroll
                              :class="link.active ? 'bg-[#1F3864] text-white' : 'text-slate-600 hover:bg-slate-100'"
                              class="min-w-[34px] rounded-md px-2.5 py-1.5 text-center text-sm" v-html="link.label" />
                        <span v-else class="min-w-[34px] rounded-md px-2.5 py-1.5 text-center text-sm text-slate-300" v-html="link.label" />
                    </template>
                </nav>
            </div>
        </div>
    </div>
</template>
