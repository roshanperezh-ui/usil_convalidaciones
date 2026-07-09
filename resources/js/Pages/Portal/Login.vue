<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const form = useForm({ email: '', password: '' });
const mostrar = ref(false);
const enviar = () => form.post('/portal/login');
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-[#1F3864] to-[#2E75B6] px-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl">
            <div class="mb-6 flex items-center gap-3">
                <div class="grid h-11 w-11 place-items-center rounded-lg bg-[#1F3864] text-xs font-bold text-white">USIL</div>
                <div>
                    <h1 class="text-lg font-semibold text-[#1F3864]">Portal del Postulante</h1>
                    <p class="text-xs text-slate-500">Seguimiento de tu solicitud de convalidación</p>
                </div>
            </div>

            <form @submit.prevent="enviar" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Correo electrónico</label>
                    <input v-model="form.email" type="email" autocomplete="username" placeholder="tucorreo@example.com"
                           class="w-full rounded-lg border-slate-300 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Contraseña</label>
                    <div class="relative">
                        <input v-model="form.password" :type="mostrar ? 'text' : 'password'" autocomplete="current-password"
                               class="w-full rounded-lg border-slate-300 pr-10 text-sm focus:border-[#2E75B6] focus:ring-[#2E75B6]" />
                        <button type="button" @click="mostrar = !mostrar" class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-slate-400 hover:text-slate-600">
                            {{ mostrar ? 'Ocultar' : 'Ver' }}
                        </button>
                    </div>
                </div>
                <button type="submit" :disabled="form.processing"
                        class="w-full rounded-lg bg-[#1F3864] py-2.5 text-sm font-medium text-white hover:bg-[#2E75B6] disabled:opacity-60">
                    Ingresar
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-slate-400">
                Tus credenciales fueron entregadas por la Coordinación Académica al registrar tu solicitud.
            </p>
            <p class="mt-2 text-center text-xs">
                <a href="/login" class="text-[#2E75B6] hover:underline">Acceso del personal USIL</a>
            </p>
        </div>
    </div>
</template>
