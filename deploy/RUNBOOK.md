# Runbook de Despliegue — Sistema de Convalidaciones USIL

Procedimiento para desplegar a producción y revertir en caso de falla.

## 1. Requisitos previos
- Servidor con Docker y Docker Compose (o cPanel/VPS con PHP 8.2, MySQL 8, Redis).
- DNS apuntando al servidor y certificado TLS (HTTPS obligatorio — RNF-01).
- Secretos definidos: `APP_KEY`, credenciales de BD, `OPENAI_API_KEY`.

## 2. Preparación
```bash
git clone <repo> && cd usil-convalidaciones
git checkout v1.0.0                      # release etiquetado
cp deploy/.env.production.example .env   # completar __definir__
```

## 3. Despliegue (Docker)
```bash
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan key:generate
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
docker compose -f docker-compose.prod.yml exec app php artisan config:cache route:cache view:cache
```
> El servicio `worker` ya queda activo para la carga masiva e IA (RF-11).

## 4. Verificación post-despliegue (smoke test)
- [ ] La página de login responde por HTTPS.
- [ ] Inicio de sesión con el admin inicial y cambio de contraseña forzado.
- [ ] Crear una malla manual y registrar una equivalencia.
- [ ] Generar una simulación y su PDF.
- [ ] `docker compose ... logs worker` muestra el worker activo.

## 5. Checklist de seguridad
- [ ] `APP_DEBUG=false` y `APP_ENV=production`.
- [ ] Secretos fuera del repositorio.
- [ ] Backups de BD programados y probados.
- [ ] Cabeceras de seguridad activas (Nginx) y TLS válido.

## 6. Rollback
```bash
# Volver a la versión anterior estable
git checkout v0.9.0
docker compose -f docker-compose.prod.yml up -d --build
# Si una migración falló:
docker compose -f docker-compose.prod.yml exec app php artisan migrate:rollback
# Restaurar respaldo de BD si fuera necesario.
```

## 7. Operación continua
- Backups diarios de BD y de los PDF (simulaciones/memorándums).
- Rotación de logs (`LOG_CHANNEL=daily`) y del worker.
- Monitoreo de disponibilidad (objetivo SLA 99,5% — RNF-07).
