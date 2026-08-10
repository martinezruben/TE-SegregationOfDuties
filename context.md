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

Estos son los roles **de la aplicación** (distintos de los roles de negocio que administra). El sistema es **totalmente RBAC**: los roles no traen permisos "cableados", sino que se componen de **permisos granulares** asignables desde la propia UI (ver §8.5). Los roles de abajo son la **plantilla inicial** (seeders), pero un Administrador puede crear roles nuevos y recomponer permisos en caliente.

| Rol (plantilla) | Perfil | Permisos típicos |
|---|---|---|
| **Administrador** | Control total | Todos: gestión de usuarios, roles, permisos, menús, catálogos, matriz y autorizaciones. |
| **Definidor de Matrices** | Diseña las reglas | `matriz.ver`, `matriz.crear`, `matriz.editar`, `matriz.eliminar`, `matriz.definir`; catálogos de roles/opciones en modo lectura. **No autoriza ni consume accesos.** |
| **Consultor (solo lectura)** | Solo consulta | `*.ver` sobre los módulos permitidos (foto, matriz, reportes, solicitudes). Ningún permiso de escritura ni de autorización. |
| **Consultor + Autorizador** | Consulta y aprueba | `*.ver` + `solicitudes.autorizar` (y opcionalmente `matriz.ver`). Consulta todo lo visible y **autoriza** los pasos que le corresponden, pero **no define matrices ni administra el sistema**. |
| **Gestor de Seguridad** | Autoridad de riesgo | `matriz.*`, `solicitudes.autorizar`, `solicitudes.autorizar_mayor`, reportes. Aprueba criticidad alta y conflictos SoD. |
| **Owner de Aplicación** | Dueño funcional | `solicitudes.ver`, `solicitudes.autorizar` sobre las aplicaciones que posee. |
| **Jefe/Supervisor** | Aprobador de equipo | `solicitudes.ver`, `solicitudes.autorizar` (primer paso) sobre su equipo. |
| **Solicitante** | Origina solicitudes | `solicitudes.ver`, `solicitudes.crear`, `simulador.ver`. |
| **Auditor** | Cumplimiento | `*.ver` + `bitacora.ver` + `reportes.exportar`. Sin escritura. |

### 4.1 Modelo de Permisos Granulares (RBAC)

