<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const status = computed(() => page.props.flash?.status);
const resetUrl = computed(() => page.props.flash?.reset_url);

const form = useForm({ email: '' });
const enviar = () => form.post('/password/olvide', { preserveScroll: true });
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-100 px-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
            <!-- Logo + marca -->
            <div class="mb-6 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-md bg-[#1F3864] text-[11px] font-bold leading-none text-white">
                    USIL
                </div>
                <span class="text-lg font-semibold text-[#1F3864]">USIL Convalidaciones</span>
            </div>

            <h1 class="text-xl font-bold text-slate-800">¿Olvidaste tu contraseña?</h1>
            <p class="mb-6 mt-2 text-sm leading-relaxed text-slate-500">
                Ingresa tu correo institucional y te enviaremos un enlace para crear una nueva contraseña.
            </p>

            <!-- Mensaje de confirmación -->
            <div v-if="status" class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">
                {{ status }}
            </div>

            <!-- Enlace de prueba (solo entorno local) -->
            <div v-if="resetUrl" class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
                <p class="font-medium">Entorno local — sin servidor de correo</p>
                <p class="mt-1">Usa este enlace para restablecer tu contraseña:</p>
                <Link :href="resetUrl" class="mt-1 block break-all font-medium text-[#2E75B6] hover:underline">
                    {{ resetUrl }}
                </Link>
            </div>

            <form @submit.prevent="enviar" class="space-y-5">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Correo Institucional</label>
                    <input v-model="form.email" type="email" autocomplete="username"
                           placeholder="ejemplo@usil.edu.pe"
                           class="w-full rounded-lg border-slate-300 py-2.5 px-3 text-sm text-slate-700 placeholder-slate-400 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>

                <button type="submit" :disabled="form.processing"
                        class="w-full rounded-lg bg-[#1F3864] py-3 text-sm font-semibold text-white transition hover:bg-[#2E75B6] disabled:opacity-60">
                    Enviar enlace
                </button>
            </form>

            <Link href="/login" class="mt-6 flex items-center justify-center gap-1 text-sm font-medium text-[#2E75B6] hover:underline">
                ← Volver a iniciar sesión
            </Link>
        </div>
    </div>
</template>
