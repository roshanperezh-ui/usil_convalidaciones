<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import Autocomplete from '../../Components/Autocomplete.vue';

const props = defineProps({
    postulante: Object,
    poolUsil: Array,
    cursosOrigen: Array,
    documentos: Array,
    tieneMalla: Boolean,
    noConvalidar: String,
    ia: Object,
    edicion: { type: Object, default: null },   // simulación a editar (o null para nueva)
    simulacionesPrevias: Array,
});

const editando = !!props.edicion;
// Al editar una simulación con IA, se entra al pipeline para volver a elegir el mapeo.
const metodo = ref(props.edicion?.metodo ?? 'manual');   // 'manual' | 'ia'
const escala = ref(props.edicion?.escala_notas ?? '0-20');
const notaMinima = ref(props.edicion?.nota_minima ?? 11);
const universidadOrigen = ref(props.edicion?.universidad_origen ?? props.postulante.institucion ?? '');
const observaciones = ref(props.edicion?.observaciones ?? '');
const procesando = ref(false);
const mensaje = ref(null);              // { tipo, texto }

const TIPO_DOC = { certificado: 'Certificado de estudios', silabos: 'Sílabos', constancia: 'Constancia' };
const documentoId = ref(props.documentos?.[0]?.id ?? '');
const documentoPath = ref(null);
const archivo = ref(null);
const onArchivo = (e) => { archivo.value = e.target.files[0] ?? null; };

// ---------------------------------------------------------------- filas / catálogo
const filaBase = (c = {}) => ({
    curso_externo_id: c.curso_externo_id ?? null,
    curso_origen_nombre: c.nombre ?? '',
    nota_origen: c.nota ?? '',
    creditos_origen: c.creditos ?? '',
    ciclo_origen: c.ciclo ?? '',
    clasificacion: c.clasificacion ?? 'convalidable',
    curso_usil_id: '',
    confianza: null,
    origen: 'manual',
});
// Al editar: se cargan las filas de la simulación existente; si no, los cursos de origen.
const filas = reactive(
    props.edicion?.filas?.length
        ? props.edicion.filas.map((f) => ({
            ...filaBase({
                curso_externo_id: f.curso_externo_id,
                nombre: f.curso_origen_nombre,
                nota: f.nota_origen,
                creditos: f.creditos_origen,
                ciclo: f.ciclo_origen,
                clasificacion: f.clasificacion,
            }),
            curso_usil_id: f.curso_usil_id ?? '',
            confianza: f.confianza ?? null,
        }))
        : (props.cursosOrigen ?? []).map(filaBase)
);

// Opciones USIL por fila: un curso ya asignado en otra fila NO aparece (regla 1‑a‑1).
// Para volver a usarlo, primero debe desasignarse de donde está.
const usilOptionsPara = (fila) => {
    const usadosOtros = new Set(
        filas.filter((f) => f !== fila && f.curso_usil_id).map((f) => Number(f.curso_usil_id))
    );
    return [
        { value: '', label: props.noConvalidar },
        ...props.poolUsil
            .filter((p) => !usadosOtros.has(p.id) || Number(fila.curso_usil_id) === p.id)
            .map((p) => ({ value: p.id, label: p.label })),
    ];
};
const creditosPorId = computed(() => Object.fromEntries(props.poolUsil.map((p) => [p.id, p.creditos])));

const duplicados = computed(() => {
    const cont = {};
    filas.forEach((f) => { if (f.curso_usil_id) cont[f.curso_usil_id] = (cont[f.curso_usil_id] || 0) + 1; });
    return Object.keys(cont).filter((k) => cont[k] > 1).map(Number);
});
const esDuplicado = (f) => f.curso_usil_id && duplicados.value.includes(Number(f.curso_usil_id));

const resumen = computed(() => {
    const conv = filas.filter((f) => f.curso_usil_id && f.clasificacion === 'convalidable');
    const creditos = conv.reduce((s, f) => s + (Number(creditosPorId.value[f.curso_usil_id]) || 0), 0);
    return { total: filas.length, convalidados: conv.length, creditos };
});

// Cursos convalidables que quedaron SIN un curso USIL asignado (a revisar antes de guardar).
const filaSinAsignar = (f) => f.clasificacion === 'convalidable' && !f.curso_usil_id;
const sinAsignar = computed(() => filas.filter(filaSinAsignar).length);

// Clasificación de la preconvalidación en 3 grupos (Etapa 6).
const usilPorId = computed(() => Object.fromEntries(props.poolUsil.map((p) => [p.id, p.curso || p.label])));
const convalidadosLista = computed(() => filas.filter((f) => f.clasificacion === 'convalidable' && f.curso_usil_id));
const noConvalidadosLista = computed(() => filas.filter((f) => f.clasificacion === 'no_convalidable' || filaSinAsignar(f)));
const desaprobadosLista = computed(() => filas.filter((f) => f.clasificacion === 'desaprobado'));
const tabPreconv = ref('conv');   // 'conv' | 'no' | 'desap'

const agregarFila = () => filas.push(filaBase());
const quitarFila = (i) => filas.splice(i, 1);
const limpiarFilas = () => { filas.splice(0, filas.length); };

// Etiqueta y color del "Tipo" (como en el módulo original: Convalidable / No convalidable (auto)).
const tipoLabel = (c) => ({ convalidable: 'Convalidable', no_convalidable: 'No convalidable (auto)', desaprobado: 'Desaprobado' }[c] ?? c);
const tipoBadge = (c) => ({
    convalidable: 'bg-green-50 text-green-700 ring-green-200',
    no_convalidable: 'bg-amber-50 text-amber-700 ring-amber-200',
    desaprobado: 'bg-red-50 text-red-700 ring-red-200',
}[c] ?? 'bg-slate-100 text-slate-500 ring-slate-200');

