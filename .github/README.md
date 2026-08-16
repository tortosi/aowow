![logo](../static/images/logos/home.png)

# AoWoW para AzerothCore — fork de IceTracks WotLK

Fork de [AoWoW](https://github.com/azerothcore/aowow), el visor de base de datos de
World of Warcraft 3.3.5 (Wrath of the Lich King) para servidores privados basados en
[AzerothCore](https://www.azerothcore.org/). Esta misma base de código es la que corre
en [db.warcrafted.com](https://db.warcrafted.com), la base de datos del servidor
IceTracks WotLK.

No nos atribuimos ningún crédito por el diseño original ni el código base de AoWoW —
todo eso es obra de sus mantenedores y de la comunidad de AzerothCore. Este fork lo
adapta, corrige y mejora para funcionar bien sobre AzerothCore. Ver **[Créditos](#créditos)**.

## Índice

- [¿Qué trae este fork?](#qué-trae-este-fork)
- [Antes de empezar](#antes-de-empezar)
- [Instalación paso a paso](#instalación-paso-a-paso)
- [Visor 3D de personajes](#visor-3d-de-personajes)
- [Si algo falla](#si-algo-falla)
- [Créditos](#créditos)

## ¿Qué trae este fork?

Sobre el AoWoW original, este fork añade:

- **Arranca en PHP moderno.** El AoWoW original tenía varios bugs de compatibilidad con
  PHP 8.4 que impedían que arrancara. Aquí están corregidos.
- **La autenticación con AzerothCore de verdad funciona.** El modo de login unificado
  contra `acore_auth` (el mismo usuario/contraseña que el juego) traía un typo que
  causaba un error fatal en cualquier intento de inicio de sesión. Corregido — ver
  [Autenticación unificada](#8-opcional-autenticación-unificada-con-azerothcore) más abajo.
- **El Perfilador de personajes funciona correctamente** con la estructura de tablas de
  AzerothCore (el original interpretaba mal la especialización de talentos activa) y
  ya no se rompe si usas un cliente de WoW no inglés.
- **Rebranding completo y traducción al español** prácticamente terminada (el AoWoW
  original dejaba bastantes cadenas en inglés incluso con el idioma cambiado).
- **Un asistente de instalación guiado** (`php aowow --extract-client`) para la parte
  que históricamente da más problemas: extraer los datos del cliente de WoW. Sustituye
  un proceso manual propenso a errores por un asistente que hace las cosas en el orden
  correcto por ti.
- **SEO real**: descripción, Open Graph y URL canónica en cada ficha, y un
  `sitemap.xml` generado desde la base de datos — el AoWoW original apenas emitía un
  `<title>` y sus listados no eran rastreables por buscadores.
- **Visor 3D de personajes** en el Perfilador, en WebGL — ver
  [más abajo](#visor-3d-de-personajes).

La lista completa de bugs corregidos, con el porqué de cada uno, está en
**[docs/PARCHES.md](../docs/PARCHES.md)** — interesante si vas a hacer `git pull` del
proyecto original y quieres saber qué podría entrar en conflicto.

## Antes de empezar

### ¿Usas Windows?

Sí se puede — PHP, MySQL y Apache/nginx necesitan un entorno Linux, así que en Windows
10/11 se instala dentro de **WSL** (Windows Subsystem for Linux): te da un Linux real
corriendo dentro de Windows, sin necesidad de una máquina virtual aparte. Para
instalarlo, abre PowerShell **como administrador** y ejecuta:

```powershell
wsl --install
```

Reinicia el ordenador si te lo pide. Al terminar tendrás una aplicación "Ubuntu" en el
menú de inicio — ábrela y te dará una terminal Linux normal. A partir de ahí, sigue el
resto de esta guía **tal cual, sin ningún cambio**: dentro de WSL todos los comandos son
exactamente los mismos que en un servidor Linux.

Esta guía asume que ya tienes:

- Un servidor (o tu propio ordenador — con Linux, o con Windows + WSL como se explica
  arriba) donde puedas instalar programas y donde ya tengas montado tu servidor de
  AzerothCore (worldserver + authserver funcionando).
- Acceso a una terminal (línea de comandos) en ese servidor. Si nunca has usado una
  terminal: es una ventana donde escribes comandos de texto en vez de hacer clic en
  botones. Cada bloque de código gris de esta guía es algo que copias y pegas ahí,
  línea por línea.
- Los tres programas siguientes ya instalados: **PHP** (versión 8.0 o superior),
  **MySQL** (no vale MariaDB — algunas consultas usan comportamiento específico de
  MySQL) y **Git**.

Instala las extensiones de PHP que hacen falta (en Debian/Ubuntu):

```bash
sudo apt install php-gd php-xml php-mbstring -y
```

Y **desactiva la extensión Intl de PHP** si la tienes activa — esto no es opcional,
AoWoW no arranca con ella activa (explicación técnica en
[docs/TROUBLESHOOTING.md](../docs/TROUBLESHOOTING.md)):

```bash
sudo phpdismod intl
```

<details>
<summary>Si vas a extraer tú mismo los datos del cliente de WoW (audio, iconos, mapas…),
necesitas además esto — puedes saltarlo si alguien ya te pasa los datos extraídos</summary>

- `cmake`, para compilar la herramienta de extracción.
- [MPQExtractor](https://github.com/Sarjuuk/MPQExtractor) (Linux) o
  [MPQEditor](http://www.zezula.net/en/mpq/download.html) (Windows) — extraen archivos
  del cliente del juego.
- `ffmpeg` — reencodea el audio del juego a un formato que el navegador entienda.

En Windows, el asistente de este fork (paso 5 más abajo) detecta si tienes **WSL**
instalado y, si es así, puede hacer todo el proceso automáticamente por ti; si no,
te guía con herramientas nativas de Windows.

</details>

## Instalación paso a paso

Sigue estos pasos **en orden**. Cada uno explica qué hace el comando y qué deberías ver
si ha ido bien. Si algo no coincide con lo descrito, para ahí y mira
[Si algo falla](#si-algo-falla) antes de seguir.

### 1. Descargar el proyecto

"Clonar" significa descargar una copia del proyecto con su historial. Elige una carpeta
donde quieras instalarlo (por ejemplo `/var/www/`) y ejecuta:

```bash
git clone https://github.com/tortosi/aowow.git aowow
cd aowow
```

Esto crea una carpeta `aowow` con todos los archivos del proyecto, y `cd aowow` te mete
dentro de ella — el resto de comandos de esta guía se ejecutan desde ahí.

### 2. Crear las bases de datos

AoWoW necesita **4 bases de datos MySQL** para funcionar. Si ya tienes AzerothCore
instalado, seguramente ya tienes 3 de ellas (`acore_world`, `acore_auth`,
`acore_characters`) — solo te falta crear la cuarta, propia de AoWoW:

| Base de datos | ¿De dónde sale? | ¿Para qué la usa AoWoW? |
|---|---|---|
| `acore_aowow` | La crea este paso | Todo lo que AoWoW genera y muestra (objetos, hechizos, textos…) |
| `acore_world` | Ya la tienes (AzerothCore) | Leer objetos, criaturas, misiones, hechizos |
| `acore_auth` | Ya la tienes (AzerothCore) | Verificar usuario/contraseña al iniciar sesión |
| `acore_characters` | Ya la tienes (AzerothCore) | El Perfilador, para mostrar personajes reales |

Crea la base nueva y su estructura (te pedirá la contraseña de tu usuario MySQL):

```bash
mysql -u <tu_usuario_mysql> -p -e "CREATE DATABASE acore_aowow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u <tu_usuario_mysql> -p acore_aowow < setup/db_structure.sql
```

AoWoW necesita además una tabla que **no viene por defecto en una instalación estándar
de AzerothCore** (`spell_learn_spell`) — impórtala en tu base `acore_world` ya existente:

```bash
mysql -u <tu_usuario_mysql> -p acore_world < setup/spell_learn_spell.sql
```

Si algo de esto falla con un error de "acceso denegado", tu usuario de MySQL no tiene
permisos suficientes — pide a quien administre el servidor que te los dé, o usa el
usuario root de MySQL solo para estos comandos puntuales.

### 3. Dar permisos de escritura

El servidor web (Apache/nginx, el programa que sirve las páginas) necesita poder
**escribir** dentro de estas carpetas — AoWoW genera archivos ahí mismo (imágenes,
configuración, caché). Si tu servidor web corre como el usuario `www-data` (lo más
habitual en Debian/Ubuntu):

```bash
sudo chown -R www-data:www-data cache config static/download static/widgets static/js static/uploads static/images/wow datasets
```

Si no existen todavía algunas de esas carpetas, créalas primero con `mkdir -p`.

### 4. Configurar la conexión a las bases de datos

Este comando te va a hacer preguntas por pantalla (host, usuario, contraseña y nombre
de cada una de las 4 bases de datos del paso 2):

```bash
php aowow --database
```

Al terminar, genera el archivo `config/config.php` con esos datos. **Este archivo
contiene contraseñas — nunca lo subas a un repositorio ni lo compartas.**

### 5. Extraer los datos del cliente de WoW

Este es el paso que más dudas suele generar, así que este fork trae un asistente que lo
hace todo automáticamente — solo necesitas indicarle dónde está tu copia del juego:

```bash
php aowow --extract-client
```

Te preguntará la ruta a la carpeta `Data` de tu instalación de World of Warcraft (por
ejemplo `C:\Juegos\World of Warcraft\Data` en Windows, o la ruta equivalente si usas
Linux/Mac) y qué idioma(s) del juego quieres usar. A partir de ahí, todo es automático:
extrae los archivos necesarios en el orden correcto y convierte el audio al formato
correcto — no tienes que recordar nada de eso.

**Importante:** si solo vas a dar soporte a un idioma en tu web (lo más habitual), dilo
cuando `php aowow --setup` (siguiente paso) te pregunte por los idiomas activos. En
AoWoW, el idioma de la web y los datos extraídos del cliente son la misma cosa — activar
un idioma para el que no extrajiste datos rompe la web para cualquiera que lo use.

### 6. Configuración inicial y generación de todos los datos

```bash
php aowow --setup
```

Este es el paso más largo (puede tardar bastante rato, sobre todo generando mapas e
imágenes) pero también el más sencillo: es un asistente guiado que te va preguntando
todo lo que falta — configuración del sitio, generación de la base de datos, generación
de archivos, y creación de una cuenta de administrador. Si el proceso se corta a
mitad por cualquier motivo (se cierra la terminal, se cae la conexión…), puedes volver a
ejecutar `php aowow --setup` y continuará donde lo dejaste.

**Dos avisos importantes para más adelante, cuando ya conozcas el proyecto:** si algún
día ejecutas manualmente `php aowow --sql` o `--build` para regenerar solo una parte
concreta, **siempre** especifica cuál con `--sql=<tipo>` / `--build=<tipo>` — sin el
`=tipo`, el comando reconstruye *absolutamente todo* y puede tardar más de una hora. Y
nunca los interrumpas a mitad (ni les pongas un límite de tiempo): son procesos que
empiezan borrando tablas, y cortarlos a mitad las deja a medio borrar.

### 7. Comprobar que funciona

Abre la web en tu navegador. Si ves la portada con el buscador, ya está — el resto de
pasos son opcionales.

### 8. (Opcional) Autenticación unificada con AzerothCore

Si quieres que la gente inicie sesión en AoWoW con la misma cuenta que usa para jugar
(recomendado), configura esto con `php aowow --configure`:

```
ACC_AUTH_MODE = 3   (AUTH_MODE_ACORE)
```

A partir de ahí, cualquier cuenta que ya exista en tu `acore_auth` funciona en AoWoW en
cuanto esa persona inicia sesión ahí por primera vez — no hace falta crear cuentas ni
migrar nada a mano.

Para convertir a alguien en administrador: que esa persona inicie sesión una vez (para
que se cree su fila), y luego ejecuta en MySQL:

```sql
UPDATE aowow_account SET userGroups = 2 WHERE user = '<nombre_de_usuario>';
```

### 9. (Opcional) Perfilador de personajes

Activa `PROFILER_ENABLE = 1` con `php aowow --configure` para que la gente pueda ver las
fichas de sus propios personajes (equipo, talentos, logros…). No necesitas un servicio
aparte corriendo todo el rato: se activa solo cuando alguien pide ver un personaje.

Si tu reino tiene configurado `timezone = 1` en `acore_auth.realmlist`, AoWoW lo trata
como "reino de desarrollo" y solo lo ven los administradores — pon ahí la región real de
tu reino y regenera después:

```bash
php aowow --build=realms --force
php aowow --build=realmmenu --force
```

## Visor 3D de personajes

El Perfilador puede mostrar cada personaje en un visor 3D real en vez de un icono plano.
Es un módulo aparte, de acceso restringido — no viene incluido en este repositorio.
Todo lo demás (incluido el propio Perfilador) funciona perfectamente sin él, con un
retrato plano como alternativa automática.

Ver **[docs/VISOR-3D.md](../docs/VISOR-3D.md)** para más detalles y cómo conseguirlo.

## Si algo falla

Los problemas más habituales (extensión Intl, contraseñas con caracteres especiales,
página en blanco, caché de opcode duplicada…) están recogidos en
**[docs/TROUBLESHOOTING.md](../docs/TROUBLESHOOTING.md)**.

## Créditos

- El equipo original de **AoWoW** y la comunidad de **AzerothCore**, por el trabajo real
  de fondo que este fork solo adapta y corrige.
- @mix — script PHP original para analizar `.blp`/`.dbc`.
- @LordJZ — clase contenedora de DBSimple; base de la clase de usuario.
- @kliver — implementación base de subida de capturas de pantalla.
- @Sarjuuk — mantenimiento del proyecto AoWoW adaptado a AzerothCore.

Por favor, no consideres este proyecto un intento de apropiación indebida del trabajo
original: es "nos encantó vuestra base, y la hemos adaptado y corregido para que
funcione bien en nuestro propio servidor AzerothCore". Este proyecto no está pensado
para uso comercial.
