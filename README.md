# ClutchData - MultiGame Stats Platform

Plataforma de estadísticas multijuego para Valorant, League of Legends y CS2.

## 📋 Descripción

ClutchData es una aplicación web desarrollada en **PHP 8.1+ nativo** con arquitectura **MVC** que realiza web scraping de datos de esports desde Liquipedia y VLR.gg, almacenándolos en MySQL y exponiéndolos a través de una **API REST**.

## 🚀 Requisitos

- PHP 8.1+
- MySQL/MariaDB
- Composer
- WAMP/XAMPP/LAMP

## ⚙️ Instalación

1. **Clonar o descargar** el proyecto en el directorio web:
   ```bash
   cd c:/wamp64/www
   git clone <repository-url> ClutchData
   ```

2. **Instalar dependencias** con Composer:
   ```bash
   cd ClutchData
   composer install
   ```

3. **Configurar base de datos**:
   - Crear base de datos `clutchdata_db`
   - Copiar `.env.example` a `.env` y configurar credenciales:
     ```
     DB_HOST=localhost
     DB_NAME=clutchdata_db
     DB_USER=root
     DB_PASS=
     ```

4. **Ejecutar migraciones** (crear tablas):
   ```bash
   php database/update_schema.php
   ```

5. **Acceder** a la aplicación:
   ```
   http://localhost/ClutchData/public/
   ```

## 📁 Estructura del Proyecto

```
/ClutchData
├── /public         # Punto de entrada web (index.php, assets)
├── /src            # Lógica de la aplicación
│   ├── /Classes    # Clases auxiliares (Scrapers, Router, Logger, Database)
│   ├── /Controllers# Controladores MVC
│   ├── /Interfaces # Interfaces (ScraperInterface)
│   └── /Models     # Modelos de datos (PDO)
├── /views          # Plantillas HTML/PHP
├── /tests          # Pruebas Unitarias (PHPUnit)
├── /logs           # Ficheros de log (Monolog)
├── /docs           # Documentación técnica
├── /database       # Scripts de migración
└── vendor/         # Librerías (Composer)
```

## 🛠️ Tecnologías

| Tecnología | Uso |
|------------|-----|
| PHP 8.1+ | Backend con tipado estricto |
| PDO | Conexión segura a MySQL |
| Guzzle | Cliente HTTP para scraping |
| DOMCrawler | Parser DOM para extracción |
| Monolog | Sistema de logs |
| PHPUnit | Testing unitario |

## 🌐 API REST

### Endpoints Disponibles

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/matches` | Lista partidos (filtros: game, region, status, limit) |
| GET | `/api/match?id={id}` | Detalle de partido |
| GET | `/api/teams` | Lista equipos |
| GET | `/api/team?name={name}&game={game}` | Detalle de equipo |
| GET | `/api/stats` | Estadísticas generales |

### Ejemplo de Respuesta

```json
{
  "success": true,
  "count": 25,
  "filters": {
    "game": "valorant",
    "region": null,
    "status": null
  },
  "data": [...]
}
```

### Cliente de Prueba

```bash
php api_client_test.php
```

## 🧪 Testing

Ejecutar pruebas unitarias:

```bash
./vendor/bin/phpunit
```

## 📊 Funcionalidades

- ✅ Web Scraping desde Liquipedia (Valorant, LoL, CS2)
- ✅ Scraping enriquecido desde VLR.gg
- ✅ Arquitectura MVC con Front Controller
- ✅ Base de datos relacional con PDO
- ✅ Sentencias preparadas (SQL Injection prevention)
- ✅ API REST con respuestas JSON
- ✅ Sistema de logs con Monolog
- ✅ Filtros por juego, región y estado
- ✅ Tipado estricto (PSR-12)

## 👨‍💻 Autor

ClutchData Team - Proyecto Final DWES

## 📄 Licencia

Este proyecto es para uso educativo.