// ---------------------------------------------------------------- sugerencias de mapeo
const nombresConvalidables = () =>
    filas.filter((f) => f.clasificacion === 'convalidable' && f.curso_origen_nombre.trim())
         .map((f) => f.curso_origen_nombre.trim());

// Aplica el mapeo garantizando la regla 1‑a‑1: un curso USIL no se asigna dos veces.
// Los cursos de origen sin sugerencia única quedan SIN convalidar (columna vacía).
const aplicarMapa = (mapa) => {
    const usados = new Set();
    filas.forEach((f) => {
        if (f.clasificacion !== 'convalidable') return;
        const s = mapa[f.curso_origen_nombre.trim()];
        const id = s?.curso_usil_id ? Number(s.curso_usil_id) : null;
        if (id && !usados.has(id)) {
            f.curso_usil_id = id;
            f.confianza = s.confianza ?? null;
            usados.add(id);
        } else {
            // Sin sugerencia o el curso USIL ya fue tomado por otra fila → queda vacío.
            f.curso_usil_id = '';
            f.confianza = null;
        }
    });
};

// Selección manual desde el combobox (limpia la afinidad, ya no es una sugerencia).
const elegirUsil = (fila, valor) => {
    fila.curso_usil_id = valor === '' || valor == null ? '' : Number(valor);
    fila.confianza = null;
};

const sugerir = async (conIA) => {
    if (!props.postulante.carrera_destino_id) { mensaje.value = { tipo: 'error', texto: 'El postulante no tiene carrera destino.' }; return; }
    const cursos = nombresConvalidables();
    if (!cursos.length) { mensaje.value = { tipo: 'error', texto: 'No hay cursos convalidables para mapear.' }; return; }
    procesando.value = true; mensaje.value = null;
    try {
        const url = conIA ? '/simulaciones/sugerir-ia' : '/simulaciones/sugerir-similitud';
        const { data } = await window.axios.post(url, { carrera_usil_id: props.postulante.carrera_destino_id, cursos });
        aplicarMapa(data.mapa || {});
        filas.forEach((f) => { if (f.confianza) f.origen = conIA ? 'ia' : 'similitud'; });
        mensaje.value = { tipo: 'ok', texto: conIA ? 'Sugerencias de IA aplicadas.' : 'Mapeo por similitud aplicado.' };
    } catch (e) {
        mensaje.value = { tipo: 'error', texto: e.response?.data?.message || 'No se pudo generar la sugerencia.' };
    } finally { procesando.value = false; }
};

// ================================================================ PIPELINE CON IA
const PASOS_IA = [
    { n: 1, label: 'Recepción', icon: '📥' },
    { n: 2, label: 'Validación documental', icon: '📄' },
    { n: 3, label: 'Extracción', icon: '🔍' },
    { n: 4, label: 'Aprobados', icon: '✅' },
    { n: 5, label: 'Mapeo USIL', icon: '🔗' },
    { n: 6, label: 'Preconvalidación', icon: '📜' },
];
// Al editar una simulación IA, se abre directamente en la etapa de Mapeo para re-elegir.
const pasoIA = ref(editando && props.edicion?.metodo === 'ia' ? 5 : 1);
const expediente = ref(editando ? `EXP-EDIT-${props.edicion?.id ?? ''}` : '');
const fechaRecepcion = ref('');
const extraccion = ref(null);           // { estudiante, institucion, aprobados, desaprobados, no_convalidables }

const docSeleccionado = computed(() => props.documentos?.find((d) => d.id === documentoId.value) ?? null);

const parseNota = (v) => {
    const n = parseFloat(String(v ?? '').replace(',', '.'));
    return Number.isNaN(n) ? null : n;
};
const aprobadosValidados = computed(() => (extraccion.value?.aprobados ?? [])
    .filter((c) => { const n = parseNota(c.nota); return n !== null && n >= Number(notaMinima.value); }));
const aprobadosFuera = computed(() => (extraccion.value?.aprobados ?? [])
    .filter((c) => { const n = parseNota(c.nota); return n === null || n < Number(notaMinima.value); }));

const iniciarPipeline = () => {
    const f = new Date();
    const pad = (x) => String(x).padStart(2, '0');
    const stamp = `${f.getFullYear()}${pad(f.getMonth() + 1)}${pad(f.getDate())}-${pad(f.getHours())}${pad(f.getMinutes())}${pad(f.getSeconds())}`;
    expediente.value = `EXP-${stamp}`;
    fechaRecepcion.value = `${pad(f.getDate())}/${pad(f.getMonth() + 1)}/${f.getFullYear()} ${pad(f.getHours())}:${pad(f.getMinutes())}`;
    pasoIA.value = 2;
};

