# Lista de Progreso del Proyecto MultiGame Stats

Este archivo rastrea el estado de todas las tareas necesarias para completar el proyecto.

## 📝 Notas

- Agregada la opcion de borrar todos los partidos de la base de datos.
- Quiero cambiar un poco el diseño de la pagina principal para que sea mas agradable de ver.
- Quiero que se pueda ordenar la tabla de partidos por fecha.
- Quiero distintas pestañas para cada juego

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

## 🛠️ Fase 2: Desarrollo del Backend (Scraping)

- [ ] **Implementación de Scrapers**
    - [ ] Investigar estructura HTML de Liquipedia.
    - [ ] Implementar `ValorantScraper` (lógica real con Guzzle/DomCrawler).
    - [ ] Implementar `LolScraper` (lógica real).
    - [ ] Implementar `Cs2Scraper` (lógica real).
- [ ] **Almacenamiento de Datos**
    - [ ] Verificar que `MatchModel` guarda correctamente los datos scrapeados.
    - [ ] Evitar duplicados al guardar partidos.

## 🧠 Fase 3: Inteligencia y Lógica

- [ ] **Sistema de Predicción**
    - [ ] Definir algoritmo básico de predicción para `ai_prediction`.
    - [ ] Implementar cálculo en el modelo o clase dedicada.

## 🎨 Fase 4: Frontend y Visualización

- [ ] **Interfaz de Usuario**
    - [ ] Mejorar diseño de `views/home.php`.
    - [ ] Mostrar tabla de partidos reales desde la DB.
    - [ ] Añadir estilos CSS básicos.

## 🏁 Fase 5: Pruebas y Despliegue

- [ ] **Verificación**
    - [ ] Probar flujo completo: Scraping -> Guardado DB -> Vista Home.
    - [ ] Verificar funcionamiento en WampServer local.
