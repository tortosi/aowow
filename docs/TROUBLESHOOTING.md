# Solución de problemas

**P: La extensión Intl de PHP está activa y el sitio no arranca / da error fatal en
`Locale`.**

R: Es un choque directo con la clase `Locale` propia de AoWoW. Desactívala:
`sudo phpdismod -s apache2 intl` (y `-s cli intl` si también usas el CLI). Si necesitas
Intl para otro proyecto en el mismo servidor con `mod_php` (un único proceso PHP
compartido por todos los sitios), la alternativa es migrar AoWoW a su propio pool
PHP-FPM en vez de desactivar Intl para todo el servidor.

**P: La página aparece en blanco, sin ningún estilo.**

R: El contenido estático (CSS, JS, imágenes) no se está sirviendo — o usas SSL y AoWoW
no lo detecta, o `STATIC_HOST` no está bien definido. Revísalo con
`php aowow --configure`.

**P: Error fatal: "No se puede heredar la función abstracta ... previamente
declarada".**

R: Tienes varios módulos de caché de opcode de PHP activos a la vez (Zend OPcache y
algún otro). Desactiva todos menos uno.

**P: No se pudo conectar a la base de datos.**

R: Revisa `config/config.php`. Si tu contraseña de MySQL contiene el carácter `#`,
sustitúyelo por su forma *URL-encoded* `%23` (y lo mismo con cualquier otro carácter
especial que dé problemas).

**P: Falta `Markup.js` / errores de consola sobre archivos JS no encontrados.**

R: A veces la configuración falla al copiar las plantillas
`setup/tools/filegen/templates/*.in` a `static/js/` por permisos. Comprueba que el
servidor web puede escribir en `static/js/` y relanza `php aowow --build=markup --force`.

**P: ¿Cómo consigo el visor 3D de personajes en el Perfilador?**

R: Es un módulo de distribución restringida, no incluido en este repositorio. Consulta
[`docs/VISOR-3D.md`](VISOR-3D.md). Si no lo instalas, el Perfilador cae automáticamente
a un retrato plano (icono de raza/género/clase) sin que el resto del sitio se vea
afectado.

**P: Solo hay datos para un idioma — ¿por qué no aparecen los demás?**

R: Es una decisión de configuración de cada instancia (`Cfg::LOCALES`). El idioma de
interfaz y el locale de los datos del cliente son la misma cosa en AoWoW — activar un
idioma sin haber extraído sus datos rompería la web para cualquiera que cayera en él. Si
quieres dar soporte a más idiomas, repite la extracción del cliente
(`php aowow --extract-client`) para cada locale adicional y añádelo a `LOCALES`.

**P: Ejecuté `php aowow --sql` o `--build` sin especificar el tipo y ahora tarda muchísimo /
el sitio está en mantenimiento.**

R: El CLI interpreta `--sql`/`--build` sin `=tipo` como "reconstruir absolutamente
todo lo registrado" — puede tardar más de una hora. Dejarlo terminar es la única opción
segura: cortarlo a mitad puede dejar tablas a medio `TRUNCATE`. Para la próxima vez, usa
siempre `--sql=<tipo>` / `--build=<tipo>` con el nombre exacto.
