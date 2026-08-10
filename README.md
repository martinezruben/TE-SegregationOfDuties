# Sistema de Registro de Roles y Segregación de Funciones (SoD)

Plataforma web para gestión de roles, validación de **Segregación de Funciones (SoD)** y control de accesos.
La especificación funcional y de arquitectura completa está en **[`context.md`](./context.md)**.

## Stack

- **Backend:** Laravel (PHP 8.3+)
- **UI:** Inertia.js + React + TypeScript + Tailwind CSS + **shadcn/ui** (Radix)
- **Base de datos:** MySQL en producción · SQLite en desarrollo
- **RBAC:** spatie/laravel-permission · **Auditoría:** owen-it/laravel-auditing · **Excel:** maatwebsite/excel

## Requisitos

- PHP 8.3+ con extensiones habituales de Laravel
- Composer
- Node.js 22+ y npm

## Puesta en marcha (desarrollo)

```bash
# 1. Dependencias
composer install
npm install

# 2. Entorno
cp .env.example .env
php artisan key:generate

# 3. Base de datos (SQLite por defecto en dev)
touch database/database.sqlite
php artisan migrate

# 4. Desarrollo (dos terminales, o usa el script combinado si existe)
npm run dev        # Vite + HMR
php artisan serve  # servidor PHP en http://localhost:8000
```

Compilar assets para producción:

```bash
npm run build
```

## Sistema de diseño (shadcn/ui)

- Componentes en `resources/js/components/ui/` (propiedad del proyecto, estilo **new-york**).
- Tokens de color/tema en `resources/css/app.css` y `tailwind.config.js` (ver `context.md` §6A).
- Utilidad `cn()` en `resources/js/lib/utils.ts`. Config de shadcn en `components.json`.
- Agregar más componentes: `npx shadcn@latest add <componente>`.

## Estructura relevante

```
app/                       # Backend Laravel (Controllers, Models, Policies, Services…)
resources/js/
├── components/ui/         # Componentes shadcn/ui
├── Layouts/  Pages/       # Layouts y páginas Inertia (React)
├── lib/utils.ts           # helper cn()
└── app.tsx                # bootstrap de Inertia
resources/css/app.css      # Tailwind + design tokens
routes/                    # Rutas web/auth
database/migrations/       # Migraciones (incluye permission + audits)
```

## Pruebas y calidad

```bash
php artisan test   # backend (Pest/PHPUnit)
npm run build      # typecheck (tsc) + build (vite)
npm run lint       # ESLint (front)
```

CI en `.github/workflows/tests.yml` (backend multi-PHP + build de frontend).
