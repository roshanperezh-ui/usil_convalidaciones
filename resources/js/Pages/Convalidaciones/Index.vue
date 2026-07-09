<script setup>
import { router } from '@inertiajs/vue3';
defineProps({ convalidaciones: Object });

// Descarga robusta: enlace temporal (evita bloqueo de pop-ups y pestañas en blanco).
const memorandum = (id) => {
    const a = document.createElement('a');
    a.href = `/convalidaciones/${id}/memorandum`;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
};
const anular = (id) => {
    const motivo = prompt('Motivo de anulación:');
    if (motivo) router.post(`/convalidaciones/${id}/anular`, { motivo }, { preserveScroll: true });
};
</script>

<template>
    <div>
        <h1 class="mb-6 text-2xl font-semibold text-[#1F3864]">Convalidaciones</h1>
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="px-4 py-2 font-medium">Estudiante</th>
                        <th class="px-4 py-2 font-medium">Memorándum</th>
                        <th class="px-4 py-2 font-medium">Fecha</th>
                        <th class="px-4 py-2 font-medium">Estado</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in convalidaciones.data" :key="c.id">
                        <td class="px-4 py-2 font-medium text-slate-800">{{ c.estudiante }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ c.memorandum }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ c.fecha }}</td>
                        <td class="px-4 py-2">
                            <span :class="c.estado === 'confirmada' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                                  class="rounded-full px-2 py-0.5 text-xs font-medium capitalize">{{ c.estado }}</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button @click="memorandum(c.id)" class="text-[#2E75B6] hover:underline">Memorándum</button>
                            <button v-if="c.estado === 'confirmada'" @click="anular(c.id)" class="ml-3 text-red-600 hover:underline">Anular</button>
                        </td>
                    </tr>
                    <tr v-if="!convalidaciones.data.length"><td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin convalidaciones.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