La autorización se implementa con **[spatie/laravel-permission](https://spatie.be/docs/laravel-permission)** (roles ↔ permisos ↔ usuarios) y **Policies/Gates** de Laravel para reglas finas por recurso (ej. "solo el owner de *esta* app puede autorizar").

* **Convención de nombres:** cada permiso es `{recurso}.{acción}`. Los **recursos** corresponden a las opciones/módulos del sistema (usuarios, roles, permisos, menus, aplicaciones, matriz, foto, solicitudes, simulador, reportes, bitacora, dashboard).
* **Acciones estándar** por recurso:

| Acción | Significado |
|---|---|
| `ver` | Ver el módulo/opción. **Controla también la visibilidad en el menú lateral** (§8.6). |
| `crear` | Crear registros. |
| `editar` | Modificar registros. |
| `eliminar` | Eliminar (lógico). |
| `autorizar` | Aprobar/rechazar un paso de solicitud. |
| `autorizar_mayor` | Aprobar cambios sensibles (modificación de un rol / definición de matriz) — *autorización mayor* (§8.4). |
| `definir` | Definir/editar reglas de la matriz SoD. |
| `exportar` | Exportar reportes. |

* **Regla de oro de la UI:** un usuario **solo ve en el sidebar** las opciones para las que tiene el permiso `ver`, y dentro de cada pantalla los botones (crear/editar/eliminar/autorizar) se muestran/ocultan según el permiso correspondiente. La misma verificación se repite en el backend (Policy) — nunca se confía solo en la UI.
* **Herencia de contexto:** además del permiso, las Policies validan el **ámbito** (p. ej. un Owner solo autoriza solicitudes de sus aplicaciones; un Jefe solo las de su departamento).

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
  * `id`, `user_id` (FK — usuario que recibe el rol), `requested_role_id` (FK, nullable para modificación de rol), `type` (Enum: `asignacion`, `movimiento`, `modificacion_rol`), `payload` (json — detalle del cambio, ej. rol de origen/destino o campos modificados del rol), `status` (Enum: `pendiente`, `en_aprobacion`, `aprobado`, `rechazado`, `cancelado`, `auto_aprobado`), `requester_id` (FK — quien lo solicita), `justification` (texto — motivo de negocio), `has_sod_conflict` (bool), `is_major` (bool — requiere *autorización mayor*), `auto_resolved` (bool — resuelta por reglas automáticas), `created_at`
* **`request_approvals`** — Flujo dinámico de aprobación (los pasos concretos)
  * `id`, `role_request_id` (FK), `approver_id` (FK, nullable si el paso se resuelve por rol/ámbito), `approver_role` (string — rol/ámbito requerido, ej. `owner_app`, `jefe`, `seguridad`), `step_order` (int), `status` (Enum: `pendiente`, `aprobado`, `rechazado`, `omitido`), `is_auto` (bool — paso resuelto automáticamente), `comments`, `resolved_at`, `resolved_by` (FK, nullable)
* **`request_sod_findings`** *(deja constancia de los conflictos detectados en la solicitud)*
  * `id`, `role_request_id` (FK), `conflicting_role_id` (FK), `sod_matrix_id` (FK), `risk_level`

### 7.4 RBAC y Menú Configurable

* **`roles` (RBAC de plataforma — spatie)** *(no confundir con los roles de negocio de §7.1; usar `guard_name` distinto o modelo separado `PlatformRole`)*
  * `id`, `name`, `guard_name`, `description`, `is_system` (bool — protegido de borrado)
* **`permissions` (spatie)**
  * `id`, `name` (formato `{recurso}.{acción}`), `guard_name`, `group` (agrupación para la UI, ej. "Solicitudes")
* Tablas pivote de spatie: `role_has_permissions`, `model_has_roles`, `model_has_permissions`.
* **`menus`** — Menú lateral jerárquico (menú tree), configurable desde *Menú → Opciones de menú*
  * `id`, `parent_id` (FK self, nullable — soporta N niveles), `label`, `icon` (nombre del ícono), `route` (nombre de ruta Laravel, nullable si es solo agrupador), `permission_name` (string — permiso `ver` requerido para mostrarlo; nullable = visible para todos los autenticados), `order` (int), `is_active` (bool)
  * *Índices:* (`parent_id`, `order`).

### 7.5 Auditoría y Sistema

* **`audits`** — Tabla de laravel-auditing (registra cambios en todos los modelos auditables)
  * `id`, `user_type`, `user_id`, `event`, `auditable_type`, `auditable_id`, `old_values` (json), `new_values` (json), `url`, `ip_address`, `user_agent`, `created_at`
* Tablas estándar de Laravel: `password_reset_tokens`, `sessions`, `jobs`, `failed_jobs`, `notifications`.

### 7.6 Diagrama entidad-relación (resumen)

```
departments ──< users >── job_positions
                 │  │
                 │  └──< user_role >── roles(negocio) ──< options
                 │                       │
job_position_role ──────────────────────┘
                                         │
roles(negocio) ──< sod_matrix >── roles │
                                         │
users ──< role_requests >── roles ──< request_approvals >── users
              │
              └──< request_sod_findings >── sod_matrix

users >──< platform_roles >──< permissions        (RBAC — spatie)
permissions.name ──▶ menus.permission_name         (visibilidad del sidebar)
menus ──< menus (parent_id)                        (árbol jerárquico)
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

El motor decide, para cada solicitud, **si se aprueba automáticamente o qué cadena de aprobadores necesita**. Se dispara con un evento al crear la solicitud (`RoleRequestCreated`) que ejecuta el `Job` **`BuildApprovalWorkflow`**, apoyado en el servicio `ApprovalWorkflowBuilder`.

#### 8.4.1 Tipos de solicitud y nivel de autorización

| Tipo (`role_requests.type`) | Descripción | Nivel |
|---|---|---|
| **Asignación de rol** (`asignacion`) | Otorgar un rol nuevo a un usuario. | Normal |
| **Movimiento de roles** (`movimiento`) | Cambiar un rol por otro (baja de uno + alta de otro) o mover accesos entre usuarios. | Normal |
| **Modificación de un rol** (`modificacion_rol`) | Cambiar la definición del rol (sus opciones/permisos). Afecta a **todos** los usuarios que lo poseen → **Autorización Mayor**. | Mayor |

La **Autorización Mayor** (`is_major = true`) exige el permiso `solicitudes.autorizar_mayor` y **siempre** incluye al Gestor de Seguridad como paso final. Modificar un rol o definir/editar la matriz SoD cae en esta categoría.

#### 8.4.2 Cómo se decide el flujo (árbol de decisión)

```
Solicitud creada
   │
   ├─▶ ¿Es Autorización Mayor (modificación de rol / matriz)?
   │        Sí → flujo: [Owner de la App] → [Gestor de Seguridad]  (autorizar_mayor)
   │
   ├─▶ Evaluación automática (SodEvaluator):
   │     ¿El cargo del usuario PERMITE el rol (job_position_role)  Y
   │      el rol NO produce conflicto en sod_matrix?
   │        Sí → AUTO-APROBACIÓN  (status = auto_aprobado, auto_resolved = true)
   │              → se actualiza la "Foto" (user_role) sin intervención humana
   │
   └─▶ Si NO califica para auto-aprobación, se arma la cadena por criticidad de la app:
            Baja   → 1 paso  : Jefe directo
            Media  → 1 paso  : Owner de la App
            Alta   → 2 pasos : Owner de la App → Gestor de Seguridad
        + Si hay conflicto SoD  → se AÑADE (o fuerza) el paso de Gestor de Seguridad,
                                   sin importar la criticidad.
```

#### 8.4.3 Reglas de autorización automática

Una solicitud se resuelve **sin aprobadores** cuando se cumplen **todas**:
1. El **cargo** del usuario admite el rol (existe en `job_position_role`), **y**
2. El rol **no está** en la matriz de conflictos contra la foto actual del usuario (`sod_matrix`), **y**
3. No es una Autorización Mayor, **y**
4. La aplicación no está marcada como "aprobación manual obligatoria".

En ese caso se registra `status = auto_aprobado`, se crea un `request_approvals` con `is_auto = true` para dejar traza, y se actualiza la "Foto". Todo queda auditado igual que una aprobación manual.

#### 8.4.4 Ejecución de los pasos
* Cada paso pendiente **notifica** al aprobador (o a los usuarios con el rol/ámbito requerido) y aparece en su **Dashboard de pendientes** (§8.8).
* Un aprobador puede **aprobar** (avanza al siguiente `step_order`) o **rechazar** (termina la solicitud como `rechazado`).
* **Anti-self-approval:** el motor nunca asigna como aprobador al `requester_id` ni al beneficiario; si por ámbito coincidiera, escala al siguiente nivel.
* La aprobación del **último paso** dispara la actualización de la "Foto" (`user_role`) —o la aplicación del cambio de rol— dentro de una transacción, y notifica a solicitante y beneficiario.
* Recordatorios automáticos (Scheduler) para pasos vencidos según SLA.

### 8.5 Módulo de Gestión de Usuarios, Roles y Permisos (RBAC)

Panel de administración, protegido por permisos `usuarios.*`, `roles.*`, `permisos.*`:

* **Usuarios:**
  * **Alta de usuario:** formulario con nombre, email, departamento, cargo, estado y **asignación de uno o varios roles de plataforma**. Contraseña inicial autogenerada + correo de activación (o alta vía SSO en fase futura).
  * Edición, activación/desactivación (baja lógica) y reseteo de contraseña.
  * Asignación/quita de roles de plataforma en caliente; opción de otorgar permisos directos puntuales (sin rol) para excepciones.
* **Roles (de plataforma):**
  * CRUD de roles RBAC. Los roles marcados `is_system` no se pueden eliminar.
  * **Editor de permisos por rol:** matriz de casillas **recurso × acción** (ver / crear / editar / eliminar / autorizar / autorizar_mayor / definir / exportar), agrupada por módulo para facilitar la lectura. Marcar `ver` de un recurso habilita que su opción de menú sea visible.
* **Permisos:** catálogo de permisos `{recurso}.{acción}` agrupados; se pueden sincronizar automáticamente con las opciones/menús registrados (comando artisan `permissions:sync`).
* Todo cambio en usuarios/roles/permisos queda **auditado** (§10).

### 8.6 Menú Lateral (Sidebar) Jerárquico

* **Sidebar tipo árbol (menú tree)** con múltiples niveles (`parent_id`), íconos y colapso de secciones (Alpine.js), responsivo (drawer en móvil).
* **Renderizado por permisos:** un `View Composer`/servicio `MenuBuilder` construye el árbol y **filtra cada nodo contra el permiso `ver`** definido en `menus.permission_name`. Si el usuario no tiene ese permiso, la opción **no se renderiza**; un nodo agrupador se oculta si ninguno de sus hijos es visible.
* El árbol se **cachea por rol** (Redis) e se invalida al cambiar menús o permisos.
* Marca activa según la ruta actual y expansión automática de la rama activa.

### 8.7 Configuración del Menú (Menú → Opciones de Menú)

Pantalla de administración (permiso `menus.*`) para **configurar el propio menú sin tocar código**:

* CRUD de opciones de menú: etiqueta, ícono, ruta (nombre de ruta Laravel), **permiso `ver` asociado**, padre (para anidar) y orden.
* **Reordenamiento y anidación drag-and-drop** (Alpine + persistencia de `order`/`parent_id`).
* Activar/desactivar opciones sin borrarlas.
* Validaciones: no permitir ciclos (un nodo no puede ser su propio ancestro), ruta existente y permiso válido.
* Al guardar, se invalida la caché del menú para que los cambios se reflejen de inmediato.

### 8.8 Autenticación (Login) y Dashboard

* **Login:** pantalla de acceso (Fortify) con email/contraseña, "recordarme", recuperación de contraseña, **2FA (TOTP)** y bloqueo por intentos fallidos (rate limiting). Redirige al Dashboard según los permisos del usuario.
* **Dashboard de Tareas Pendientes:** página de inicio personalizada que muestra, según el rol:
  * **Autorizaciones pendientes** asignadas al usuario (solicitudes esperando su aprobación), con acceso directo a aprobar/rechazar.
  * **Mis solicitudes** y su estado (pendiente / en aprobación / aprobada / rechazada).
  * **Alertas SoD** abiertas (para Seguridad): conflictos activos y solicitudes con riesgo alto.
  * **Indicadores** (tarjetas/gráficos): pendientes por antigüedad/SLA, solicitudes por estado, conflictos detectados (ver §12).
  * Cada tarjeta respeta los permisos `ver` del usuario; si no tiene el permiso, la tarjeta no aparece.

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
│   ├── ApprovalWorkflowBuilder.php
│   └── MenuBuilder.php     # Arma el sidebar filtrado por permisos
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

* **Permisos** `{recurso}.{acción}` para todos los módulos (comando `permissions:sync`).
* **Roles de plataforma** con sus permisos precargados: Administrador, Definidor de Matrices, Consultor, Consultor + Autorizador, Gestor de Seguridad, Owner, Jefe, Solicitante, Auditor (ver §4).
* **Opciones de menú** (`menus`) del sidebar, con jerarquía, orden y su `permission_name` de tipo `ver`.
* **Departamentos y cargos** de ejemplo.
* **Aplicaciones** con distintos niveles de criticidad y sus owners.
* **Roles y opciones** por aplicación (ej. ERP: "Crear Proveedor", "Aprobar Pago").
* **Matriz SoD** con conflictos clásicos precargados (ej. *Crear Proveedor* ⇄ *Aprobar Pago*).
* **Usuario administrador** inicial parametrizado por `.env`.

---

## 20. Roadmap por Fases

| Fase | Entregable |
|---|---|
| **Fase 1 — Fundaciones** | Login + 2FA, RBAC (usuarios/roles/permisos), sidebar jerárquico configurable, dashboard de pendientes, CRUD de catálogos, modelo de datos y migraciones. |
| **Fase 2 — Core SoD** | Matriz SoD, motor `SodEvaluator`, simulador Livewire. |
| **Fase 3 — Workflow** | Solicitudes (asignación/movimiento/modificación), motor de autorizaciones con auto-aprobación y autorización mayor, notificaciones. |
| **Fase 4 — Auditoría y Reportes** | Bitácora, dashboards, reportes exportables, import/export Excel. |
| **Fase 5 — API e Integraciones** | API v1 con Sanctum, documentación OpenAPI. |
| **Fase 6 — Madurez** | Recertificación de accesos, SSO/SAML, aprovisionamiento automático (IGA). |
