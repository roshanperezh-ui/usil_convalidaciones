<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const mostrar = ref(false);
const enviar = () => form.post('/password/restablecer', {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
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

            <h1 class="text-xl font-bold text-slate-800">Crea una nueva contraseña</h1>
            <p class="mb-6 mt-2 text-sm leading-relaxed text-slate-500">
                Debe tener al menos 8 caracteres, con mayúsculas, minúsculas, números y un símbolo.
            </p>

            <form @submit.prevent="enviar" class="space-y-5">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Correo Institucional</label>
                    <input v-model="form.email" type="email" autocomplete="username" readonly
                           class="w-full cursor-not-allowed rounded-lg border-slate-200 bg-slate-50 py-2.5 px-3 text-sm text-slate-500" />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Nueva contraseña</label>
                    <div class="relative">
                        <input v-model="form.password" :type="mostrar ? 'text' : 'password'"
                               autocomplete="new-password" placeholder="••••••••"
                               class="w-full rounded-lg border-slate-300 py-2.5 pl-3 pr-10 text-sm text-slate-700 placeholder-slate-400 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                        <button type="button" @click="mostrar = !mostrar"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                            <svg v-if="!mostrar" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.75 7.5-9.75 7.5S2.25 12 2.25 12z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.5 10.5 0 002.25 12s3.75 7.5 9.75 7.5a9.7 9.7 0 005.02-1.38M6.7 6.7A9.7 9.7 0 0112 4.5c6 0 9.75 7.5 9.75 7.5a18.5 18.5 0 01-2.16 3.19M3 3l18 18" />
                            </svg>
                        </button>
                    </div>
                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Confirmar contraseña</label>
                    <input v-model="form.password_confirmation" :type="mostrar ? 'text' : 'password'"
                           autocomplete="new-password" placeholder="••••••••"
                           class="w-full rounded-lg border-slate-300 py-2.5 px-3 text-sm text-slate-700 placeholder-slate-400 focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                </div>

                <button type="submit" :disabled="form.processing"
                        class="w-full rounded-lg bg-[#1F3864] py-3 text-sm font-semibold text-white transition hover:bg-[#2E75B6] disabled:opacity-60">
                    Restablecer contraseña
                </button>
            </form>

            <Link href="/login" class="mt-6 flex items-center justify-center gap-1 text-sm font-medium text-[#2E75B6] hover:underline">
                ← Volver a iniciar sesión
            </Link>
        </div>
    </div>
</template>
