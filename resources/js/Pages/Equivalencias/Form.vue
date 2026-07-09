<script setup>
import { router, Link } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({
    carreras: Array, instituciones: Array, mallas: Array,
    cursosUsil: Array, cursosExternos: Array, previas: Array, seleccion: Object,
    postulante: { type: Object, default: null },
    destinosSelector: { type: Array, default: () => [] },
});

// --- Navegación del wizard ---
const paso = ref(1);
const irA = (n) => { paso.value = n; window.scrollTo({ top: 0, behavior: 'smooth' }); };

// --- Selección (contexto del expediente) ---
const sel = reactive({
    carrera_usil_id: props.seleccion?.carrera_usil_id ?? props.postulante?.carrera_destino_id ?? '',
    malla_id: props.seleccion?.malla_id ?? '',
    carrera_externa_id: props.seleccion?.carrera_externa_id ?? props.postulante?.carrera_externa_id ?? '',
});

const recargar = () => router.get('/equivalencias/crear',
    { ...sel, ...(props.postulante ? { destino: props.postulante.destino_id } : {}) },
    { preserveState: true, preserveScroll: true, replace: true });

// Años de malla disponibles para la carrera USIL elegida.
const aniosMalla = computed(() => {
    const vistos = new Map();
    props.mallas.filter((m) => m.carrera_id == sel.carrera_usil_id)
        .forEach((m) => { if (!vistos.has(m.anio)) vistos.set(m.anio, m); });
    return [...vistos.keys()].sort((a, b) => b - a);
});
const anioSeleccionado = computed(() => props.mallas.find((m) => m.id == sel.malla_id)?.anio ?? '');

const onCarreraUsil = (id) => { sel.carrera_usil_id = id; sel.malla_id = ''; recargar(); };
const onAnio = (e) => {
    const anio = e.target.value;
    const candidatas = props.mallas.filter((m) => m.carrera_id == sel.carrera_usil_id && m.anio == anio);
    const elegida = candidatas.find((m) => m.activa) ?? candidatas[0];
    sel.malla_id = elegida?.id ?? '';
    recargar();
};
const onDestino = (id) => { if (id) router.get('/equivalencias/crear', { destino: id }); };

const carrerasUsilOpts = computed(() => props.carreras.map((c) => ({ value: c.id, label: c.nombre })));
const destinosOpts = computed(() => props.destinosSelector.map((p) => ({ value: p.id, label: p.label })));

const contextoListo = computed(() =>
    !!sel.carrera_usil_id && !!sel.malla_id && props.cursosExternos.length > 0 && props.cursosUsil.length > 0);

// --- Emparejamiento (paso 2) ---
const yaEquivalente = (ce, cu) => props.previas?.some((p) => p.curso_externo_id == ce && p.curso_usil_id == cu);
const seleccionUsil = reactive({}); // curso_externo_id -> curso_usil_id elegido
const sugerencias = ref({});
const cargandoIA = ref(null);

const pedirSugerencias = async (cursoExternoId) => {
    cargandoIA.value = cursoExternoId;
    try {
        const { data } = await window.axios.post('/sugerencias', {
            curso_externo_id: cursoExternoId,
            carrera_usil_id: sel.carrera_usil_id,
            malla_id: sel.malla_id,
        });
        sugerencias.value[cursoExternoId] = data.sugerencias;
    } finally {
        cargandoIA.value = null;
    }
};

const guardar = (cursoExternoId, cursoUsilId, origenIa = false, confianza = null) => {
    if (!cursoUsilId) return;
    const ruta = origenIa ? '/sugerencias/aceptar' : '/equivalencias';
    router.post(ruta, {
        carrera_externa_id: sel.carrera_externa_id,
        carrera_usil_id: sel.carrera_usil_id,
        curso_externo_id: cursoExternoId,
        curso_usil_id: cursoUsilId,
        tipo_equivalencia: 'completa',
        confianza,
    }, { preserveScroll: true, preserveState: true });
};

