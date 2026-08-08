# Sistema de Registro de Roles y Segregación de Funciones (SoD)

> Documento maestro de arquitectura, modelo de datos, funcionalidades y lineamientos técnicos.
> Versión: 2.0 · Última actualización: 2026-08-08 · Estado: Diseño / Especificación

---

## Índice

1. [Visión General](#1-visión-general)
2. [Objetivos y Alcance](#2-objetivos-y-alcance)
3. [Glosario](#3-glosario)
4. [Actores y Roles de la Plataforma](#4-actores-y-roles-de-la-plataforma)
5. [Stack Tecnológico](#5-stack-tecnológico)
6. [Arquitectura de la Aplicación](#6-arquitectura-de-la-aplicación)
7. [Modelo de Datos (MySQL)](#7-modelo-de-datos-mysql)
8. [Funcionalidades y Módulos](#8-funcionalidades-y-módulos)
9. [Seguridad](#9-seguridad)
10. [Auditoría y Trazabilidad](#10-auditoría-y-trazabilidad)
11. [Notificaciones](#11-notificaciones)
12. [Reportería, Dashboards y KPIs](#12-reportería-dashboards-y-kpis)
13. [API REST](#13-api-rest)
14. [Importación / Exportación de Datos](#14-importación--exportación-de-datos)
15. [Requisitos No Funcionales](#15-requisitos-no-funcionales)
16. [Estrategia de Pruebas](#16-estrategia-de-pruebas)
17. [DevOps, CI/CD y Despliegue](#17-devops-cicd-y-despliegue)
18. [Estructura del Proyecto y Convenciones](#18-estructura-del-proyecto-y-convenciones)
19. [Datos Iniciales (Seeders)](#19-datos-iniciales-seeders)
20. [Roadmap por Fases](#20-roadmap-por-fases)

---

## 1. Visión General

Este documento define la arquitectura, modelos de base de datos y flujos para un sistema de gestión de roles, validación de **Segregación de Funciones (SoD)** y control de accesos, basado en los conceptos del documento "Segregación de Funciones SoD - Roles". El sistema evoluciona desde un enfoque simple en Python/Streamlit hacia una **plataforma web empresarial** robusta, auditable y escalable.

El propósito central es **prevenir la asignación de combinaciones de roles que generen riesgo** (por ejemplo, que la misma persona pueda crear un proveedor y a la vez aprobar pagos a ese proveedor), soportando además el flujo completo de solicitud, simulación y autorización de accesos.

---

## 2. Objetivos y Alcance

### 2.1 Objetivos
* Centralizar el catálogo de aplicaciones, roles y opciones de la organización.
* Mantener una "foto" actualizada de los accesos que posee cada colaborador.
* Detectar y prevenir conflictos de Segregación de Funciones **antes** de otorgar un acceso (simulación preventiva).
* Gestionar un flujo de aprobaciones dinámico según la criticidad de la aplicación.
* Proveer trazabilidad completa (quién solicitó, quién aprobó, cuándo y por qué) para auditorías internas y externas (SOX, ISO 27001).

### 2.2 Dentro del alcance
* Gestión de catálogos, matriz SoD, simulador, workflow de aprobaciones, reportería y auditoría.

### 2.3 Fuera del alcance (por ahora)
* Aprovisionamiento automático de accesos en los sistemas destino (integración IGA/IAM). El sistema **recomienda y autoriza**; la ejecución técnica del acceso permanece manual o se integra en una fase futura.
* Single Sign-On con proveedores externos (se contempla en el Roadmap).

---

## 3. Glosario

| Término | Definición |
|---|---|
| **SoD** | Segregation of Duties. Principio de control interno que separa tareas incompatibles entre distintas personas. |
| **Foto (Snapshot)** | Conjunto de roles que un usuario posee actualmente en todas las aplicaciones. |
| **Cargo (Job Position)** | Puesto formal del colaborador; determina qué roles le son "compatibles". |
| **Opción** | Acción o permiso atómico dentro de un rol (ej. "Crear Proveedor"). |
| **Conflicto SoD** | Par de roles/opciones que no deben coexistir en un mismo usuario. |
| **Owner de aplicación** | Responsable funcional que aprueba accesos a esa aplicación. |
| **Criticidad** | Nivel de riesgo de una aplicación; define la profundidad del flujo de aprobación. |

---

## 4. Actores y Roles de la Plataforma

Estos son los roles **de la aplicación** (distintos de los roles de negocio que administra):

| Rol | Responsabilidades |
|---|---|
| **Administrador** | Gestiona catálogos, usuarios, matriz SoD y parámetros del sistema. |
| **Gestor de Seguridad** | Aprueba accesos de criticidad alta; administra reglas SoD; revisa reportes. |
| **Owner de Aplicación** | Aprueba solicitudes de acceso a las aplicaciones bajo su responsabilidad. |
| **Jefe/Supervisor** | Aprueba (primer paso) solicitudes de su equipo. |
| **Solicitante** | Crea solicitudes de acceso para sí mismo o para colaboradores a cargo. |
| **Auditor (solo lectura)** | Consulta la foto, el historial y los reportes sin poder modificar datos. |

La autorización se implementa con **[spatie/laravel-permission](https://spatie.be/docs/laravel-permission)** (roles y permisos) y **Policies/Gates** de Laravel para reglas finas por recurso.

---

## 5. Stack Tecnológico

| Capa | Tecnología |
|---|---|
| **Backend** | Laravel 11 (PHP 8.3+) |
| **Frontend** | Livewire 3 + Alpine.js + Tailwind CSS |
| **Base de Datos** | MySQL 8.x |
| **Cache / Colas / Sesiones** | Redis |
| **Cola de trabajos** | Laravel Queues (driver Redis) + Horizon (monitoreo) |
| **Autorización** | spatie/laravel-permission |
| **Auditoría** | owen-it/laravel-auditing |
| **Import/Export** | maatwebsite/excel (Laravel Excel) |
| **Autenticación** | Laravel Fortify / Jetstream (2FA incluido) |
| **Testing** | PHPUnit / Pest + Laravel Dusk (E2E) |
| **Assets** | Vite |
| **Contenedores** | Docker + Docker Compose (Laravel Sail en desarrollo) |

---

## 6. Arquitectura de la Aplicación

Arquitectura **monolítica modular** basada en las convenciones de Laravel, con separación de responsabilidades:

* **Controllers / Componentes Livewire** → orquestan la interacción de UI.
* **Form Requests** → validación de entrada.
* **Services / Actions** → lógica de negocio reutilizable (ej. `SodEvaluator`, `ApprovalWorkflowBuilder`).
* **Models (Eloquent)** → acceso a datos y relaciones.
* **Policies** → autorización por recurso.
* **Events / Listeners / Jobs** → efectos secundarios asíncronos (notificaciones, construcción de flujos, auditoría).

```
Usuario ─▶ Componente Livewire ─▶ Form Request (validación)
                                   │
                                   ▼
                            Service / Action  ◀── SodEvaluator, ApprovalWorkflowBuilder
                                   │
                     ┌─────────────┼──────────────┐
                     ▼             ▼               ▼
                 Eloquent      Events/Jobs     Audit Log
                  (MySQL)       (Redis)      (laravel-auditing)
```

El **motor SoD** (`App\Services\SodEvaluator`) es un servicio puro y testeable de forma aislada, sin dependencias de UI.

---

## 7. Modelo de Datos (MySQL)

> Convenciones: claves primarias `id` (BIGINT UNSIGNED autoincrement), timestamps `created_at`/`updated_at` en todas las tablas, borrado lógico (`deleted_at`) en catálogos críticos, restricciones de integridad referencial explícitas y **índices** en todas las FK y columnas de búsqueda frecuente.

### 7.1 Catálogos Principales

* **`users`**
  * `id`, `name`, `email` (unique), `password`, `job_position_id` (FK), `department_id` (FK), `is_active` (bool), `email_verified_at`, `remember_token`, `created_at`, `updated_at`, `deleted_at`
* **`departments`** *(nuevo — normaliza el departamento)*
  * `id`, `name`, `code` (unique), `manager_id` (FK users, nullable)
* **`job_positions` (Cargos)**
  * `id`, `name`, `description`, `deleted_at`
* **`applications`** — Catálogo de las apps administradas
  * `id`, `name` (unique), `description`, `criticality_level` (Enum: `baja`, `media`, `alta`), `owner_id` (FK users — responsable de aprobar accesos), `is_active` (bool), `deleted_at`
* **`roles`** — Los roles de negocio dentro de cada app
  * `id`, `application_id` (FK), `name`, `description`, `deleted_at`
  * *unique compuesto:* (`application_id`, `name`)
* **`options`** — Opciones/Acciones dentro de un rol
  * `id`, `role_id` (FK), `name` (ej. 'Crear Proveedor'), `action_key` (unique por rol)

### 7.2 Relaciones, Asignaciones y Reglas de Negocio

* **`user_role`** — La "Foto" actual: qué tiene cada quién
  * `id`, `user_id` (FK), `role_id` (FK), `assigned_at`, `assigned_by` (FK users), `source` (Enum: `manual`, `import`, `request`), `expires_at` (nullable — soporta accesos temporales)
  * *unique compuesto:* (`user_id`, `role_id`)
* **`job_position_role`** — Matriz de Cargos vs Roles Permitidos
  * `id`, `job_position_id` (FK), `role_id` (FK)
  * *unique compuesto:* (`job_position_id`, `role_id`)
* **`sod_matrix`** — La tabla de conflictos
  * `id`, `role_a_id` (FK roles), `role_b_id` (FK roles), `risk_level` (Enum: `alto`, `medio`, `bajo`), `description` (razón del conflicto), `is_active` (bool)
  * *unique compuesto normalizado:* (`role_a_id`, `role_b_id`) — se recomienda almacenar siempre con `role_a_id < role_b_id` para evitar pares duplicados invertidos.

### 7.3 Gestión de Solicitudes y Autorizaciones

* **`role_requests`**
  * `id`, `user_id` (FK — usuario que recibe el rol), `requested_role_id` (FK), `status` (Enum: `pendiente`, `en_aprobacion`, `aprobado`, `rechazado`, `cancelado`), `requester_id` (FK — quien lo solicita), `justification` (texto — motivo de negocio), `has_sod_conflict` (bool), `created_at`
* **`request_approvals`** — Flujo dinámico de aprobación
  * `id`, `role_request_id` (FK), `approver_id` (FK), `step_order` (int), `status` (Enum: `pendiente`, `aprobado`, `rechazado`), `comments`, `resolved_at`
* **`request_sod_findings`** *(nuevo — deja constancia de los conflictos detectados en la solicitud)*
  * `id`, `role_request_id` (FK), `conflicting_role_id` (FK), `sod_matrix_id` (FK), `risk_level`

### 7.4 Auditoría y Sistema

* **`audits`** — Tabla de laravel-auditing (registra cambios en todos los modelos auditables)
  * `id`, `user_type`, `user_id`, `event`, `auditable_type`, `auditable_id`, `old_values` (json), `new_values` (json), `url`, `ip_address`, `user_agent`, `created_at`
* Tablas estándar de Laravel: `password_reset_tokens`, `sessions`, `jobs`, `failed_jobs`, `notifications`, `permission`/`role` de spatie.

### 7.5 Diagrama entidad-relación (resumen)

```
departments ──< users >── job_positions
                 │  │
                 │  └──< user_role >── roles ──< options
                 │                       │
job_position_role ──────────────────────┘
                                         │
roles ──< sod_matrix >── roles          │
                                         │
users ──< role_requests >── roles ──< request_approvals >── users
              │
              └──< request_sod_findings >── sod_matrix
```

---

## 8. Funcionalidades y Módulos

### 8.1 Módulo de Administración (CRUD)
* **Gestión de Catálogos:** Pantallas interactivas (Livewire) para crear, editar y eliminar usuarios, departamentos, cargos, aplicaciones, roles y opciones. UI con Tailwind CSS (modales y tablas responsivas, paginación, búsqueda y filtros server-side).
* **Mapeo de Cargos:** Pantalla para asociar qué roles (de cualquier aplicación) son válidos para un Cargo específico.
* **Actualización de la "Foto":** Interfaz para sincronizar manualmente o importar vía Excel los roles que actualmente poseen los usuarios, con vista previa y validación antes de confirmar.

### 8.2 Módulo Gestor de la Matriz SoD
* Pantalla para definir conflictos cruzados entre roles (y opciones).
* Selección de Rol A y Rol B mediante menús desplegables dependientes (aplicación → rol) y especificación del nivel de riesgo.
* Prevención de duplicados invertidos y validación de que un rol no entre en conflicto consigo mismo.

### 8.3 Módulo de Simulación y Solicitud (Core)
Construido con Livewire para interactividad en tiempo real sin recargar la página.

**Flujo del Simulador:**
1. **Selección:** El usuario selecciona a un colaborador y la aplicación a la que desea solicitar acceso.
2. **Intención:** Se elige el rol deseado.
3. **Validación Backend (`SodEvaluator`):**
   * *Regla 1 (Cargos):* Verifica en `job_position_role` si el cargo del usuario admite el rol. Si no, alerta que el cargo no es compatible.
   * *Regla 2 (SoD):* Obtiene la "Foto" del usuario y cruza el rol nuevo contra ella usando `sod_matrix`.
4. **Resultados UI:**
   * Si hay conflicto, se muestra una alerta (`bg-red-100`) detallando roles en conflicto y su nivel de riesgo.
   * Si la simulación es exitosa (o el solicitante acepta el riesgo con justificación), se habilita el botón para crear la solicitud.

### 8.4 Motor de Autorizaciones (Workflow)
Al generar una solicitud, un `Listener`/`Job` construye el flujo en `request_approvals` según la criticidad:
* **Criticidad Baja:** Auto-aprobación o 1 paso (jefe directo).
* **Criticidad Media:** 1 paso (aprobación del `owner_id` de la App).
* **Criticidad Alta:** 2 pasos (1° `owner_id`, 2° Gestor de Seguridad).
* **Regla adicional:** si la solicitud tiene conflicto SoD, se **fuerza** el paso de Gestor de Seguridad independientemente de la criticidad.

Cada paso notifica al aprobador; la aprobación del último paso dispara la actualización de la "Foto" (`user_role`).

---

## 9. Seguridad

* **Autenticación:** Laravel Fortify/Jetstream con verificación de correo y **2FA (TOTP)** opcional/obligatorio para roles administrativos.
* **Autorización:** RBAC con spatie/laravel-permission + Policies por recurso. Principio de mínimo privilegio.
* **Protección de credenciales:** Hash `bcrypt`/`argon2id`; nunca se almacenan contraseñas en texto plano.
* **Protecciones del framework:** CSRF en formularios, escape de salida (Blade), consultas parametrizadas (Eloquent) contra inyección SQL.
* **Rate limiting** en login y endpoints de API.
* **Cabeceras de seguridad** (CSP, HSTS, X-Frame-Options) vía middleware.
* **Cifrado en tránsito** (TLS obligatorio) y de datos sensibles en reposo cuando aplique (`encrypted` casts).
* **Gestión de secretos** por variables de entorno (`.env`), nunca en el repositorio.
* **Segregación de la propia app:** ningún usuario puede aprobar una solicitud que él mismo generó (control anti-self-approval en el workflow).

---

## 10. Auditoría y Trazabilidad

* Todos los modelos sensibles (usuarios, roles, `sod_matrix`, `user_role`, solicitudes, aprobaciones) son **auditables** con `owen-it/laravel-auditing`, registrando valores anteriores/nuevos, autor, IP y user-agent.
* **Historial inmutable** de solicitudes y aprobaciones (quién, cuándo, qué comentó).
* Pantalla de "Bitácora" filtrable por usuario, entidad, evento y rango de fechas, exportable para auditores.
* Retención configurable de logs conforme a políticas de cumplimiento.

---

## 11. Notificaciones

Vía **Laravel Notifications** (canales `mail` + `database`, extensible a Slack/Teams):

* Nueva solicitud pendiente → aprobador del paso actual.
* Solicitud aprobada/rechazada → solicitante y beneficiario.
* Recordatorios de aprobaciones pendientes (Job programado en el Scheduler).
* Alerta a Gestor de Seguridad cuando se registra una solicitud con conflicto SoD de riesgo alto.
* Centro de notificaciones in-app (campana) con marcado de leído/no leído.

---

## 12. Reportería, Dashboards y KPIs

**Dashboard principal** con tarjetas y gráficos (por rol de usuario):
* Solicitudes por estado y por criticidad.
* Conflictos SoD detectados vs. aceptados (con justificación) en el tiempo.
* Aprobaciones pendientes por aprobador (SLA / antigüedad).
* Top aplicaciones y roles más solicitados.

**Reportes exportables (Excel/PDF):**
* **Reporte de Conflictos Activos:** usuarios que actualmente poseen combinaciones en conflicto (revisión periódica).
* **Matriz de Accesos por Usuario/Cargo/Aplicación.**
* **Reporte de Excepciones:** accesos otorgados pese a conflicto SoD, con su justificación y aprobador.
* **Reporte de Certificación de Accesos** (recertificación periódica).

---

## 13. API REST

API versionada (`/api/v1`) protegida con **Laravel Sanctum** (tokens) para integraciones y automatización:

| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/api/v1/users/{id}/roles` | Foto de accesos de un usuario |
| `POST` | `/api/v1/simulations` | Evalúa un posible acceso (motor SoD) |
| `POST` | `/api/v1/requests` | Crea una solicitud de acceso |
| `GET` | `/api/v1/requests/{id}` | Estado y trazabilidad de una solicitud |
| `POST` | `/api/v1/requests/{id}/approve` | Aprueba el paso actual |
| `POST` | `/api/v1/requests/{id}/reject` | Rechaza la solicitud |
| `GET` | `/api/v1/sod-conflicts` | Consulta la matriz de conflictos |

* Respuestas JSON estándar con **API Resources**, paginación y códigos HTTP correctos.
* Rate limiting y scopes de token por permiso.
* Documentación con OpenAPI/Swagger (`l5-swagger` o Scribe).

---

## 14. Importación / Exportación de Datos

* **Importación de la "Foto"** desde Excel/CSV con **Laravel Excel**: validación por fila, reporte de errores, importación en cola (Jobs) para archivos grandes y confirmación previa (dry-run).
* **Importación de catálogos** (usuarios, roles, matriz SoD) con plantillas descargables.
* **Exportación** de cualquier reporte a Excel/PDF respetando los permisos del usuario.

---

## 15. Requisitos No Funcionales

* **Rendimiento:** respuestas de UI < 300 ms en operaciones comunes; simulación SoD < 1 s. Uso de cache (Redis) para catálogos y matriz.
* **Escalabilidad:** procesamiento asíncrono (colas) para importaciones, notificaciones y construcción de workflows.
* **Disponibilidad:** objetivo 99.5%; jobs idempotentes y reintentos con backoff.
* **Accesibilidad:** cumplimiento WCAG 2.1 AA en la UI.
* **Internacionalización:** textos vía archivos de traducción (`lang/`), español por defecto.
* **Observabilidad:** logging estructurado, monitoreo de colas con Horizon, health checks.
* **Mantenibilidad:** PSR-12, análisis estático (PHPStan/Larastan), formateo con Pint.

---

## 16. Estrategia de Pruebas

| Nivel | Herramienta | Cobertura objetivo |
|---|---|---|
| **Unitarias** | Pest/PHPUnit | `SodEvaluator`, `ApprovalWorkflowBuilder`, reglas de negocio |
| **Feature/Integración** | Pest/PHPUnit | Endpoints, componentes Livewire, flujos de solicitud/aprobación |
| **E2E** | Laravel Dusk | Flujo completo: simulación → solicitud → aprobación |
| **Estático** | Larastan/PHPStan (nivel ≥ 6) | Todo el código |

* Cobertura mínima recomendada: **80%** en la capa de servicios (core SoD).
* Casos clave a cubrir: detección de conflicto directo, par invertido, accesos expirados, anti-self-approval, escalamiento a Seguridad por conflicto.
* Factories y seeders de prueba para datos determinísticos.

---

## 17. DevOps, CI/CD y Despliegue

* **Contenedores:** Dockerfile + `docker-compose` (app PHP-FPM, Nginx, MySQL, Redis, worker de colas). Laravel Sail en desarrollo.
* **CI (GitHub Actions):** en cada PR → `composer install`, Pint (lint), Larastan (estático), migraciones sobre BD efímera, suite de pruebas. Bloqueo de merge si algo falla.
* **CD:** despliegue a staging automático en `main`; producción con aprobación manual. Migraciones con `php artisan migrate --force`, `optimize`, y `config/route/view cache`.
* **Entornos:** `local`, `staging`, `production`, con `.env` separados y variables gestionadas por el proveedor.
* **Backups:** respaldo diario de MySQL y prueba periódica de restauración.
* **Zero-downtime:** despliegue atómico (symlink) y `php artisan down/up` o modo mantenimiento con secret.

---

## 18. Estructura del Proyecto y Convenciones

```
app/
├── Actions/               # Acciones de un solo propósito
├── Http/
│   ├── Controllers/Api/   # Controladores de la API v1
│   ├── Requests/          # Form Requests (validación)
│   └── Resources/         # API Resources
├── Livewire/              # Componentes Livewire (UI)
├── Models/                # Eloquent
├── Policies/              # Autorización por recurso
├── Services/
│   ├── SodEvaluator.php
│   └── ApprovalWorkflowBuilder.php
├── Events/  Listeners/  Jobs/  Notifications/
database/
├── migrations/  factories/  seeders/
tests/
├── Unit/  Feature/  Browser/
```

**Convenciones:**
* Código y comentarios de dominio en **español**; nombres de clases/métodos en inglés siguiendo Laravel.
* Migraciones con nombres descriptivos y `down()` reversibles.
* Enums de PHP 8.1 para estados (`RequestStatus`, `CriticalityLevel`, `RiskLevel`).
* Commits siguiendo *Conventional Commits*.
* Ramas: `feature/*`, `fix/*`, `chore/*` con PR y revisión.

---

## 19. Datos Iniciales (Seeders)

* **Roles y permisos de plataforma** (Administrador, Gestor de Seguridad, Owner, Jefe, Solicitante, Auditor).
* **Departamentos y cargos** de ejemplo.
* **Aplicaciones** con distintos niveles de criticidad y sus owners.
* **Roles y opciones** por aplicación (ej. ERP: "Crear Proveedor", "Aprobar Pago").
* **Matriz SoD** con conflictos clásicos precargados (ej. *Crear Proveedor* ⇄ *Aprobar Pago*).
* **Usuario administrador** inicial parametrizado por `.env`.

---

## 20. Roadmap por Fases

| Fase | Entregable |
|---|---|
| **Fase 1 — Fundaciones** | Autenticación, RBAC, CRUD de catálogos, modelo de datos y migraciones. |
| **Fase 2 — Core SoD** | Matriz SoD, motor `SodEvaluator`, simulador Livewire. |
| **Fase 3 — Workflow** | Solicitudes, motor de autorizaciones dinámico, notificaciones. |
| **Fase 4 — Auditoría y Reportes** | Bitácora, dashboards, reportes exportables, import/export Excel. |
| **Fase 5 — API e Integraciones** | API v1 con Sanctum, documentación OpenAPI. |
| **Fase 6 — Madurez** | Recertificación de accesos, SSO/SAML, aprovisionamiento automático (IGA). |
