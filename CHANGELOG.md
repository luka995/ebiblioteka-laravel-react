# Changelog

## [31.08.2026] Initial Laravel + React project setup with Docker

### Added
- **Project documentation** (`PRD_laravel_react_migracija.md`, `PRD_laravel_react_migracija.html`) — PRD for Laravel + React migration
- **Docker infrastructure** (`docker-compose.yml`, `php/Dockerfile`, `nginx/Dockerfile`, `nginx/default.conf`, `frontend/Dockerfile`, `frontend/.dockerignore`) — complete Docker Compose stack for PHP-FPM, Nginx, PostgreSQL, Redis
- **Laravel application skeleton** (`config/app.php`, `routes/web.php`, `app/Http/Controllers/`) — PublicHomeController, PublicLibraryController, PublicProjectController
- **Frontend React application** (`frontend/`, `vite.config.js`, `resources/js/app.ts`, `resources/js/bootstrap.ts`) — Vite + React + TypeScript setup with DM Sans font
- **Public views** (`resources/views/`) — home, libraries (index, show, book, category), project, layouts, partials (nav, footer)
- **Styling** (`resources/css/app.css`, `frontend/src/styles.css`) — global styles with DM Sans typography, removed DM Mono
- **Database structure** (`ebiblioteka_bib_structure_dump.sql`) — initial database schema dump
- **Testing** (`tests/Feature/`) — PublicHomeTest, PublicProjectTest
- **Postman collections** (`postman/`) — API environment and collection for testing
- **Frontend build assets** (`frontend/dist/`, `public/favicon.svg`) — compiled Vite assets
- **Dependencies** (`package.json`, `package-lock.json`, `frontend/package.json`, `frontend/package-lock.json`) — npm and Composer dependencies

### Changed
- **Config** (`config/app.php`) — updated application configuration
- **Routes** (`routes/web.php`) — added public routes for home, libraries, projects

### Removed
- **DM Mono font** — replaced with DM Sans across all stylesheets