// --- Resumen (paso 3) ---
const cursoUsilDe = (id) => props.cursosUsil.find((c) => c.id == id);
const cursoExtDe = (id) => props.cursosExternos.find((c) => c.id == id);
const resumen = computed(() => (props.previas ?? []).map((p) => {
    const u = cursoUsilDe(p.curso_usil_id);
    const e = cursoExtDe(p.curso_externo_id);
    return {
        externo: e ? `${e.codigo} · ${e.nombre}` : `#${p.curso_externo_id}`,
        usil: u ? `${u.codigo} · ${u.nombre}` : `#${p.curso_usil_id}`,
        creditos: u ? Number(u.creditos) : 0,
        tipo: p.tipo_equivalencia,
    };
}));
const totalCreditos = computed(() => resumen.value.reduce((s, r) => s + r.creditos, 0));
const cobertura = computed(() => props.cursosExternos.length
    ? Math.round((resumen.value.length / props.cursosExternos.length) * 100) : 0);

const aprobar = () => {
    if (props.postulante && confirm('¿Aprobar el emparejamiento de este destino?')) {
        router.post(`/equivalencias/aprobar/${props.postulante.destino_id}`);
    }
};

const PASOS = [
    { n: 1, label: 'Contexto del Expediente' },
    { n: 2, label: 'Emparejamiento de Cursos' },
    { n: 3, label: 'Resumen de Convalidación' },
];
</script>

