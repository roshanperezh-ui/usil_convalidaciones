<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({
    mallas: Object,
    kpis: { type: Object, default: () => ({}) },
    filtros: { type: Object, default: () => ({}) },
});

const filtro = reactive({
    q: props.filtros?.q ?? '',
});

const aplicar = () => router.get('/equivalencias', filtro, { preserveState: true, preserveScroll: true, replace: true });

// Formulario para subir nueva Malla
const showModal = ref(false);
const form = useForm({
    carrera_externa_id: '',
    anio: new Date().getFullYear().toString(),
    version: '1',
    pdf: null,
});

const submitMalla = () => {
    form.post('/mallas-externas', {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        }
    });
};

const handleFile = (e) => {
    form.pdf = e.target.files[0];
};

const editarMalla = (m) => router.get(`/equivalencias/crear`, { malla_id: m.id });

</script>

<template>
    <div>
        // Encabezado
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-[#1F3864]">Base de Conocimiento (Equivalencias)</h1>
                <p class="mt-1 text-sm text-slate-500">Gestor maestro de Mallas Curriculares externas y sus reglas de equivalencia para la IA.</p>
            </div>
            <Link href="/equivalencias/crear" class="rounded-md bg-[#1F3864] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E75B6]">
                + Extraer Malla con IA
            </Link>
        </div>

        <!-- KPIs -->
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total Mallas</p>
                <p class="mt-1 text-3xl font-bold text-slate-800">{{ kpis.total_mallas ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Mallas Activas</p>
                <p class="mt-1 text-3xl font-bold text-green-600">{{ kpis.activas ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Cursos en Diccionario</p>
                <p class="mt-1 text-3xl font-bold text-blue-600">{{ kpis.total_cursos ?? 0 }}</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm text-slate-500">Buscar por institución o carrera</label>
                <input v-model="filtro.q" @keyup.enter="aplicar" placeholder="Ej. SENATI o Software..."
                       class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
            </div>
        </div>

        <!-- Tabla -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Institución Origen</th>
                            <th class="px-4 py-3 font-semibold">Carrera Origen</th>
                            <th class="px-4 py-3 font-semibold text-center">Año / Versión</th>
                            <th class="px-4 py-3 font-semibold text-center">Malla Oficial (PDF)</th>
                            <th class="px-4 py-3 font-semibold text-center">Cursos / Mapeados</th>
                            <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="m in mallas.data" :key="m.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-medium text-slate-800">{{ m.institucion }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ m.carrera }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600">{{ m.anio }} v{{ m.version }}</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <a v-if="m.pdf_path" :href="m.pdf_path" target="_blank" class="inline-flex items-center text-[#2E75B6] hover:underline">
                                    📄 Ver PDF
                                </a>
                                <span v-else class="text-slate-400">Sin archivo</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-medium text-[#1F3864]">{{ m.cursos_mapeados }}</span> / <span class="text-slate-500">{{ m.total_cursos }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button @click="editarMalla(m)" class="rounded-md bg-[#1F3864] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#2E75B6]">
                                    Mapear Cursos
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!mallas.data.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                                No hay mallas externas registradas con los filtros actuales.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="mallas.data.length && mallas.last_page > 1"
                 class="flex flex-wrap items-center justify-end gap-1 border-t border-slate-200 px-4 py-3">
                <template v-for="(link, i) in mallas.links" :key="i">
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
