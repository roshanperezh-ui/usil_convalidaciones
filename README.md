# Sistema de Convalidaciones USIL

Backend del Simulador Web de Convalidaciones de Cursos (Laravel 11 + Inertia + Vue 3, MySQL 8).

**Estado:** Construcción completa — los 7 módulos del alcance implementados (Sprints 1 a 4).
Este paquete corresponde al **Sprint 1 — Construcción #1**: andamiaje, esquema de base de datos
(18 tablas, 3FN, InnoDB), **Módulo de Seguridad** (login, RBAC, permisos por carrera),
**Gestión de Usuarios (CU-10)** y **Gestión de Mallas — alta manual (CU-01)** con su frontend Vue/Inertia.

## Decisiones técnicas confirmadas
- **Base de datos:** MySQL 8 (motor InnoDB).
- **Entorno local:** Laravel Sail (Docker) — paridad dev/QA/prod y escalabilidad.
- **Auth:** sesión (Inertia) con tabla `usuarios` y hashing bcrypt en `password_hash`.

## Contenido entregado
- `database/migrations/` — 18 migraciones (esquema completo en 3FN, FKs, índices, soft deletes).
  Incluye la corrección del defecto **TIESTAMP → TIMESTAMP** en `auditoria_log`.
- `app/Models/` — 17 modelos Eloquent con relaciones.
- `app/Http/Controllers/Auth/` — `LoginController` (RF-38/41/42) y `PasswordController`.
- `app/Http/Middleware/EnsureRole.php` — control de acceso por rol (RF-39).
- `app/Models/Concerns/FiltraPorCarrera.php` + `app/Policies/CarreraPolicy.php` — permisos por carrera (RF-40).
- `app/Services/AuditoriaService.php` — registro de auditoría (RNF-08).
- `database/seeders/` — roles base y usuario administrador inicial.
- `app/Http/Controllers/UsuarioController.php` — CU-10 (gestión de usuarios y permisos).
- `app/Http/Controllers/MallaController.php` — CU-01 (alta manual de mallas con ciclos/cursos).
- `app/Http/Middleware/HandleInertiaRequests.php` — comparte usuario/flash con el frontend.
- `resources/js/` — frontend Vue 3 + Inertia: Login, CambiarPassword, Dashboard, Usuarios (index/form), Mallas (index/form) y AppLayout.
- `tests/Feature/` — LoginTest, MallaTest (TC-01 duplicada), RbacTest.

## Puesta en marcha (entorno limpio)
> Requiere instalar primero un esqueleto Laravel 11 y copiar estos archivos dentro,
> o crear el proyecto con `composer create-project laravel/laravel` y sobrescribir.

```bash
# 1. Levantar contenedores (MySQL 8 + Redis + app)
docker compose up -d

# 2. Dependencias y clave de app
composer install
php artisan key:generate
cp .env.example .env   # ajustar credenciales si aplica

# 3. Base de datos
php artisan migrate
php artisan db:seed     # crea roles y admin@usil.edu.pe / Admin#2026

# 4. Pruebas del módulo de seguridad
php artisan test --filter=LoginTest
```

## Configuración requerida (snippets incluidos en /config)
1. `config/auth.php` → provider `users` apunta a `App\Models\User` (tabla `usuarios`).
2. `bootstrap/app.php` → registrar alias de middleware `'role' => EnsureRole::class`.
3. `AppServiceProvider::boot()` → `Gate::policy(Carrera::class, CarreraPolicy::class)`.

## Trazabilidad con la documentación
| Requisito | Implementación |
|-----------|----------------|
| RF-38 | Hashing bcrypt en `password_hash` |
| RF-39 | Roles + `EnsureRole` middleware |
| RF-40 | `permisos_carrera` + `FiltraPorCarrera` + `CarreraPolicy` |
| RF-41 | Bloqueo tras 5 intentos (`intentos_fallidos`, `bloqueado_hasta`) |
| RF-42 | Cambio forzado en primer acceso (`primer_acceso`) |
| RNF-08 | `AuditoriaService` registra en `auditoria_log` |