const ejecutarExtraccion = async () => {
    if (!documentoId.value && !archivo.value) { mensaje.value = { tipo: 'error', texto: 'Selecciona un documento.' }; return; }
    procesando.value = true; mensaje.value = null;
    try {
        let data;
        if (documentoId.value && !archivo.value) {
            ({ data } = await window.axios.post('/simulaciones/extraer-ia', {
                documento_id: documentoId.value,
                carrera_externa_id: props.postulante.carrera_externa_id,
            }));
        } else {
            const fd = new FormData();
            fd.append('documento', archivo.value);
            if (props.postulante.carrera_externa_id) fd.append('carrera_externa_id', props.postulante.carrera_externa_id);
            ({ data } = await window.axios.post('/simulaciones/extraer-ia', fd, { headers: { 'Content-Type': 'multipart/form-data' } }));
        }
        extraccion.value = data;
        documentoPath.value = data.documento_path ?? null;
        if (data.institucion?.universidad) universidadOrigen.value = data.institucion.universidad;
        mensaje.value = { tipo: 'ok', texto: `Extraídos ${data.aprobados?.length || 0} aprobados · ${data.desaprobados?.length || 0} desaprobados · ${data.no_convalidables?.length || 0} no convalidables.` };
    } catch (e) {
        mensaje.value = { tipo: 'error', texto: e.response?.data?.message || 'No se pudo procesar el documento.' };
    } finally { procesando.value = false; }
};

// Construye la tabla de mapeo a partir de la extracción validada (al entrar a la etapa 5).
const construirFilasMapeo = () => {
    limpiarFilas();
    aprobadosValidados.value.forEach((c) => filas.push(filaBase({ ...c, clasificacion: 'convalidable' })));
    (extraccion.value?.no_convalidables ?? []).forEach((c) => filas.push(filaBase({ ...c, clasificacion: 'no_convalidable' })));
    (extraccion.value?.desaprobados ?? []).forEach((c) => filas.push(filaBase({ ...c, clasificacion: 'desaprobado' })));
    filas.forEach((f) => { f.origen = 'ia'; });
};

const puedeAvanzarIA = computed(() => {
    if (pasoIA.value === 2) return !!(documentoId.value || archivo.value || filas.length);
    if (pasoIA.value === 3) return !!(extraccion.value?.aprobados?.length || filas.length);
    if (pasoIA.value === 4) return aprobadosValidados.value.length > 0 || filas.length > 0;
    if (pasoIA.value === 5) return duplicados.value.length === 0;
    return true;
});

const siguienteIA = async () => {
    if (!puedeAvanzarIA.value) return;
    if (pasoIA.value === 4) {
        // Se prepara el mapeo con la columna VACÍA (sin sugerencia automática).
        // Los cursos sugeridos aparecen solo al pulsar «Sugerir con IA» o «Re-sugerir por similitud».
        if (extraccion.value) {
            construirFilasMapeo();
        }
        pasoIA.value = 5;
        return;
    }
    pasoIA.value = Math.min(6, pasoIA.value + 1);
};
const anteriorIA = () => { pasoIA.value = Math.max(1, pasoIA.value - 1); };

// Al cambiar a modo IA sin haber iniciado, arrancar en Recepción.
watch(metodo, (m) => { if (m === 'ia') { pasoIA.value = 1; mensaje.value = null; } });

// ---------------------------------------------------------------- guardar
const guardadoId = ref(null);   // id de la preconvalidación guardada (habilita descargas)

// Convierte créditos (que la IA puede devolver como "3,000", "3.0", "4") a número o null.
const aNumero = (v) => {
    if (v === '' || v == null) return null;
    const n = parseFloat(String(v).replace(/[^\d.,]/g, '').replace(',', '.'));
    return Number.isNaN(n) ? null : n;
};

const guardar = () => {
    if (!props.tieneMalla) return;
    if (duplicados.value.length) { mensaje.value = { tipo: 'error', texto: 'Corrige los cursos USIL duplicados (regla 1 a 1).' }; return; }
    const payload = {
        postulante_id: props.postulante.id,
        carrera_usil_id: props.postulante.carrera_destino_id,
        metodo: metodo.value,
        documento_path: documentoPath.value,
        universidad_origen: universidadOrigen.value,
        escala_notas: escala.value,
        nota_minima: notaMinima.value,
        observaciones: [expediente.value ? `Expediente ${expediente.value}` : '', observaciones.value].filter(Boolean).join(' — '),
        filas: filas
            .filter((f) => f.curso_origen_nombre.trim())
            .map((f) => ({
                curso_externo_id: f.curso_externo_id ?? null,
                curso_origen_nombre: String(f.curso_origen_nombre).slice(0, 200),
                nota_origen: f.nota_origen == null || f.nota_origen === '' ? null : String(f.nota_origen).slice(0, 20),
                creditos_origen: aNumero(f.creditos_origen),
                ciclo_origen: f.ciclo_origen == null || f.ciclo_origen === '' ? null : String(f.ciclo_origen).slice(0, 30),
                clasificacion: f.clasificacion,
                curso_usil_id: f.curso_usil_id || null,
                confianza: aNumero(f.confianza),
                origen: f.origen || (metodo.value === 'ia' ? 'ia' : 'manual'),
            })),
    };
    procesando.value = true;
    mensaje.value = null;
    const peticion = editando
        ? window.axios.put(`/simulaciones/${props.edicion.id}`, payload)
        : window.axios.post('/simulaciones', payload);
    peticion
        .then(({ data }) => {
            guardadoId.value = data.id;
            mensaje.value = { tipo: 'ok', texto: `Preconvalidación ${editando ? 'actualizada' : 'guardada'}. Ya puedes descargar el documento (PDF o Excel).` };
        })
        .catch((e) => {
            const errs = e.response?.data?.errors;
            mensaje.value = { tipo: 'error', texto: errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'No se pudo guardar. Revisa los datos.') };
        })
        .finally(() => { procesando.value = false; });
};

// Descarga robusta: enlace temporal en la misma pestaña (evita bloqueo de pop-ups y pestañas en blanco).
const descargarArchivo = (url) => {
    const a = document.createElement('a');
    a.href = url;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
};
const descargarPdf = () => guardadoId.value && descargarArchivo(`/simulaciones/${guardadoId.value}/pdf`);
const descargarExcel = () => guardadoId.value && descargarArchivo(`/simulaciones/${guardadoId.value}/excel`);