<template>
    <div class="max-w-5xl">
        <!-- Encabezado -->
        <div class="mb-5">
            <h1 class="text-2xl font-semibold text-[#1F3864]">Gestión de Equivalencias</h1>
            <p class="mt-1 text-sm text-slate-500">Diccionario académico: cruce de cursos externos con cursos USIL (memoria institucional).</p>
        </div>

        <!-- Pestañas -->
        <div class="mb-6 flex gap-6 border-b border-slate-200 text-sm font-medium">
            <Link href="/equivalencias" class="-mb-px border-b-2 border-transparent pb-2 text-slate-500 hover:text-[#2E75B6]">
                Bandeja de Atención
            </Link>
            <span class="-mb-px border-b-2 border-[#1F3864] pb-2 text-[#1F3864]">Emparejamiento de Cursos</span>
        </div>

        <!-- Stepper -->
        <div class="mb-6 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-6 py-5">
            <template v-for="(p, i) in PASOS" :key="p.n">
                <button @click="p.n < paso || (p.n === 2 && contextoListo) ? irA(p.n) : null"
                        class="flex items-center gap-3"
                        :class="p.n <= paso || (p.n === 2 && contextoListo) ? 'cursor-pointer' : 'cursor-default'">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold"
                          :class="paso >= p.n ? 'bg-[#1F3864] text-white' : 'bg-slate-200 text-slate-500'">{{ p.n }}</span>
                    <span class="text-sm font-medium" :class="paso >= p.n ? 'text-[#1F3864]' : 'text-slate-400'">{{ p.label }}</span>
                </button>
                <div v-if="i < PASOS.length - 1" class="mx-3 h-px flex-1 bg-slate-200"></div>
            </template>
        </div>

        <!-- ===================== PASO 1: Contexto del Expediente ===================== -->
        <section v-show="paso === 1" class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="mb-5 text-lg font-semibold text-[#1F3864]">Contexto del Expediente</h2>

            <!-- Selector de destino cuando se entra sin expediente -->
            <div v-if="!postulante" class="mb-6 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                <label class="mb-1 block text-sm font-medium text-slate-700">Selecciona el destino a emparejar (postulante → carrera USIL)</label>
                <Autocomplete :options="destinosOpts" @update:modelValue="onDestino" placeholder="Buscar por apellidos, documento o carrera…" />
                <p class="mt-2 text-xs text-slate-500">O ingresa desde la <Link href="/equivalencias" class="text-[#2E75B6] hover:underline">Bandeja de Atención</Link>.</p>
            </div>

            <div v-if="postulante" class="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                <!-- Datos del postulante -->
                <div class="space-y-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Datos del postulante</p>
                    <div>
                        <label class="mb-1 block text-sm text-slate-500">Nombre Completo</label>
                        <input :value="postulante.nombre" readonly
                               class="w-full cursor-default rounded-md border-slate-200 bg-slate-50 text-sm text-slate-700" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm text-slate-500">{{ postulante.tipo_documento || 'Documento' }}</label>
                            <input :value="postulante.documento" readonly
                                   class="w-full cursor-default rounded-md border-slate-200 bg-slate-50 text-sm text-slate-700" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm text-slate-500">Institución de Origen <span class="text-red-500">*</span></label>
                            <input :value="`${postulante.institucion}${postulante.institucion_pais ? ' (' + postulante.institucion_pais + ')' : ''}`" readonly
                                   class="w-full cursor-default rounded-md border-slate-200 bg-slate-50 text-sm text-slate-700" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-500">Carrera de Origen</label>
                        <input :value="postulante.carrera_origen" readonly
                               class="w-full cursor-default rounded-md border-slate-200 bg-slate-50 text-sm text-slate-700" />
                    </div>
                </div>

                <!-- Destino USIL -->
                <div class="space-y-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Destino USIL</p>
                    <div>
                        <label class="mb-1 block text-sm text-slate-500">Carrera USIL <span class="text-red-500">*</span></label>
                        <Autocomplete :model-value="sel.carrera_usil_id" @update:modelValue="onCarreraUsil"
                                      :options="carrerasUsilOpts" placeholder="Buscar carrera USIL…" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-500">Año de Malla <span class="text-red-500">*</span></label>
                        <select :value="anioSeleccionado" @change="onAnio" :disabled="!sel.carrera_usil_id"
                                class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6] disabled:bg-slate-50">
                            <option value="">Seleccione año…</option>
                            <option v-for="a in aniosMalla" :key="a" :value="a">{{ a }}</option>
                        </select>
                        <p v-if="sel.carrera_usil_id && !aniosMalla.length" class="mt-1 text-xs text-amber-600">
                            Esta carrera no tiene mallas registradas.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Record histórico -->
            <template v-if="postulante">
                <p class="mb-2 mt-7 text-xs font-semibold uppercase tracking-wide text-slate-400">Record histórico del postulante</p>
                <div v-if="cursosExternos.length"
                     class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-green-200 bg-green-50 p-4">
                    <div>
                        <p class="text-sm font-semibold text-green-800">{{ cursosExternos.length }} cursos identificados — listo para emparejar</p>
                        <p class="text-xs text-green-700">Listo para emparejar con la malla USIL</p>
                    </div>
                    <Link :href="`/postulantes/${postulante.id}/editar`" class="text-sm font-medium text-[#2E75B6] hover:underline">Cambiar archivo</Link>
                </div>
                <div v-else class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                    No se identificaron cursos en el expediente del postulante (carrera de origen sin malla cargada).
                </div>

                <div class="mt-6 flex justify-end">
                    <button @click="irA(2)" :disabled="!contextoListo"
                            class="rounded-md bg-[#1F3864] px-5 py-2.5 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:cursor-not-allowed disabled:bg-slate-300">
                        Siguiente: Emparejar Cursos →
                    </button>
                </div>
            </template>
        </section>

        <!-- ===================== PASO 2: Emparejamiento de Cursos ===================== -->
        <section v-show="paso === 2" class="rounded-xl border border-slate-200 bg-white p-6">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-[#1F3864]">Emparejamiento de Cursos</h2>
                <span class="text-xs text-slate-500">{{ resumen.length }} de {{ cursosExternos.length }} emparejados</span>
            </div>

            <div v-if="cursosExternos.length && cursosUsil.length" class="space-y-3">
                <div v-for="ce in cursosExternos" :key="ce.id" class="rounded-lg border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="min-w-[200px] flex-1">
                            <p class="text-sm font-medium text-slate-800">{{ ce.codigo }} · {{ ce.nombre }}</p>
                            <p class="text-xs text-slate-400">{{ Number(ce.creditos) }} créditos</p>
                        </div>
                        <select v-model="seleccionUsil[ce.id]" class="min-w-[220px] rounded-md border-slate-300 text-sm">
                            <option value="">Curso USIL equivalente…</option>
                            <option v-for="cu in cursosUsil" :key="cu.id" :value="cu.id" :disabled="yaEquivalente(ce.id, cu.id)">
                                {{ cu.nombre }} {{ yaEquivalente(ce.id, cu.id) ? '(ya equivalente)' : '' }}
                            </option>
                        </select>
                        <button @click="guardar(ce.id, seleccionUsil[ce.id])"
                                class="rounded-md bg-[#2E75B6] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#1F3864]">
                            Guardar
                        </button>
                        <button @click="pedirSugerencias(ce.id)" :disabled="cargandoIA === ce.id"
                                class="rounded-md border border-[#7030A0] px-3 py-1.5 text-xs font-medium text-[#7030A0] hover:bg-purple-50 disabled:opacity-50">
                            {{ cargandoIA === ce.id ? 'Consultando IA…' : 'Sugerir con IA' }}
                        </button>
                    </div>

                    <div v-if="sugerencias[ce.id]" class="mt-3 space-y-1 rounded-md bg-purple-50 p-3">
                        <p v-if="!sugerencias[ce.id].length" class="text-xs text-slate-500">Sin sugerencias con confianza suficiente.</p>
                        <div v-for="(s, i) in sugerencias[ce.id]" :key="i" class="flex items-center justify-between text-xs">
                            <span class="text-slate-700">
                                <strong>{{ s.nombre }}</strong>
                                <span class="ml-2 rounded bg-white px-1.5 py-0.5 text-[#7030A0]">{{ Number(s.confianza).toFixed(0) }}% · {{ s.origen }}</span>
                                <span class="ml-2 text-slate-500">{{ s.justificacion }}</span>
                            </span>
                            <button @click="guardar(ce.id, s.curso_usil_id, true, s.confianza)"
                                    class="ml-3 rounded bg-[#7030A0] px-2 py-1 font-medium text-white hover:opacity-90">Aceptar</button>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else class="rounded-lg border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
                Completa el contexto del expediente para ver los cursos a emparejar.
            </p>

            <div class="mt-6 flex justify-between">
                <button @click="irA(1)" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                <button @click="irA(3)" class="rounded-md bg-[#1F3864] px-5 py-2.5 text-sm font-medium text-white hover:bg-[#2E75B6]">
                    Siguiente: Resumen →
                </button>
            </div>
        </section>

        <!-- ===================== PASO 3: Resumen de Convalidación ===================== -->
        <section v-show="paso === 3" class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="mb-5 text-lg font-semibold text-[#1F3864]">Resumen de Convalidación</h2>

            <div class="mb-5 grid grid-cols-3 gap-3">
                <div class="rounded-lg bg-slate-50 p-4 text-center">
                    <p class="text-xs uppercase tracking-wide text-slate-400">Cursos emparejados</p>
                    <p class="mt-1 text-2xl font-bold text-[#1F3864]">{{ resumen.length }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-4 text-center">
                    <p class="text-xs uppercase tracking-wide text-slate-400">Créditos convalidados</p>
                    <p class="mt-1 text-2xl font-bold text-[#1F3864]">{{ totalCreditos }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-4 text-center">
                    <p class="text-xs uppercase tracking-wide text-slate-400">Cobertura</p>
                    <p class="mt-1 text-2xl font-bold text-[#1F3864]">{{ cobertura }}%</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2 font-semibold">Curso externo</th>
                            <th class="px-4 py-2 font-semibold">Curso USIL</th>
                            <th class="px-4 py-2 font-semibold">Créditos</th>
                            <th class="px-4 py-2 font-semibold">Tipo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(r, i) in resumen" :key="i">
                            <td class="px-4 py-2 text-slate-600">{{ r.externo }}</td>
                            <td class="px-4 py-2 font-medium text-slate-800">{{ r.usil }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ r.creditos }}</td>
                            <td class="px-4 py-2 capitalize text-slate-500">{{ r.tipo }}</td>
                        </tr>
                        <tr v-if="!resumen.length">
                            <td colspan="4" class="px-4 py-8 text-center text-slate-400">Aún no hay cursos emparejados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <button @click="irA(2)" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                <button v-if="postulante" @click="aprobar" :disabled="postulante.estado === 'aprobada' || !resumen.length"
                        class="rounded-md bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                    {{ postulante.estado === 'aprobada' ? '✓ Emparejamiento aprobado' : 'Aprobar convalidación' }}
                </button>
            </div>
        </section>

        <div class="mt-6">
            <Link href="/equivalencias" class="text-sm text-[#2E75B6] hover:underline">← Volver a la Bandeja de Atención</Link>
        </div>
    </div>
</template>
