<script setup>
import { useForm, Link } from '@inertiajs/vue3';
defineProps({ carreras: Array });

const form = useForm({ carrera_id: '', anio: new Date().getFullYear(), version: '', archivo: null });
const enviar = () => form.post('/mallas/importar', { forceFormData: true });
</script>

<template>
    <div class="max-w-2xl">
        <h1 class="mb-2 text-2xl font-semibold text-[#1F3864]">Carga masiva de malla</h1>
        <p class="mb-6 text-sm text-slate-500">
            Sube un Excel con columnas: <code class="rounded bg-slate-100 px-1">ciclo</code>,
            <code class="rounded bg-slate-100 px-1">codigo</code>,
            <code class="rounded bg-slate-100 px-1">nombre</code>,
            <code class="rounded bg-slate-100 px-1">creditos</code>. Se procesa en segundo plano.
        </p>

        <form @submit.prevent="enviar" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Carrera</label>
                    <select v-model="form.carrera_id" class="w-full rounded-md border-slate-300 text-sm">
                        <option value="" disabled>Seleccione</option>
                        <option v-for="c in carreras" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                    </select>
                    <p v-if="form.errors.carrera_id" class="mt-1 text-xs text-red-600">{{ form.errors.carrera_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Año</label>
                    <input v-model="form.anio" type="number" class="w-full rounded-md border-slate-300 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Versión</label>
                    <input v-model="form.version" type="text" placeholder="2026-I" class="w-full rounded-md border-slate-300 text-sm" />
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Archivo Excel (.xlsx)</label>
                <input type="file" accept=".xlsx,.xls" @input="form.archivo = $event.target.files[0]"
                       class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-[#1F3864] file:px-4 file:py-2 file:text-sm file:text-white" />
                <p v-if="form.errors.archivo" class="mt-1 text-xs text-red-600">{{ form.errors.archivo }}</p>
                <p v-if="form.progress" class="mt-1 text-xs text-slate-500">Subiendo: {{ form.progress.percentage }}%</p>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" :disabled="form.processing" class="rounded-md bg-[#1F3864] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">Iniciar carga</button>
                <Link href="/mallas" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancelar</Link>
            </div>
        </form>
    </div>
</template>
