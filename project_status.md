# Lista de Progreso del Proyecto MultiGame Stats

Este archivo rastrea el estado de todas las tareas necesarias para completar el proyecto.

## 📝 Notas / Backlog

- [x] Separar jugadores por equipo en la vista de detalles del partido.
- [ ] Implementar navegación para ver detalles de equipos y jugadores.
- [ ] Scraper de información de Equipos (Logos, Historia, Roster actual).
- [ ] Scraper de información de Jugadores (Biografía, Estadísticas, Historial).
- [ ] Mejorar diseño de la página principal (más agradable, Tailwind).
- [ ] Ordenar tabla de partidos por fecha.

## 🚀 Fase 1: Configuración Inicial (Skeleton)

- [x] **Estructura del Proyecto**
    - [x] Crear carpetas MVC (`src/`, `public/`, `views/`).
    - [x] Crear clases base (`MatchModel`, `ScraperInterface`).
- [x] **Dependencias**
    - [x] Configurar `composer.json`.
    - [x] Ejecutar `composer install` (Vendor creado).
    - [x] Solucionar error de `autoload.php`.
- [x] **Servidor y Routing**
    - [x] Solucionar error 404 (Crear `.htaccess`).
    - [x] Configurar `index.php` para subcarpetas.
- [x] **Base de Datos**
    - [x] Diseñar esquema (`schema.sql`).
    - [x] Configurar credenciales (`.env`).
    - [x] Crear Base de Datos en WampServer.
    - [x] Importar tablas.

## 🛠️ Fase 2: Desarrollo del Backend (Scraping & Datos)

- [x] **Implementación de Scrapers Básicos**
    - [x] Investigar estructura HTML de Liquipedia.
    - [x] Implementar `ValorantScraper` (Partidos, Scores, Status).
    - [x] Implementar `LolScraper` (Partidos, Scores).
    - [x] Implementar `Cs2Scraper` (Partidos, Scores).
- [x] **Gestión de Partidos**
    - [x] Guardado en Base de Datos (`MatchModel`).
    - [x] Evitar duplicados.
    - [x] Implementar borrado de partidos.
- [x] **Detalles de Partidos**
    - [x] Scraping de estadísticas detalladas (KDA/Agents para Valorant).
    - [x] Vista de detalles del partido (`match_details.php`).

## 🧠 Fase 3: Inteligencia y Lógica

- [ ] **Sistema de Predicción**
    - [ ] Definir algoritmo básico de predicción para `ai_prediction` (Simulado por ahora).
    - [ ] Implementar cálculo en el modelo o clase dedicada.

## 🎨 Fase 4: Frontend y Visualización

- [x] **Interfaz Básica**
    - [x] `views/home.php` funcional.
    - [x] Mostrar tabla de partidos reales desde la DB.
- [ ] **Mejoras de UI/UX**
    - [ ] Uso de Tailwind CSS para diseño premium.
    - [ ] Filtros por Región y Torneo.
    - [ ] Paginación o Scroll infinito.

## 🔍 Fase 5: Expansión de Datos (Equipos y Jugadores)

- [ ] **Equipos**
    - [ ] Crear tabla `teams`.
    - [ ] Scraper de Equipos (Logo, Nombre, Integrantes).
    - [ ] Vista de Detalle de Equipo.
- [ ] **Jugadores**
    - [ ] Crear tabla `players`.
    - [ ] Scraper de Jugadores (Foto, Rol, Stats).
    - [ ] Vista de Detalle de Jugador.

## 🏁 Fase 6: Pruebas y Despliegue

- [ ] **Verificación**
    - [ ] Probar flujo completo: Scraping -> Guardado DB -> Navegación.
    - [ ] Verificar funcionamiento en WampServer local.
