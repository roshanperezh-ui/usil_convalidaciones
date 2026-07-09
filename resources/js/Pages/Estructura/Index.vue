<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({ conteos: Object });

const ICON = {
    building: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    cap: 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
    briefcase: 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z',
    desktop: 'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25',
    document: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
    stack: 'M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122',
};

const cards = [
    { label: 'Sedes', desc: 'Administra los campus o sedes de la institución.', href: '/estructura/sedes', key: 'sedes', unidad: 'sedes registradas', icon: ICON.building,
        iconBg: 'bg-blue-100', iconText: 'text-blue-600', footBg: 'bg-blue-50', footText: 'text-blue-700' },
    { label: 'Facultades', desc: 'Gestiona las facultades por cada sede.', href: '/estructura/facultades', key: 'facultades', unidad: 'facultades registradas', icon: ICON.cap,
        iconBg: 'bg-emerald-100', iconText: 'text-emerald-600', footBg: 'bg-emerald-50', footText: 'text-emerald-700' },
    { label: 'Programas Académicos', desc: 'Administra los programas o carreras profesionales.', href: '/estructura/programas', key: 'programas', unidad: 'programas registrados', icon: ICON.briefcase,
        iconBg: 'bg-violet-100', iconText: 'text-violet-600', footBg: 'bg-violet-50', footText: 'text-violet-700' },
    { label: 'Modalidades', desc: 'Gestiona las modalidades de estudio disponibles.', href: '/estructura/modalidades', key: 'modalidades', unidad: 'modalidades registradas', icon: ICON.desktop,
        iconBg: 'bg-amber-100', iconText: 'text-amber-600', footBg: 'bg-amber-50', footText: 'text-amber-700' },
    { label: 'Planes de Estudios', desc: 'Administra los planes de estudio por programa y modalidad.', href: '/estructura/planes', key: 'planes', unidad: 'planes registrados', icon: ICON.document,
        iconBg: 'bg-teal-100', iconText: 'text-teal-600', footBg: 'bg-teal-50', footText: 'text-teal-700' },
    { label: 'Mallas Curriculares', desc: 'Administra las mallas de cada plan de estudios.', href: '/mallas', key: 'mallas', unidad: 'mallas registradas', icon: ICON.stack,
        iconBg: 'bg-rose-100', iconText: 'text-rose-600', footBg: 'bg-rose-50', footText: 'text-rose-700' },
];

const num = (n) => String(n ?? 0).padStart(2, '0');
</script>

<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-[#1F3864]">Gestión de la Estructura Institucional</h1>
            <p class="mt-1 text-sm text-slate-500">Administra la jerarquía académica que utilizan Mallas y Convalidaciones.</p>
        </div>

        <!-- Jerarquía -->
        <div class="mb-6 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-sm">
            <template v-for="(n, i) in ['Sede', 'Facultad', 'Programa Académico', 'Modalidad', 'Plan de Estudios', 'Malla Curricular']" :key="n">
                <span class="rounded-md bg-slate-100 px-2.5 py-1 font-medium text-slate-700">{{ n }}</span>
                <span v-if="i < 5" class="text-slate-300">→</span>
            </template>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <Link v-for="c in cards" :key="c.key" :href="c.href"
                  class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div :class="c.iconBg" class="grid h-14 w-14 place-items-center rounded-2xl">
                            <svg :class="c.iconText" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.6"
                                 stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                <path :d="c.icon" />
                            </svg>
                        </div>
                        <span class="rounded-lg border border-slate-200 p-1 text-slate-300 transition group-hover:text-slate-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </span>
                    </div>

                    <h2 class="mt-4 text-lg font-semibold text-[#1F3864]">{{ c.label }}</h2>
                    <p class="mt-1 text-sm leading-snug text-slate-500">{{ c.desc }}</p>

                    <p class="mt-5 text-4xl font-bold tracking-tight text-[#1F3864]">{{ num(conteos[c.key]) }}</p>
                    <p class="text-xs text-slate-400">{{ c.unidad }}</p>
                </div>

                <div :class="[c.footBg, c.footText]" class="mt-auto px-6 py-3 text-sm font-semibold">
                    Administrar {{ c.label.toLowerCase() }} →
                </div>
            </Link>
        </div>
    </div>
</template>
