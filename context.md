# Sistema de Registro de Roles y Segregación de Funciones (SoD)

## 1. Visión General
Este documento define la arquitectura, modelos de base de datos y flujos para un sistema de gestión de roles, validación de Segregación de Funciones (SoD) y control de accesos, basado en los conceptos del documento "Segregacion de Funciones SOD - Roles". El sistema pasará de un enfoque simple en Python/Streamlit a una plataforma web robusta empresarial.

## 2. Stack Tecnológico
* **Backend:** Laravel (PHP)
* **Frontend:** Livewire + Alpine.js + Tailwind CSS
* **Base de Datos:** MySQL

## 3. Modelos de Base de Datos (MySQL)

Para soportar las validaciones por cargo, aplicación, y flujo de autorizaciones, la base de datos MySQL debe estar estructurada de la siguiente manera:

### 3.1 Catálogos Principales
* **`users`**:
  * `id`, `name`, `email`, `job_position_id` (FK), `department_id` (FK), `created_at`, `updated_at`
* **`job_positions` (Cargos)**:
  * `id`, `name`, `description`
* **`applications`** (Catálogo de las apps administradas [cite: 1]):
  * `id`, `name`, `description`, `criticality_level` (Enum: Baja, Media, Alta), `owner_id` (FK users - Responsable de aprobar accesos)
* **`roles`** (Los roles dentro de cada app [cite: 1]):
  * `id`, `application_id` (FK), `name`, `description`
* **`options` (Opciones/Acciones dentro de un rol)**:
  * `id`, `role_id` (FK), `name` (Ej: 'Crear Proveedor'), `action_key`

### 3.2 Relaciones, Asignaciones y Reglas de Negocio
* **`user_role` (La "Foto" actual: qué tiene cada quién [cite: 1])**:
  * `id`, `user_id` (FK), `role_id` (FK), `assigned_at`, `assigned_by` (FK)
* **`job_position_role` (Matriz de Cargos vs Roles Permitidos)**:
  * `id`, `job_position_id` (FK), `role_id` (FK)
* **`sod_matrix` (La tabla de conflictos [cite: 1])**:
  * `id`, `role_a_id` (FK), `role_b_id` (FK), `risk_level` (Enum: Alto, Medio, Bajo) [cite: 1], `description` (Razón del conflicto)

### 3.3 Gestión de Solicitudes y Autorizaciones
* **`role_requests`**:
  * `id`, `user_id` (FK - Usuario que recibe el rol), `requested_role_id` (FK), `status` (Enum: Pendiente, Aprobado, Rechazado), `requester_id` (FK - Quien lo solicita), `created_at`
* **`request_approvals` (Flujo dinámico de aprobación)**:
  * `id`, `role_request_id` (FK), `approver_id` (FK), `step_order` (Int), `status` (Pendiente, Aprobado, Rechazado), `comments`, `resolved_at`

## 4. Funcionalidades y Módulos de la Aplicación (Laravel + Livewire)

### 4.1 Módulo de Administración (CRUD)
* **Gestión de Catálogos [cite: 1]:** Pantallas interactivas (Livewire) para crear, editar y eliminar usuarios, cargos, aplicaciones y roles. La interfaz utilizará Tailwind CSS para modales y tablas responsivas.
* **Mapeo de Cargos:** Pantalla para asociar qué roles (de cualquier aplicación) son válidos para un Cargo (`job_position`) específico.
* **Actualización de la "Foto":** Interfaz para sincronizar manualmente o importar vía Excel los roles que actualmente poseen los usuarios [cite: 1].

### 4.2 Módulo Gestor de la Matriz SoD
* Pantalla para definir conflictos cruzados entre roles y opciones [cite: 1].
* Se seleccionan el Rol A y Rol B desde menús desplegables dependientes (aplicación -> rol) y se especifica el nivel de riesgo de esa combinación [cite: 1].

### 4.3 Módulo de Simulación y Solicitud
Este es el *Core* del sistema, construido con Livewire para interactividad en tiempo real sin recargar la página.

**Flujo del Simulador:**
1. **Selección:** El usuario selecciona a un colaborador y la aplicación a la que desea solicitar acceso [cite: 1].
2. **Intención:** Se elige el rol deseado [cite: 1].
3. **Validación Backend (Laravel Collections/Eloquent):**
   * *Regla 1 (Cargos):* Verifica en `job_position_role` si el cargo del usuario admite el rol seleccionado. Si no, alerta que el cargo no es compatible.
   * *Regla 2 (SoD):* Obtiene la "Foto" del usuario (sus roles actuales) [cite: 1]. Cruza el rol nuevo contra la "Foto" usando la tabla `sod_matrix`.
4. **Resultados UI:**
   * Si hay conflicto, se muestra una alerta (estilo Tailwind `bg-red-100`) detallando los roles en conflicto [cite: 1].
   * Si la simulación es exitosa, se habilita el botón para crear la solicitud (`role_requests`).

### 4.4 Motor de Autorizaciones (Workflow)
Cuando se genera una solicitud, un `Observer` o `Job` de Laravel define el flujo en `request_approvals` basado en la criticidad:
* **App Criticidad Baja:** Auto-aprobación o 1 paso (aprobación del jefe directo).
* **App Criticidad Media:** 1 paso (Aprobación del `owner_id` de la App).
* **App Criticidad Alta:** 2 pasos (1° Aprobación del `owner_id`, 2° Aprobación de Seguridad de la Información).