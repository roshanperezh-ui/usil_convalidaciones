<script setup>
import { router, Link, usePage } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

const permisos = computed(() => usePage().props.auth?.user?.permisos ?? []);
const puedeEditar = computed(() => permisos.value.includes('*') || permisos.value.includes('evaluacion.editar'));

const props = defineProps({
    carreras: Array, mallasUsil: Array,
    cursosUsil: Array, previas: Array, seleccion: Object,
    malla: { type: Object, default: null },
    instituciones: Array,
});

// --- Navegación del wizard (Pipeline IA) ---
const paso = ref(props.malla ? 4 : 1);
const irA = (n) => { paso.value = n; window.scrollTo({ top: 0, behavior: 'smooth' }); };

const PASOS = [
    { n: 1, label: 'Recepción' },
    { n: 2, label: 'Extracción IA' },
    { n: 3, label: 'Catálogo Extraído' },
    { n: 4, label: 'Mapeo Maestro' },
    { n: 5, label: 'Diccionario Generado' },
];

// ===================== ETAPAS 1-3: CREACIÓN DE LA MALLA EXTERNA =====================
const formRecepcion = reactive({
    institucion_id: '',
    carrera_externa_id: '',
    anio: new Date().getFullYear().toString(),
    version: '1',
    pdf: null,
});

const archivoNombre = ref('');
const extrayendo = ref(false);
const errorExtraccion = ref('');
const cursosExtraidos = ref([]);
const datosExtraidos = ref({}); // institucion, carrera info

const carrerasExternasOpts = computed(() => {
    if (!formRecepcion.institucion_id) return [];
    const inst = props.instituciones.find(i => i.id == formRecepcion.institucion_id);
    return inst ? inst.carreras : [];
});

const onArchivo = (e) => { 
    formRecepcion.pdf = e.target.files[0] ?? null; 
    archivoNombre.value = formRecepcion.pdf?.name ?? '';
};

