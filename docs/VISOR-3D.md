# Visor 3D de personajes

El Perfilador (Herramientas → Perfiles) puede mostrar cada personaje en un visor 3D real
(WebGL, Three.js), en vez del retrato plano por defecto: modelo de la raza, equipo
compuesto sobre la textura del personaje, y las piezas ancladas a los huesos correctos
(casco, hombreras, armas).

## Las dos rutas de instalación

- **Solo lo público y gratuito** (lo que trae este repositorio): no tienes que hacer
  nada especial. Si sigues la instalación del [README principal](../.github/README.md)
  tal cual, el Perfilador funciona perfectamente y muestra un retrato plano (icono de
  raza/género/clase) en vez del visor 3D. El código ya detecta que el módulo no está
  instalado y cae automáticamente al retrato — no hay ningún paso extra que hacer ni
  nada que puedas dejar "a medias".
- **Con el visor 3D** (módulo de pago): un paso más, opcional, que se añade **encima**
  de una instalación ya funcionando. Es justo lo que documenta el resto de esta página.

## Por qué es de pago

Este módulo (el visor en sí, el endpoint que traduce personaje + equipo + datos del
cliente a algo renderizable, y las herramientas de extracción de modelos) es un
desarrollo propio considerable, así que su código no se distribuye públicamente: vive en
un repositorio privado aparte y se comparte con quien apoye el proyecto mediante una
donación.

## Cómo conseguirlo

Escríbenos por [Discord](https://discord.gg/hxUJtZWp6f) — te contamos cómo colaborar y
te añadimos como colaborador del repositorio privado `tortosi/aowow-visor3d`.

## Instalación paso a paso (una vez tengas acceso al repositorio privado)

Requisito: ya debes tener este repositorio (`tortosi/aowow`) instalado y funcionando —
si no, sigue primero el [README principal](../.github/README.md).

### 1. Clonar el módulo

Clónalo en una carpeta aparte, **no** dentro de tu instalación de AoWoW:

```bash
git clone git@github.com:tortosi/aowow-visor3d.git
```

(Necesitas que tu cuenta de GitHub tenga acceso al repo — es lo que te da la invitación
del paso anterior.)

### 2. Copiar los archivos a tu instalación

El repositorio del módulo reproduce las mismas rutas relativas que tu instalación de
AoWoW — solo tienes que copiar su contenido encima, respetando la estructura de
carpetas. Desde la carpeta del módulo que acabas de clonar:

```bash
cp -r includes/ajaxHandler/model3d.class.php  /ruta/a/tu/aowow/includes/ajaxHandler/
cp -r static/modelviewer3d                    /ruta/a/tu/aowow/static/
cp    setup/extract-client-models.sh          /ruta/a/tu/aowow/setup/
cp    setup/tools/extract-attachments.py      /ruta/a/tu/aowow/setup/tools/
```

Sustituye `/ruta/a/tu/aowow` por la carpeta real donde instalaste el proyecto (la que
creó `git clone` en el paso 1 del README principal).

**No hace falta tocar ni reiniciar nada más.** `index.php` y `static/js/Profiler.js` de
tu instalación ya traen el enganche necesario (son parte del repositorio público) y
detectan automáticamente que el visor ya está disponible.

### 3. Importar las tablas DBC que describen el aspecto del personaje

Esto usa una herramienta que **ya tienes** en tu instalación (viene en el repositorio
público, no en el módulo) — no son datos que vengan con la extracción normal del
cliente (`php aowow --extract-client`), así que hay que pedirlos aparte:

```bash
cd /ruta/a/tu/aowow
php aowow --dbc=charsections,charhairgeosets,characterfacialhairstyles,helmetgeosetvisdata
```

Esto necesita los archivos DBC del cliente ya extraídos en `setup/mpqdata/` (los mismos
que usó `--extract-client` en la instalación normal).

### 4. Extraer del cliente los modelos y texturas de personaje/equipo

Son varios GB solo de modelos y texturas (mucho más que el resto de la extracción del
cliente), así que es un paso aparte incluso teniendo el módulo instalado:

```bash
./setup/extract-client-models.sh '/ruta/a/tu/Wow/Data'
```

Sustituye `/ruta/a/tu/Wow/Data` por la carpeta `Data` de tu cliente de World of Warcraft
(la misma que usaste en el paso 5 del README principal). Recorre los mismos MPQ que
`--extract-client`, pero extrayendo `Character\`, `Item\ObjectComponents\` e
`Item\TextureComponents\`. Muestra progreso y tiempo estimado — puede tardar bastante,
es normal. El destino por defecto es `static/modeldata/`.

### 5. Generar los puntos de anclaje de armas, escudos y hombreras

```bash
python3 setup/tools/extract-attachments.py
```

Vuelca los 20 modelos de raza/género a `static/modeldata/attachments.json` — le dice al
visor a qué hueso y en qué posición va anclada cada pieza de equipo.

### 6. Comprobar que funciona

Con el [Perfilador activado](../.github/README.md#9-opcional-perfilador-de-personajes)
(`PROFILER_ENABLE = 1`), abre el perfil de un personaje real de tu servidor. Deberías
ver el visor 3D en vez del retrato plano. Si sigues viendo el retrato plano, revisa por
este orden: que los 4 archivos/carpetas del paso 2 llegaron a las rutas correctas, que
el paso 3 no dio ningún error, y que el paso 4 terminó sin errores (comprueba que
`static/modeldata/` no está vacío).

## Detalles que no son evidentes (no deshacer sin entenderlos)

- El geoset 0 es el cuerpo base y debe estar siempre visible; comparte grupo con los
  peinados, así que filtrarlo junto a ellos borra medio personaje.
- En casi todos los demás grupos la variante 1 es la de "sin equipo" y también debe
  dibujarse: es la que pinta manos y pies desnudos. Solo se ocultan por defecto los
  grupos que exigen llevar la pieza (tabardo, capa, brillo de ojos, hebilla).
- `charsections` tiene dos índices distintos fáciles de confundir: variación (rostro,
  peinado) y color (tono de piel, color de pelo); la cara se resuelve con variación de
  rostro + tono de piel.
- `charhairgeosets` traduce el número de peinado al id de geoset real, que no coinciden.
