# Parches aplicados en este fork

Correcciones de bugs reales encontrados en la adaptación de AoWoW a AzerothCore —
no son cambios de comportamiento ni de preferencia, son arreglos. Si en el futuro un
`git pull` de [azerothcore/aowow](https://github.com/azerothcore/aowow) trae cambios en
estos mismos archivos, revisa si el upstream ya los arregló (entonces puedes descartar
el parche local) o si hay que resolver el conflicto a mano.

- **`includes/user.class.php`**: `CFG_ACC_AUTH_MODE` (constante inexistente) →
  `Cfg::get('ACC_AUTH_MODE')` en `isValidName()`. Era un typo real del proyecto base al
  añadir `AUTH_MODE_ACORE`: causaba un error fatal en **cualquier intento de login** en
  modo AzerothCore.
- **`includes/profiler.class.php`**: el `specMask` de `character_talent` se convierte
  con `>> 1` antes de usarlo como índice. En AzerothCore `character_talent.specMask` es
  una **máscara de bits** (1 = spec 0), no un índice plano como asume el código
  original — sin este arreglo, los talentos se guardaban en la especialización
  equivocada.
- **`setup/tools/sqlgen/zones.ss.php`** y varios `setup/tools/sqlgen/*.ss.php` más: el
  proyecto base asume que `name_loc0` (inglés) de los datos DBC del cliente siempre está
  poblado, y lo usa para hacer `JOIN`/`LIKE`. Si usas un cliente **no inglés**, esa
  columna está vacía y esas consultas fallan en silencio o producen resultados
  corruptos — colapsaba por completo la sección de "Zonas" y dejaba visibles en el
  sitio público un montón de hechizos/objetos de prueba interna de Blizzard que
  deberían estar ocultos.
- **`includes/kernel.php`** / **`includes/utilities.php`**: varias correcciones de
  compatibilidad con PHP 8.4 (uso de la constante obsoleta `E_STRICT`, un
  `case E_USER_ERROR` duplicado que hacía que los errores reportados con
  `trigger_error(..., E_USER_ERROR)` se descartaran en silencio, y un manejador de
  errores fatales que marcaba como "Fatal Error" cualquier aviso de PHP aunque el
  script hubiera terminado con éxito).
- **`setup/tools/clisetup/dbconfig.us.php`**: el asistente de configuración de base de
  datos ya no marca la base `world` como desactualizada solo por tener versionado
  `ACDB` (AzerothCore) en vez de `TDB` (TrinityCore) — ese chequeo solo tenía sentido
  para TrinityCore.
- **`pages/genericPage.class.php`**: los scripts de página inyectados dinámicamente
  (`?data=...`) ahora sobreviven a un acierto de caché — antes se perdían porque vivían
  en una propiedad privada no serializada, rompiendo silenciosamente ~30 páginas del
  sitio cuando se servían desde caché.

## Otros ajustes menores (no bugs, decisiones de esta instancia)

- `pQueue`: si el siguiente elemento de la cola está programado a futuro, ahora
  **duerme** en vez de hacer `continue` inmediato (evitaba que el proceso girara a
  máxima CPU sin pausa).
- `setup/tools/filegen/statistics.ss.php`: corregido un desplazamiento de parámetros
  (dejaba `g_statistics.combo` siempre vacío, lo que abortaba la carga de todas las
  pestañas del Perfilador) y un desbordamiento de enteros sin signo al restar ajustes de
  estadísticas.
- `setup/tools/filegen/templates/locale.js.in`: el locale por defecto ya no es fijo
  `LOCALE_ENUS`, sino el primero disponible — necesario en instancias restringidas a un
  único idioma.