// Llama al endpoint extraerIA de MallaExternaController
const extraerConIA = async () => {
    if (!formRecepcion.pdf) { errorExtraccion.value = 'Debes subir un archivo PDF.'; return; }
    if (!formRecepcion.carrera_externa_id) { errorExtraccion.value = 'Falta ID Carrera (Demo).'; return; }

    extrayendo.value = true;
    errorExtraccion.value = '';
    paso.value = 2;

    const formData = new FormData();
    formData.append('documento', formRecepcion.pdf);

    try {
        const { data } = await window.axios.post('/mallas-externas/extraer-ia', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        datosExtraidos.value = { institucion: data.institucion, carrera: data.carrera };
        cursosExtraidos.value = data.cursos || [];
        
        if (cursosExtraidos.value.length === 0) {
            errorExtraccion.value = 'La IA no pudo encontrar cursos en el PDF.';
            paso.value = 1;
        } else {
            paso.value = 3;
        }
    } catch (e) {
        errorExtraccion.value = e.response?.data?.message || 'Error al comunicarse con la IA.';
        paso.value = 1;
    } finally {
        extrayendo.value = false;
    }
};

const guardarMallaOficial = () => {
    const formData = new FormData();
    formData.append('carrera_externa_id', formRecepcion.carrera_externa_id);
    formData.append('anio', formRecepcion.anio);
    formData.append('version', formRecepcion.version);
    formData.append('pdf', formRecepcion.pdf);
    formData.append('cursos', JSON.stringify(cursosExtraidos.value));

    window.axios.post('/mallas-externas', formData).then(res => {
        // Redirige al step 4 (Mapeo) pasándole el malla_id que acaba de crearse
        router.get(`/equivalencias/crear`, { malla_id: res.data.id }, { preserveScroll: true });
    }).catch(e => {
        alert('Error al guardar la malla: ' + (e.response?.data?.message || 'Revisa consola'));
    });
};


// ===================== ETAPAS 4-5: MAPEO Y DICCIONARIO =====================
const sel = reactive({
    carrera_usil_id: props.seleccion?.carrera_usil_id ?? '',
    malla_usil_id: props.seleccion?.malla_usil_id ?? '',
});

const recargarMapeo = () => router.get('/equivalencias/crear',
    { malla_id: props.malla?.id, ...sel },
    { preserveState: true, preserveScroll: true, replace: true });

const aniosMallaUsil = computed(() => {
    const vistos = new Map();
    props.mallasUsil.filter((m) => m.carrera_id == sel.carrera_usil_id)
        .forEach((m) => { if (!vistos.has(m.anio)) vistos.set(m.anio, m); });
    return [...vistos.keys()].sort((a, b) => b - a);
});
const anioUsilSeleccionado = computed(() => props.mallasUsil.find((m) => m.id == sel.malla_usil_id)?.anio ?? '');

const onCarreraUsil = (e) => { sel.carrera_usil_id = e.target.value; sel.malla_usil_id = ''; recargarMapeo(); };
const onAnioUsil = (e) => {
    const anio = e.target.value;
    const candidatas = props.mallasUsil.filter((m) => m.carrera_id == sel.carrera_usil_id && m.anio == anio);
    const elegida = candidatas.find((m) => m.activa) ?? candidatas[0];
    sel.malla_usil_id = elegida?.id ?? '';
    recargarMapeo();
};

const contextoListo = computed(() => !!sel.carrera_usil_id && !!sel.malla_usil_id && props.cursosUsil.length > 0);
const cursosExternosFinales = computed(() => props.malla?.cursos ?? []);

const yaEquivalente = (ce, cu) => props.previas?.some((p) => p.curso_externo_id == ce && p.curso_usil_id == cu);
const seleccionUsil = reactive({});
const sugerencias = ref({});
const cargandoIA = ref(null);

const pedirSugerencias = async (cursoExternoId) => {
    cargandoIA.value = cursoExternoId;
    try {
        const { data } = await window.axios.post('/sugerencias', {
            curso_externo_id: cursoExternoId,
            carrera_usil_id: sel.carrera_usil_id,
            malla_id: sel.malla_usil_id,
        });
        sugerencias.value[cursoExternoId] = data.sugerencias;
    } finally {
        cargandoIA.value = null;
    }
};

const guardarMapeo = (cursoExternoId, cursoUsilId, origenIa = false, confianza = null) => {
    if (!cursoUsilId) return;
    const ruta = origenIa ? '/sugerencias/aceptar' : '/equivalencias';
    router.post(ruta, {
        carrera_externa_id: props.malla.carrera_externa_id,
        carrera_usil_id: sel.carrera_usil_id,
        curso_externo_id: cursoExternoId,
        curso_usil_id: cursoUsilId,
        tipo_equivalencia: 'completa',
        confianza,
    }, { preserveScroll: true, preserveState: true });
};

const cursoUsilDe = (id) => props.cursosUsil.find((c) => c.id == id);
const cursoExtDe = (id) => cursosExternosFinales.value.find((c) => c.id == id);
const resumen = computed(() => (props.previas ?? []).map((p) => {
    const u = cursoUsilDe(p.curso_usil_id);
    const e = cursoExtDe(p.curso_externo_id);
    return {
        id: p.id,
        externo: e ? `${e.codigo || ''} ${e.nombre}` : `#${p.curso_externo_id}`,
        usil: u ? `${u.codigo || ''} ${u.nombre}` : `#${p.curso_usil_id}`,
        creditos: u ? Number(u.creditos) : 0,
        tipo: p.tipo_equivalencia,
        origen: p.origen,
    };
}));
const totalCreditos = computed(() => resumen.value.reduce((s, r) => s + r.creditos, 0));
const cobertura = computed(() => cursosExternosFinales.value.length
    ? Math.round((resumen.value.length / cursosExternosFinales.value.length) * 100) : 0);

const eliminarEquivalencia = (id) => {
    if(confirm('¿Eliminar esta equivalencia de la base de conocimiento?')) {
        router.delete(`/equivalencias/${id}`, { preserveScroll: true });
    }
};

</script>

<template>
    <div class="max-w-6xl">
        <!-- Encabezado -->
        <div class="mb-5">
            <h1 class="text-2xl font-semibold text-[#1F3864]">Pipeline IA: Base de Conocimiento</h1>
            <p class="mt-1 text-sm text-slate-500">Mapeo maestro automatizado: Sube una malla, extrae los cursos y empareja.</p>
        </div>

        <div class="mb-6 flex gap-6 border-b border-slate-200 text-sm font-medium">
            <Link href="/equivalencias" class="-mb-px border-b-2 border-transparent pb-2 text-slate-500 hover:text-[#2E75B6]">
                Catálogo de Mallas Externas
            </Link>
            <span class="-mb-px border-b-2 border-[#1F3864] pb-2 text-[#1F3864]">Procesar Malla</span>
        </div>

        <!-- Stepper Visual Pipeline -->
        <div class="mb-6 flex items-center justify-between rounded-xl border border-slate-200 bg-white px-6 py-5 shadow-sm overflow-x-auto">
            <template v-for="(p, i) in PASOS" :key="p.n">
                <div class="flex items-center gap-3 shrink-0"
                     :class="p.n <= paso ? 'opacity-100' : 'opacity-40'">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold"
                          :class="paso >= p.n ? 'bg-[#1F3864] text-white' : 'bg-slate-200 text-slate-500'">{{ p.n }}</span>
                    <span class="text-sm font-medium text-[#1F3864]">{{ p.label }}</span>
                </div>
                <div v-if="i < PASOS.length - 1" class="mx-3 h-px min-w-[20px] flex-1 bg-slate-200 shrink-0" :class="paso > p.n ? 'bg-[#1F3864]' : ''"></div>
            </template>
        </div>

        <!-- Alertas de Error -->
        <div v-if="errorExtraccion" class="mb-6 rounded-md bg-red-50 p-4 text-sm text-red-700 border border-red-200">
            ⚠ {{ errorExtraccion }}
        </div>

        <!-- Layout General -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Columna Izquierda: Visor del PDF / Info de Malla -->
            <div class="col-span-1 space-y-6">
                <!-- Cuando ya hay malla creada -->
                <div v-if="malla" class="rounded-xl border border-slate-200 bg-slate-50 p-6 shadow-sm">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">Malla Externa Oficial</h3>
                    <div class="space-y-4">
                        <div><label class="text-xs text-slate-500 block">Institución</label><p class="font-medium text-slate-800">{{ malla.institucion }}</p></div>
                        <div><label class="text-xs text-slate-500 block">Carrera</label><p class="font-medium text-slate-800">{{ malla.carrera }}</p></div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="text-xs text-slate-500 block">Año</label><p class="font-medium text-slate-800">{{ malla.anio }}</p></div>
                            <div><label class="text-xs text-slate-500 block">Versión</label><p class="font-medium text-slate-800">{{ malla.version || 'Única' }}</p></div>
                        </div>
                        <div class="pt-2">
                            <a v-if="malla.pdf_url" :href="malla.pdf_url" target="_blank"
                               class="inline-flex w-full justify-center items-center gap-2 rounded-md bg-[#2E75B6] px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-[#1F3864]">
                                📄 Ver Documento Oficial (PDF)
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Visor Temporal para la Recepción -->
                <div v-else class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden min-h-[400px] flex flex-col justify-center items-center bg-slate-50 p-6">
                    <template v-if="!formRecepcion.pdf">
                        <div class="text-4xl mb-3 opacity-20">📄</div>
                        <p class="text-sm text-slate-500 text-center">Sube un archivo PDF para previsualizarlo aquí.</p>
                    </template>
                    <template v-else>
                        <div class="text-4xl mb-3 text-[#2E75B6]">📑</div>
                        <p class="text-sm font-semibold text-slate-700 text-center">{{ archivoNombre }}</p>
                        <p class="text-xs text-slate-400 mt-2">Documento listo para ser procesado por la IA.</p>
                    </template>
                </div>
            </div>

            <!-- Columna Derecha: Contenido de los Pasos -->
            <div class="col-span-1 lg:col-span-2">

                <!-- PASO 1: Recepción -->
                <section v-if="paso === 1 && !malla" class="rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
                    <h2 class="mb-5 text-xl font-semibold text-[#1F3864]">Recepción de Documento Oficial</h2>
                    <p class="mb-6 text-sm text-slate-500">Ingresa los datos de la malla externa oficial y sube el PDF para extraer el catálogo automáticamente.</p>

                    <div class="space-y-5 max-w-lg">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Institución Externa <span class="text-red-500">*</span></label>
                                <select v-model="formRecepcion.institucion_id" required @change="formRecepcion.carrera_externa_id = ''"
                                       class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6]">
                                    <option value="">Selecciona la institución...</option>
                                    <option v-for="inst in instituciones" :key="inst.id" :value="inst.id">
                                        {{ inst.nombre }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Carrera Externa <span class="text-red-500">*</span></label>
                                <select v-model="formRecepcion.carrera_externa_id" required :disabled="!formRecepcion.institucion_id"
                                       class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] disabled:opacity-50 disabled:bg-slate-50">
                                    <option value="">Selecciona la carrera...</option>
                                    <option v-for="carrera in carrerasExternasOpts" :key="carrera.id" :value="carrera.id">
                                        {{ carrera.nombre }}
                                    </option>
                                </select>
                                <p v-if="formRecepcion.institucion_id && carrerasExternasOpts.length === 0" class="mt-1 text-xs text-red-500">Esta institución no tiene carreras registradas.</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Año de Malla <span class="text-red-500">*</span></label>
                                <input v-model="formRecepcion.anio" type="text" required
                                       class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6]" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Versión</label>
                                <input v-model="formRecepcion.version" type="text"
                                       class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6]" />
                            </div>
                        </div>
                        <div class="pt-2 border-t border-slate-100">
                            <label class="mb-2 block text-sm font-medium text-slate-700">Malla Oficial (Formato PDF) <span class="text-red-500">*</span></label>
                            <input type="file" accept="application/pdf" @change="onArchivo" required
                                   class="w-full text-sm text-slate-500 file:mr-4 file:rounded-md file:border-0 file:bg-[#1F3864]/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#1F3864] hover:file:bg-[#1F3864]/20" />
                        </div>
                    </div>

                    <div class="mt-10 flex justify-end">
                        <button @click="extraerConIA" :disabled="!formRecepcion.pdf || !formRecepcion.carrera_externa_id"
                                class="rounded-md bg-[#7030A0] px-6 py-3 text-sm font-bold text-white shadow hover:bg-purple-800 disabled:opacity-50">
                            ✨ Procesar con IA →
                        </button>
                    </div>
                </section>

                <!-- PASO 2: Extracción IA (Loading State) -->
                <section v-if="paso === 2" class="rounded-xl border border-purple-200 bg-purple-50 p-12 shadow-sm flex flex-col items-center justify-center min-h-[400px]">
                    <div class="w-16 h-16 border-4 border-purple-200 border-t-[#7030A0] rounded-full animate-spin mb-6"></div>
                    <h2 class="text-xl font-bold text-[#7030A0] mb-2">Analizando Documento...</h2>
                    <p class="text-sm text-purple-700 text-center max-w-md">La Inteligencia Artificial está leyendo el PDF y extrayendo los códigos, nombres y créditos del plan de estudios.</p>
                </section>

                <!-- PASO 3: Catálogo Extraído -->
                <section v-if="paso === 3" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-[#1F3864]">Revisión del Catálogo Extraído</h2>
                        <span class="text-xs font-medium text-white bg-green-600 px-2.5 py-1 rounded-full">{{ cursosExtraidos.length }} cursos detectados</span>
                    </div>
                    
                    <div class="mb-4 bg-slate-50 p-4 rounded-md border border-slate-200">
                        <p class="text-xs text-slate-500 uppercase tracking-wide">Info detectada del PDF</p>
                        <p class="font-medium text-slate-800 mt-1">{{ datosExtraidos.institucion?.nombre || 'Institución desconocida' }}</p>
                        <p class="text-sm text-slate-600">{{ datosExtraidos.carrera?.nombre || 'Carrera desconocida' }}</p>
                    </div>

                    <div class="max-h-[400px] overflow-y-auto border border-slate-200 rounded-lg">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Cód.</th>
                                    <th class="px-4 py-3 font-semibold">Nombre del Curso</th>
                                    <th class="px-4 py-3 font-semibold text-center">Cr.</th>
                                    <th class="px-4 py-3 font-semibold text-center">Ciclo</th>
                                    <th class="px-4 py-3 text-right">Quitar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="(c, idx) in cursosExtraidos" :key="idx" class="hover:bg-slate-50">
                                    <td class="px-4 py-2"><input v-model="c.codigo" class="w-16 border-slate-300 rounded text-xs py-1" /></td>
                                    <td class="px-4 py-2"><input v-model="c.nombre" class="w-full border-slate-300 rounded text-xs py-1" /></td>
                                    <td class="px-4 py-2 text-center"><input v-model="c.creditos" class="w-12 border-slate-300 rounded text-xs py-1 text-center" /></td>
                                    <td class="px-4 py-2 text-center"><input v-model="c.ciclo" class="w-12 border-slate-300 rounded text-xs py-1 text-center" /></td>
                                    <td class="px-4 py-2 text-right">
                                        <button @click="cursosExtraidos.splice(idx, 1)" class="text-red-500 hover:text-red-700 text-xs">🗑</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button @click="guardarMallaOficial" 
                                class="rounded-md bg-green-600 px-6 py-3 text-sm font-bold text-white shadow hover:bg-green-700">
                            ✓ Confirmar y Crear Malla
                        </button>
                    </div>
                </section>

                <!-- PASO 4: Mapeo Maestro -->
                <section v-if="paso === 4 && malla" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-[#1F3864]">Emparejamiento Maestro</h2>
                            <p class="text-sm text-slate-500 mt-1">Selecciona el destino y busca la equivalencia para los cursos extraídos.</p>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 text-sm">
                            <div class="flex gap-2 items-center mb-2">
                                <span class="font-medium text-slate-700">Target USIL:</span>
                                <select :value="sel.carrera_usil_id" @change="onCarreraUsil" class="rounded-md border-slate-300 text-xs py-1 focus:border-[#2E75B6]">
                                    <option value="">Seleccione carrera...</option>
                                    <option v-for="c in carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                </select>
                            </div>
                            <div class="flex gap-2 items-center">
                                <span class="font-medium text-slate-700">Año Malla:</span>
                                <select :value="anioUsilSeleccionado" @change="onAnioUsil" :disabled="!sel.carrera_usil_id" class="rounded-md border-slate-300 text-xs py-1 focus:border-[#2E75B6] disabled:opacity-50">
                                    <option value="">Año...</option>
                                    <option v-for="a in aniosMallaUsil" :key="a" :value="a">{{ a }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div v-if="contextoListo" class="space-y-4 max-h-[500px] overflow-y-auto pr-2 mt-6 border-t border-slate-200 pt-6">
                        <div v-for="ce in cursosExternosFinales" :key="ce.id" class="rounded-lg border border-slate-200 p-4 hover:border-[#2E75B6] transition-colors">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                                <div class="min-w-[250px] flex-1">
                                    <p class="text-sm font-semibold text-slate-800">{{ ce.nombre }}</p>
                                    <p class="text-xs text-slate-400 font-mono">{{ ce.codigo || 'S/N' }} • {{ Number(ce.creditos) }} cr.</p>
                                </div>
                                <div class="flex-1 w-full">
                                    <select v-model="seleccionUsil[ce.id]" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6]">
                                        <option value="">Buscar equivalencia USIL…</option>
                                        <option v-for="cu in cursosUsil" :key="cu.id" :value="cu.id" :disabled="yaEquivalente(ce.id, cu.id)">
                                            {{ cu.nombre }} {{ yaEquivalente(ce.id, cu.id) ? '(Ya mapeado)' : '' }}
                                        </option>
                                    </select>
                                </div>
                                <div class="flex gap-2">
                                    <button v-if="puedeEditar" @click="guardarMapeo(ce.id, seleccionUsil[ce.id])" :disabled="!seleccionUsil[ce.id]"
                                            class="rounded-md bg-[#2E75B6] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#1F3864] disabled:opacity-50">
                                        Guardar
                                    </button>
                                    <button v-if="puedeEditar" @click="pedirSugerencias(ce.id)" :disabled="cargandoIA === ce.id"
                                            class="rounded-md border border-[#7030A0] px-3 py-1.5 text-xs font-medium text-[#7030A0] hover:bg-purple-50 disabled:opacity-50" title="Usar Inteligencia Artificial">
                                        {{ cargandoIA === ce.id ? 'IA...' : '✨ Sugerir' }}
                                    </button>
                                </div>
                            </div>

                            <!-- Sugerencias IA -->
                            <div v-if="sugerencias[ce.id]" class="mt-3 space-y-2 rounded-md bg-purple-50 border border-purple-100 p-3">
                                <p v-if="!sugerencias[ce.id].length" class="text-xs text-slate-500">Sin sugerencias con confianza suficiente.</p>
                                <div v-for="(s, i) in sugerencias[ce.id]" :key="i" class="flex flex-col sm:flex-row sm:items-center justify-between text-xs gap-2">
                                    <div class="flex-1">
                                        <strong class="text-slate-800">{{ s.nombre }}</strong>
                                        <span class="ml-2 rounded bg-white font-medium border border-purple-200 px-1.5 py-0.5 text-[#7030A0]">{{ Number(s.confianza).toFixed(0) }}%</span>
                                        <p class="mt-1 text-slate-500 italic">"{{ s.justificacion }}"</p>
                                    </div>
                                    <button @click="guardarMapeo(ce.id, s.curso_usil_id, true, s.confianza)"
                                            class="shrink-0 rounded bg-[#7030A0] px-3 py-1.5 font-medium text-white shadow-sm hover:opacity-90">Aceptar Sugerencia</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-6 rounded-md bg-slate-50 border border-slate-200 p-8 text-center text-slate-500">
                        Selecciona el Target USIL en la parte superior para comenzar el emparejamiento.
                    </div>

                    <div class="mt-8 flex justify-end">
                        <button @click="irA(5)" :disabled="!contextoListo" class="rounded-md bg-[#1F3864] px-5 py-2.5 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">
                            Siguiente: Ver Diccionario →
                        </button>
                    </div>
                </section>

                <!-- PASO 5: Diccionario Generado -->
                <section v-if="paso === 5 && malla" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm min-h-[400px]">
                    <div class="mb-5 flex justify-between items-end">
                        <div>
                            <h2 class="text-lg font-semibold text-[#1F3864]">Diccionario Generado</h2>
                            <p class="text-sm text-slate-500 mt-1">Estas reglas ya están activas y alimentando la base de conocimiento para la IA.</p>
                        </div>
                        <div class="text-right">
                            <span class="block text-2xl font-bold text-[#1F3864]">{{ cobertura }}%</span>
                            <span class="text-xs text-slate-400 uppercase tracking-wide">Cobertura Malla</span>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3 font-semibold w-5/12">Curso Origen</th>
                                    <th class="px-4 py-3 font-semibold w-5/12">Curso USIL (Target)</th>
                                    <th class="px-4 py-3 font-semibold w-1/12 text-center">IA</th>
                                    <th class="px-4 py-3 font-semibold w-1/12 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(r, i) in resumen" :key="i" class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-700">{{ r.externo }}</td>
                                    <td class="px-4 py-3 font-medium text-[#1F3864]">{{ r.usil }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span v-if="r.origen === 'ia'" class="text-xs font-semibold text-[#7030A0]">Sí</span>
                                        <span v-else class="text-xs text-slate-400">Manual</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button @click="eliminarEquivalencia(r.id)" class="text-red-500 hover:text-red-700 font-medium text-xs">Quitar</button>
                                    </td>
                                </tr>
                                <tr v-if="!resumen.length">
                                    <td colspan="4" class="px-4 py-12 text-center text-slate-400">No has generado ninguna regla de equivalencia aún.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex items-center justify-between">
                        <button @click="irA(4)" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Seguir Mapeando</button>
                        <Link href="/equivalencias" class="rounded-md bg-[#1F3864] px-5 py-2.5 text-sm font-medium text-white hover:bg-[#2E75B6]">
                            ✓ Finalizar Pipeline
                        </Link>
                    </div>
                </section>

            </div>
        </div>
    </div>
</template>