## Frontend (Vue 3 + Inertia)
Requiere instalar dependencias JS y compilar con Vite:
```bash
npm install
npm install @inertiajs/vue3 @vitejs/plugin-vue
npm run dev   # o npm run build
```

## Trazabilidad adicional
| Caso de uso | Implementación |
|-------------|----------------|
| CU-10 | UsuarioController + Usuarios/Index.vue + Form.vue |
| CU-01 | MallaController + Mallas/Index.vue + Form.vue (RN-01/02/03, RF-04, RF-07) |

## Sprint 2 — incluido en este paquete
| Caso de uso / RF | Implementación |
|------------------|----------------|
| CU-02 | InstitucionController + Instituciones/Index.vue, Form.vue (RF-18, RF-23) |
| CU-03 | EquivalenciaController + Equivalencias/Index.vue, Form.vue (RF-20, RF-21, RF-22, RF-23) |
| RF-08..12 | MallaImportController + Job ImportarMallaExcel + cargas_masivas + Mallas/Importar.vue, CargaEstado.vue |

**Carga masiva (RF-08..12):** valida estructura antes de procesar (RF-08), corre en
background vía colas Redis (RF-11), normaliza y distribuye en ciclos/cursos (RF-10) y
registra logs de éxito/fallo por línea (RF-12). La vista de estado consulta el progreso por sondeo.

> Formato del Excel: encabezados `ciclo`, `codigo`, `nombre`, `creditos`.

## Sprint 3 — incluido en este paquete
| Caso de uso / RF | Implementación |
|------------------|----------------|
| CU-04 | SimulacionController + SimulacionService (RF-24/25/26: tabla comparativa automática) |
| RF-27 | toggleDetalle: excluir/incluir cursos por excepción |
| CU-05 | generarPdf + pdf/simulacion.blade.php (RF-28/29) |
| CU-06 | ConvalidacionController::confirmar (RF-30/31, 1:1) + memorándum (RF-33) |
| RF-46/47 | anular: cambia estado a 'anulada' sin eliminar, con motivo y auditoría |

Vistas: `Simulaciones/Index.vue`, `Form.vue`, `Detalle.vue`; `Convalidaciones/Index.vue`.
PDFs con DomPDF y formato institucional USIL.

## Sprint 4 — incluido en este paquete
| Caso de uso / RF | Implementación |
|------------------|----------------|
| CU-08 | ReporteController (RF-36: resumen por facultad/carrera/fechas) |
| RF-37 | exportar + ConvalidacionesExport (Excel) |
| CU-11 | SugerenciaController::sugerir + SugerenciaIAService (RF-43/44) |
| CU-12 | SugerenciaController::aceptar (RF-45: la IA no autoconfirma) |
| RNF-09 | Seudonimizador: limpia datos personales antes de llamar a la IA (Ley 29733) |
| R-03 | Fallback por historial / por nombre cuando la IA no está disponible |

## Cobertura del alcance (7 módulos)
| Módulo | Estado |
|--------|--------|
| 1. Mallas curriculares (manual + Excel) | ✅ |
| 2. Instituciones y equivalencias | ✅ |
| 3. Simulación de convalidaciones + PDF | ✅ |
| 4. Convalidación confirmada + memorándum + anulación | ✅ |
| 5. Reportes + exportación Excel | ✅ |
| 6. Seguridad y gestión de usuarios | ✅ |
| 7. Asistente de IA (seudonimizado) | ✅ |

## Cómo correr las pruebas
```bash
php artisan test
```
Cubre: seguridad (login/lockout/primer acceso), mallas (duplicada), RBAC, equivalencias,
carga masiva (job en cola), simulación (tabla automática), convalidación (1:1 y anulación),
seudonimización y fallback de IA.

## Configuración adicional (IA)
- Añadir el bloque `openai` de `config/services_openai_snippet.php` a `config/services.php`.
- Definir `OPENAI_API_KEY` y `OPENAI_MODEL` en `.env` (nunca en el código).