// Elimina una simulación previa registrando el motivo en la base de datos.
const eliminarSimulacion = (s) => {
    const motivo = window.prompt(`Motivo para eliminar la simulación #${s.id} (quedará registrado en la base de datos):`);
    if (motivo === null) return;
    if (motivo.trim().length < 5) { alert('El motivo debe tener al menos 5 caracteres.'); return; }
    router.delete(`/simulaciones/${s.id}`, { data: { motivo: motivo.trim() }, preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <!-- Encabezado -->
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link href="/simulaciones" class="text-xs font-medium uppercase tracking-wide text-slate-400 hover:text-[#2E75B6]">← Simulaciones</Link>
                <h1 class="mt-1 text-2xl font-semibold text-[#1F3864]">{{ editando ? `Editar simulación #${edicion.id}` : 'Simular convalidación' }}</h1>
                <p class="mt-1 text-sm text-slate-500"><span class="font-medium text-slate-700">{{ postulante.nombre }}</span> · {{ postulante.documento }}</p>
                <p class="text-xs text-slate-400">
                    Origen: {{ postulante.institucion || '—' }} · {{ postulante.carrera_externa || '—' }}
                    &nbsp;→&nbsp; Destino: <span class="font-medium text-slate-600">{{ postulante.carrera_destino || '— sin carrera —' }}</span>
                </p>
            </div>
            <button v-if="metodo === 'manual'" @click="guardar" :disabled="!tieneMalla || procesando || guardadoId"
                    class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">
                {{ guardadoId ? '✓ Guardada' : (procesando ? 'Guardando…' : 'Guardar simulación') }}</button>
        </div>

        <div v-if="!postulante.carrera_destino_id" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            El postulante no tiene carrera destino USIL. Edítalo antes de simular.
        </div>
        <div v-else-if="!tieneMalla" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            La carrera destino no tiene un plan de estudios (malla) cargado. Carga la malla para poder mapear cursos.
        </div>
        <div v-else-if="!poolUsil.length" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            El plan de estudios de <strong>{{ postulante.carrera_destino }}</strong> no tiene cursos cargados, por lo que no hay a qué convalidar. Carga los cursos de la malla en <strong>Estructura → Mallas</strong>.
        </div>

        <div v-if="mensaje" :class="mensaje.tipo === 'ok' ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'"
             class="mb-4 rounded-lg border px-4 py-2 text-sm">{{ mensaje.texto }}</div>

        <!-- Panel de descargas: aparece al guardar la preconvalidación -->
        <div v-if="guardadoId" class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4">
            <p class="mb-3 text-sm font-medium text-green-800">✓ Preconvalidación guardada (expediente #{{ guardadoId }}). Descarga el documento:</p>
            <div class="flex flex-wrap gap-3">
                <button @click="descargarPdf" class="inline-flex items-center gap-2 rounded-md border border-[#2E75B6] bg-white px-4 py-2 text-sm font-medium text-[#2E75B6] hover:bg-blue-50">
                    ⬇️ Descargar preconvalidación (PDF)
                </button>
                <button @click="descargarExcel" class="inline-flex items-center gap-2 rounded-md border border-green-600 bg-white px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-100">
                    ⬇️ Descargar preconvalidación (Excel)
                </button>
                <Link :href="`/simulaciones/${guardadoId}`" class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium text-slate-600 hover:bg-white">Ver detalle</Link>
                <Link href="/simulaciones" class="inline-flex items-center rounded-md px-4 py-2 text-sm font-medium text-slate-600 hover:bg-white">Ir a simulaciones</Link>
            </div>
        </div>

        <!-- Selector de método -->
        <div class="mb-4 inline-flex rounded-lg border border-slate-200 bg-white p-1 shadow-sm">
            <button @click="metodo = 'manual'" :class="metodo === 'manual' ? 'bg-[#1F3864] text-white' : 'text-slate-600 hover:bg-slate-50'" class="rounded-md px-4 py-1.5 text-sm font-medium">Manual</button>
            <button @click="metodo = 'ia'" :class="metodo === 'ia' ? 'bg-violet-600 text-white' : 'text-slate-600 hover:bg-slate-50'" class="rounded-md px-4 py-1.5 text-sm font-medium">✨ Con IA</button>
        </div>

        <!-- ============================= MODO MANUAL ============================= -->
        <template v-if="metodo === 'manual'">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Escala</label>
                        <select v-model="escala" class="rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]">
                            <option value="0-20">0 - 20</option><option value="0-100">0 - 100</option><option value="0-5">0 - 5</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Universidad de origen</label>
                        <input v-model="universidadOrigen" type="text" class="w-64 rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="sugerir(false)" :disabled="procesando" class="rounded-md border border-[#2E75B6] px-3 py-2 text-sm font-medium text-[#2E75B6] hover:bg-blue-50 disabled:opacity-50">Sugerir por similitud</button>
                    <button @click="sugerir(true)" :disabled="procesando || !ia?.disponible" :title="ia?.disponible ? '' : 'Configura la API key'" class="rounded-md bg-violet-600 px-3 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50">✨ Sugerir con IA</button>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div><table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                        <th class="px-3 py-2.5 font-semibold">Curso de origen</th><th class="w-20 px-3 py-2.5 font-semibold">Nota</th>
                        <th class="w-20 px-3 py-2.5 font-semibold">Créd.</th><th class="w-40 px-3 py-2.5 font-semibold">Clasificación</th>
                        <th class="px-3 py-2.5 font-semibold">Convalidar con (USIL)</th><th class="w-16 px-3 py-2.5 text-center font-semibold">%</th><th class="w-12 px-3 py-2.5"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(f, i) in filas" :key="i" :class="filaSinAsignar(f) ? 'bg-amber-50/60' : 'hover:bg-slate-50/70'">
                            <td class="px-3 py-2"><input v-model="f.curso_origen_nombre" type="text" placeholder="Nombre del curso" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" /></td>
                            <td class="px-3 py-2"><input v-model="f.nota_origen" type="text" class="w-full rounded-md border-slate-300 text-sm" /></td>
                            <td class="px-3 py-2"><input v-model="f.creditos_origen" type="number" step="0.5" class="w-full rounded-md border-slate-300 text-sm" /></td>
                            <td class="px-3 py-2"><select v-model="f.clasificacion" class="w-full rounded-md border-slate-300 text-sm">
                                <option value="convalidable">Convalidable</option><option value="desaprobado">Desaprobado</option><option value="no_convalidable">No convalidable</option></select></td>
                            <td class="px-3 py-2">
                                <Autocomplete :model-value="f.curso_usil_id" @update:modelValue="(v) => elegirUsil(f, v)"
                                              :options="usilOptionsPara(f)" :disabled="f.clasificacion !== 'convalidable'"
                                              :placeholder="f.clasificacion === 'convalidable' ? 'Elegir curso a convalidar…' : '—'" />
                            </td>
                            <td class="px-3 py-2 text-center text-xs text-slate-500">{{ f.confianza ? Number(f.confianza).toFixed(0) : '—' }}</td>
                            <td class="px-3 py-2 text-center"><button @click="quitarFila(i)" class="text-slate-400 hover:text-red-600" title="Quitar">✕</button></td>
                        </tr>
                        <tr v-if="!filas.length"><td colspan="7" class="px-4 py-8 text-center text-slate-400">Sin cursos. Usa «Agregar curso».</td></tr>
                    </tbody>
                </table></div>
                <div class="flex items-center justify-between border-t border-slate-200 px-4 py-3">
                    <button @click="agregarFila" class="text-sm font-medium text-[#2E75B6] hover:underline">+ Agregar curso</button>
                    <div class="flex flex-wrap items-center gap-4 text-sm">
                        <span class="text-slate-500">Cursos: <span class="font-medium text-slate-700">{{ resumen.total }}</span></span>
                        <span class="text-slate-500">Convalidados: <span class="font-medium text-green-600">{{ resumen.convalidados }}</span></span>
                        <span v-if="sinAsignar" class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">{{ sinAsignar }} sin asignar</span>
                        <span class="text-slate-500">Créditos: <span class="font-semibold text-[#1F3864]">{{ resumen.creditos.toFixed(1) }}</span></span>
                    </div>
                </div>
            </div>
            <p v-if="duplicados.length" class="mt-2 text-xs text-red-600">⚠️ Hay cursos USIL asignados más de una vez. La convalidación es 1 a 1.</p>
            <div class="mt-4"><label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                <textarea v-model="observaciones" rows="2" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]"></textarea></div>
            <div class="mt-6 flex gap-3">
                <button @click="guardar" :disabled="!tieneMalla || procesando || guardadoId" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">
                    {{ guardadoId ? '✓ Guardada' : (procesando ? 'Guardando…' : 'Guardar simulación') }}</button>
                <Link href="/simulaciones" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</Link>
            </div>
        </template>

        <!-- ============================= MODO IA (pipeline 6 etapas) ============================= -->
        <template v-else>
            <p v-if="!ia?.disponible" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                IA inactiva: ve a <strong>Configuración</strong> y define la API key para ejecutar el pipeline. También puedes usar el modo <strong>Manual</strong>.
            </p>

            <!-- Indicador de etapas -->
            <div class="mb-5 grid grid-cols-3 gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm sm:grid-cols-6">
                <div v-for="p in PASOS_IA" :key="p.n" class="text-center">
                    <div :class="p.n === pasoIA ? 'border-2 border-[#2E75B6] text-[#1F3864]' : (p.n < pasoIA ? 'text-green-600' : 'text-slate-400')" class="mx-auto rounded-lg px-2 py-2">
                        <div class="text-lg leading-none">{{ p.icon }}</div>
                        <div class="mt-1 text-xs font-semibold">{{ p.n }}</div>
                        <div class="text-[11px] leading-tight">{{ p.label }}</div>
                        <div v-if="p.n < pasoIA" class="text-xs">✓</div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <!-- Etapa 1 · Recepción -->
                <div v-if="pasoIA === 1">
                    <h2 class="text-lg font-semibold text-[#1F3864]">📥 Etapa 1 · Recepción del expediente</h2>
                    <p class="mb-4 text-sm text-slate-500">Se registra el expediente para el postulante. La carrera destino proviene de su ficha.</p>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Postulante</dt><dd class="font-medium text-slate-700">{{ postulante.nombre }}</dd></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Carrera USIL destino</dt><dd class="font-medium text-slate-700">{{ postulante.carrera_destino || '—' }}</dd></div>
                    </dl>
                    <div class="mt-6 flex justify-end">
                        <button @click="iniciarPipeline" :disabled="!tieneMalla" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">Siguiente →</button>
                    </div>
                </div>

                <!-- Etapa 2 · Validación documental -->
                <div v-else-if="pasoIA === 2">
                    <h2 class="text-lg font-semibold text-[#1F3864]">📄 Etapa 2 · Validación documental</h2>
                    <p class="mb-4 text-sm text-slate-500">Expediente <strong>{{ expediente }}</strong> · Recepción: {{ fechaRecepcion }}</p>

                    <div v-if="documentos?.length">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Documento del postulante (ya cargado en su expediente)</label>
                        <select v-model="documentoId" class="w-full max-w-lg rounded-md border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500">
                            <option v-for="d in documentos" :key="d.id" :value="d.id">{{ TIPO_DOC[d.tipo] || d.tipo }} — {{ d.nombre }}</option>
                        </select>
                        <div v-if="docSeleccionado" class="mt-3 flex flex-wrap gap-4 text-sm text-green-700">
                            <span>✅ Documento en expediente</span><span>✅ Trazabilidad activa</span><span>✅ Formato aceptado</span>
                        </div>
                    </div>
                    <div v-else class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                        <p class="mb-2">El postulante no tiene documentos cargados. Sube uno (o cárgalo en su ficha):</p>
                        <input type="file" accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.txt,.csv" @change="onArchivo" class="text-sm text-slate-600" />
                    </div>

                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="siguienteIA" :disabled="!puedeAvanzarIA" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">Siguiente →</button>
                    </div>
                </div>

                <!-- Etapa 3 · Extracción -->
                <div v-else-if="pasoIA === 3">
                    <h2 class="text-lg font-semibold text-[#1F3864]">🔍 Etapa 3 · Extracción de cursos (OCR + IA)</h2>
                    <p class="mb-4 text-sm text-slate-500">Expediente <strong>{{ expediente }}</strong> · Documento: {{ docSeleccionado?.nombre || archivo?.name }}</p>

                    <button @click="ejecutarExtraccion" :disabled="!ia?.disponible || procesando" class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50">
                        {{ procesando ? 'Procesando con IA…' : (extraccion ? '🔁 Re-ejecutar extracción' : '🤖 Ejecutar extracción con IA') }}
                    </button>

                    <div v-if="extraccion" class="mt-5">
                        <div class="mb-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Estudiante (detectado)</dt><dd class="font-medium text-slate-700">{{ extraccion.estudiante?.nombre || '—' }}</dd></div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Universidad</dt><dd class="font-medium text-slate-700">{{ extraccion.institucion?.universidad || '—' }}</dd></div>
                            <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Carrera (doc)</dt><dd class="font-medium text-slate-700">{{ extraccion.estudiante?.carrera || '—' }}</dd></div>
                        </div>
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-green-600">{{ extraccion.aprobados?.length || 0 }}</p><p class="text-xs text-slate-500">Aprobados</p></div>
                            <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-red-500">{{ extraccion.desaprobados?.length || 0 }}</p><p class="text-xs text-slate-500">Desaprobados</p></div>
                            <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-amber-500">{{ extraccion.no_convalidables?.length || 0 }}</p><p class="text-xs text-slate-500">No convalidables</p></div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="siguienteIA" :disabled="!puedeAvanzarIA" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">Siguiente →</button>
                    </div>
                </div>

                <!-- Etapa 4 · Aprobados -->
                <div v-else-if="pasoIA === 4">
                    <h2 class="text-lg font-semibold text-[#1F3864]">✅ Etapa 4 · Validación de aprobados</h2>
                    <p class="mb-4 text-sm text-slate-500">Ajusta la escala y la nota mínima. Los cursos que no cumplen quedan fuera del mapeo.</p>
                    <div class="mb-4 flex flex-wrap items-end gap-3">
                        <div><label class="mb-1 block text-xs font-medium text-slate-500">Escala</label>
                            <select v-model="escala" class="rounded-md border-slate-300 text-sm"><option value="0-20">0 - 20</option><option value="0-100">0 - 100</option><option value="0-5">0 - 5</option></select></div>
                        <div><label class="mb-1 block text-xs font-medium text-slate-500">Nota mínima</label>
                            <input v-model="notaMinima" type="number" step="0.1" class="w-28 rounded-md border-slate-300 text-sm" /></div>
                        <div class="flex gap-3 text-sm">
                            <span class="rounded-lg bg-green-50 px-3 py-2 text-green-700">Cumplen: <strong>{{ aprobadosValidados.length }}</strong></span>
                            <span class="rounded-lg bg-slate-100 px-3 py-2 text-slate-500">Fuera: <strong>{{ aprobadosFuera.length }}</strong></span>
                        </div>
                    </div>
                    <div class="overflow-hidden rounded-lg border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-2 font-semibold">Curso</th><th class="px-4 py-2 font-semibold">Nota</th><th class="px-4 py-2 font-semibold">Créditos</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(c, i) in aprobadosValidados" :key="i"><td class="px-4 py-2 text-slate-700">{{ c.nombre }}</td><td class="px-4 py-2 text-slate-600">{{ c.nota }}</td><td class="px-4 py-2 text-slate-600">{{ c.creditos || '—' }}</td></tr>
                                <tr v-if="!aprobadosValidados.length"><td colspan="3" class="px-4 py-6 text-center text-slate-400">Ningún curso cumple la nota mínima.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="siguienteIA" :disabled="!puedeAvanzarIA" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">Siguiente →</button>
                    </div>
                </div>

                <!-- Etapa 5 · Mapeo USIL (réplica del módulo original) -->
                <div v-else-if="pasoIA === 5">
                    <h2 class="text-lg font-semibold text-[#1F3864]">🔗 Etapa 5 · Mapeo USIL — {{ postulante.carrera_destino }}</h2>
                    <p class="mb-3 text-sm text-slate-500">Cada curso aprobado se mapea a un curso USIL (incluye electivos). Regla 1‑a‑1: cada curso USIL solo puede usarse una vez.</p>

                    <div class="mb-3 flex flex-wrap items-center gap-3">
                        <button @click="sugerir(true)" :disabled="procesando || !ia?.disponible" :title="ia?.disponible ? '' : 'Configura la API key'"
                                class="rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50">✨ Sugerir con IA</button>
                        <button @click="sugerir(false)" :disabled="procesando"
                                class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50">↻ Re-sugerir por similitud</button>
                        <span class="text-xs text-slate-500">La columna inicia vacía; elige el curso manualmente o usa <strong>✨ Sugerir con IA</strong> ({{ ia?.proveedor === 'openai' ? 'OpenAI' : 'Gemini' }}) para el mapeo semántico.</span>
                    </div>

                    <div class="rounded-xl border border-slate-200">
                        <div><table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                                <th class="px-3 py-2.5 font-semibold">Curso origen</th>
                                <th class="w-16 px-3 py-2.5 font-semibold">Nota</th>
                                <th class="w-20 px-3 py-2.5 font-semibold">Créditos</th>
                                <th class="w-24 px-3 py-2.5 font-semibold">Ciclo</th>
                                <th class="w-44 px-3 py-2.5 font-semibold">Tipo</th>
                                <th class="px-3 py-2.5 font-semibold">Convalidar con USIL</th>
                            </tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(f, i) in filas" :key="i" :class="filaSinAsignar(f) ? 'bg-amber-50/60' : 'hover:bg-slate-50/70'">
                                    <td class="px-3 py-2 text-slate-700">{{ f.curso_origen_nombre }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ f.nota_origen || '—' }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ f.creditos_origen !== '' && f.creditos_origen != null ? f.creditos_origen : '—' }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ f.ciclo_origen || '—' }}</td>
                                    <td class="px-3 py-2">
                                        <span :class="tipoBadge(f.clasificacion)" class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset">{{ tipoLabel(f.clasificacion) }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <Autocomplete :model-value="f.curso_usil_id" @update:modelValue="(v) => elegirUsil(f, v)"
                                                      :options="usilOptionsPara(f)" :disabled="f.clasificacion !== 'convalidable'"
                                                      :placeholder="f.clasificacion === 'convalidable' ? 'Elegir curso a convalidar…' : '—'" />
                                        <span v-if="f.confianza" class="mt-0.5 block text-[11px] text-slate-400">afinidad {{ Number(f.confianza).toFixed(0) }}%</span>
                                    </td>
                                </tr>
                                <tr v-if="!filas.length"><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin cursos para mapear.</td></tr>
                            </tbody>
                        </table></div>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                        <span class="text-green-600">🎯 {{ resumen.convalidados }} de {{ filas.length }} cursos mapeados (1 a 1)</span>
                        <span v-if="sinAsignar" class="rounded-full bg-amber-50 px-2.5 py-0.5 font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                            {{ sinAsignar }} convalidable(s) sin asignar
                        </span>
                        <span v-if="duplicados.length" class="text-red-600">⚠️ Corrige los duplicados (regla 1 a 1).</span>
                    </div>

                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="siguienteIA" :disabled="!puedeAvanzarIA" class="rounded-lg bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">Siguiente →</button>
                    </div>
                </div>

                <!-- Etapa 6 · Preconvalidación -->
                <div v-else-if="pasoIA === 6">
                    <h2 class="text-lg font-semibold text-[#1F3864]">📜 Etapa 6 · Preconvalidación</h2>
                    <p class="mb-4 text-sm text-slate-500">Revisa el resumen del expediente y guarda la preconvalidación. Podrás descargar el documento (PDF/Excel) al finalizar.</p>
                    <div class="mb-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Expediente</dt><dd class="font-medium text-slate-700">{{ expediente }}</dd></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Solicitante</dt><dd class="font-medium text-slate-700">{{ postulante.nombre }}</dd></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Universidad de origen</dt><dd class="font-medium text-slate-700">{{ universidadOrigen || '—' }}</dd></div>
                        <div class="rounded-lg bg-slate-50 px-4 py-3"><dt class="text-xs text-slate-400">Carrera USIL destino</dt><dd class="font-medium text-slate-700">{{ postulante.carrera_destino }}</dd></div>
                    </div>
                    <div class="mb-4 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-[#1F3864]">{{ resumen.total }}</p><p class="text-xs text-slate-500">Cursos</p></div>
                        <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-green-600">{{ resumen.convalidados }}</p><p class="text-xs text-slate-500">Convalidados</p></div>
                        <div class="rounded-xl border border-slate-200 p-3"><p class="text-2xl font-bold text-[#2E75B6]">{{ resumen.creditos.toFixed(1) }}</p><p class="text-xs text-slate-500">Créditos reconocidos</p></div>
                    </div>

                    <!-- Pestañas de clasificación de cursos -->
                    <div class="mb-3 inline-flex flex-wrap gap-1 rounded-lg border border-slate-200 bg-white p-1 shadow-sm">
                        <button type="button" @click="tabPreconv = 'conv'"
                                :class="tabPreconv === 'conv' ? 'bg-green-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                                class="rounded-md px-3 py-1.5 text-sm font-medium">Convalidados ({{ convalidadosLista.length }})</button>
                        <button type="button" @click="tabPreconv = 'no'"
                                :class="tabPreconv === 'no' ? 'bg-amber-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                                class="rounded-md px-3 py-1.5 text-sm font-medium">No convalidados ({{ noConvalidadosLista.length }})</button>
                        <button type="button" @click="tabPreconv = 'desap'"
                                :class="tabPreconv === 'desap' ? 'bg-red-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                                class="rounded-md px-3 py-1.5 text-sm font-medium">Desaprobados ({{ desaprobadosLista.length }})</button>
                    </div>

                    <div class="mb-4 overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <!-- Convalidados -->
                            <template v-if="tabPreconv === 'conv'">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                                    <th class="px-4 py-2.5 font-semibold">Curso de origen</th><th class="w-16 px-4 py-2.5 font-semibold">Nota</th>
                                    <th class="w-20 px-4 py-2.5 font-semibold">Créd.</th><th class="px-4 py-2.5 font-semibold">Convalida con (USIL)</th>
                                </tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(f, i) in convalidadosLista" :key="i" class="hover:bg-slate-50/70">
                                        <td class="px-4 py-2 text-slate-700">{{ f.curso_origen_nombre }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ f.nota_origen || '—' }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ f.creditos_origen !== '' && f.creditos_origen != null ? f.creditos_origen : '—' }}</td>
                                        <td class="px-4 py-2 font-medium text-green-700">{{ usilPorId[f.curso_usil_id] || '—' }}</td>
                                    </tr>
                                    <tr v-if="!convalidadosLista.length"><td colspan="4" class="px-4 py-6 text-center text-slate-400">Sin cursos convalidados.</td></tr>
                                </tbody>
                            </template>
                            <!-- No convalidados -->
                            <template v-else-if="tabPreconv === 'no'">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                                    <th class="px-4 py-2.5 font-semibold">Curso de origen</th><th class="w-16 px-4 py-2.5 font-semibold">Nota</th><th class="px-4 py-2.5 font-semibold">Motivo</th>
                                </tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(f, i) in noConvalidadosLista" :key="i" class="hover:bg-slate-50/70">
                                        <td class="px-4 py-2 text-slate-700">{{ f.curso_origen_nombre }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ f.nota_origen || '—' }}</td>
                                        <td class="px-4 py-2">
                                            <span class="inline-block rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                                                {{ f.clasificacion === 'no_convalidable' ? 'No convalidable (política)' : 'Sin equivalencia USIL' }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!noConvalidadosLista.length"><td colspan="3" class="px-4 py-6 text-center text-slate-400">Sin cursos no convalidados.</td></tr>
                                </tbody>
                            </template>
                            <!-- Desaprobados -->
                            <template v-else>
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr>
                                    <th class="px-4 py-2.5 font-semibold">Curso de origen</th><th class="w-16 px-4 py-2.5 font-semibold">Nota</th><th class="w-20 px-4 py-2.5 font-semibold">Créd.</th>
                                </tr></thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(f, i) in desaprobadosLista" :key="i" class="hover:bg-slate-50/70">
                                        <td class="px-4 py-2 text-slate-700">{{ f.curso_origen_nombre }}</td>
                                        <td class="px-4 py-2 text-red-600">{{ f.nota_origen || '—' }}</td>
                                        <td class="px-4 py-2 text-slate-600">{{ f.creditos_origen !== '' && f.creditos_origen != null ? f.creditos_origen : '—' }}</td>
                                    </tr>
                                    <tr v-if="!desaprobadosLista.length"><td colspan="3" class="px-4 py-6 text-center text-slate-400">Sin cursos desaprobados.</td></tr>
                                </tbody>
                            </template>
                        </table>
                    </div>
                    <div v-if="sinAsignar" class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <span>⚠️</span>
                        <span>Hay <strong>{{ sinAsignar }}</strong> curso(s) convalidable(s) <strong>sin asignar</strong> a un curso USIL. Puedes volver a la <button type="button" @click="pasoIA = 5" class="font-medium underline">Etapa 5 · Mapeo</button> para revisarlos, o guardar así si es intencional (quedarán como no convalidados).</span>
                    </div>

                    <div><label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                        <textarea v-model="observaciones" rows="2" class="w-full rounded-md border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]"></textarea></div>
                    <div class="mt-6 flex justify-between">
                        <button @click="anteriorIA" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">← Anterior</button>
                        <button @click="guardar" :disabled="!tieneMalla || procesando || guardadoId" class="rounded-lg bg-[#1F3864] px-6 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-50">
                            {{ guardadoId ? '✓ Guardada' : (procesando ? 'Guardando…' : 'Guardar preconvalidación') }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Simulaciones previas -->
        <div v-if="simulacionesPrevias?.length" class="mt-6">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-400">Simulaciones previas</h2>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm"><tbody class="divide-y divide-slate-100">
                    <tr v-for="s in simulacionesPrevias" :key="s.id" class="hover:bg-slate-50/70">
                        <td class="px-4 py-2 text-slate-600">#{{ s.id }} · {{ s.fecha }}</td>
                        <td class="px-4 py-2"><span :class="s.metodo === 'ia' ? 'text-violet-600' : 'text-slate-600'" class="text-xs font-medium capitalize">{{ s.metodo }}</span></td>
                        <td class="px-4 py-2 text-slate-600">{{ s.carrera }}</td>
                        <td class="px-4 py-2 text-right">
                            <Link :href="`/simulaciones/${s.id}/editar`" class="mr-3 text-[#2E75B6] hover:underline">Editar</Link>
                            <Link :href="`/simulaciones/${s.id}`" class="mr-3 text-slate-500 hover:underline">Ver</Link>
                            <button type="button" @click="eliminarSimulacion(s)" class="text-red-600 hover:underline">Eliminar</button>
                        </td>
                    </tr>
                </tbody></table>
            </div>
        </div>
    </div>
</template>
