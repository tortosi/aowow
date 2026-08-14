<?php

if (!defined('AOWOW_REVISION'))
    die('illegal access');

if (!CLI)
    die('not in cli mode');


/*
    Genera el sitemap del sitio.

    Motivo: los listados (?items, ?spells, ?npcs …) se pintan con JavaScript a partir de un
    bloque de datos, así que el HTML servido no contiene ni un solo enlace a las fichas. Sin
    sitemap, un buscador que llega a la portada no tiene forma fiable de descubrir las ~155.000
    páginas de detalle que hay debajo.

    Salida (en la raíz del sitio, porque un sitemap solo puede declarar URLs que estén en su
    propia carpeta o por debajo):
        sitemap.xml                 índice que apunta a los demás
        sitemap-main.xml            portada, listados y herramientas
        sitemap-<tipo>-<n>.xml      fichas, troceadas por el límite del formato
*/

CLISetup::registerSetup("build", new class extends SetupScript
{
    protected $info = array(
        'sitemap' => [[], CLISetup::ARGV_PARAM, 'Generates sitemap.xml so crawlers can find pages the JS-driven listviews never link to.']
    );

    public $isOptional = true;                              // no forma parte de la cadena de instalación

    private const MAX_URLS = 45000;                         // el formato admite 50.000; se deja margen

    // tipo (tal cual aparece en la URL) => [tabla, prioridad]
    private const TYPES = array(
        'item'        => ['?_items',       '0.6'],
        'spell'       => ['?_spell',       '0.5'],
        'npc'         => ['?_creature',    '0.5'],
        'quest'       => ['?_quests',      '0.6'],
        'object'      => ['?_objects',     '0.4'],
        'achievement' => ['?_achievement', '0.5'],
        'itemset'     => ['?_itemset',     '0.5'],
        'zone'        => ['?_zones',       '0.6'],
        'faction'     => ['?_factions',    '0.5'],
        'title'       => ['?_titles',      '0.4'],
        'currency'    => ['?_currencies',  '0.4']
    );

    // páginas fijas: no salen de ninguna tabla, pero son la puerta de entrada de cada sección
    private const STATIC_PAGES = array(
        ''                => '1.0',
        '?items'          => '0.9',
        '?spells'         => '0.9',
        '?npcs'           => '0.9',
        '?quests'         => '0.9',
        '?objects'        => '0.8',
        '?achievements'   => '0.8',
        '?itemsets'       => '0.8',
        '?zones'          => '0.8',
        '?factions'       => '0.7',
        '?titles'         => '0.7',
        '?currencies'     => '0.7',
        '?classes'        => '0.7',
        '?races'          => '0.7',
        '?skills'         => '0.7',
        '?pets'           => '0.7',
        '?enchantments'   => '0.6',
        '?emotes'         => '0.5',
        '?sounds'         => '0.5',
        '?icons'          => '0.5',
        '?talent'         => '0.8',                         // calculadora de talentos
        '?petcalc'        => '0.7',                         // calculadora de mascotas
        '?maps'           => '0.6',
        '?events'         => '0.6',
        '?aboutus'        => '0.4',
        '?faq'            => '0.4'
    );

    private string $host = '';
    private array  $files = [];                             // sitemaps escritos, para el índice

    private function url(string $path, string $prio, string $freq) : string
    {
        return '  <url><loc>'.Util::htmlEscape($this->host.'/'.$path).'</loc>'.
               '<changefreq>'.$freq.'</changefreq><priority>'.$prio."</priority></url>\n";
    }

    private function writeSitemap(string $name, string $body) : bool
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
               '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n".
               $body.
               '</urlset>'."\n";

        if (!CLISetup::writeFile($name, $xml))
            return false;

        $this->files[] = $name;
        return true;
    }

    public function generate() : bool
    {
        $this->host  = rtrim(Cfg::get('HOST_URL'), '/');
        $this->files = [];

        if (!$this->host)
        {
            CLI::write('[sitemap] HOST_URL is not configured', CLI::LOG_ERROR);
            $this->success = false;
            return false;
        }

        // ------------------------------------------------ páginas fijas
        $buff = '';
        foreach (self::STATIC_PAGES as $path => $prio)
            $buff .= $this->url($path, $prio, 'weekly');

        if (!$this->writeSitemap('sitemap-main.xml', $buff))
        {
            $this->success = false;
            return false;
        }

        // ------------------------------------------------ fichas
        $total = count(self::STATIC_PAGES);
        foreach (self::TYPES as $type => [$table, $prio])
        {
            // se excluye lo que tampoco aparece en los listados del sitio (interno, deshabilitado…):
            // enviar a un buscador URLs que no queremos que indexe solo desperdicia presupuesto de rastreo
            $ids = DB::Aowow()->selectCol(
                'SELECT `id` FROM '.$table.' WHERE (`cuFlags` & ?d) = 0 ORDER BY `id` ASC',
                CUSTOM_EXCLUDE_FOR_LISTVIEW
            );

            if (!$ids)
            {
                CLI::write('[sitemap] no entries for type '.CLI::bold($type), CLI::LOG_WARN);
                continue;
            }

            $chunks = array_chunk($ids, self::MAX_URLS);
            foreach ($chunks as $i => $chunk)
            {
                $buff = '';
                foreach ($chunk as $id)
                    $buff .= $this->url('?'.$type.'='.$id, $prio, 'monthly');

                // se numera siempre, aunque haya un solo trozo: así el nombre no cambia al crecer los datos
                if (!$this->writeSitemap('sitemap-'.$type.'-'.($i + 1).'.xml', $buff))
                {
                    $this->success = false;
                    return false;
                }
            }

            $total += count($ids);
            CLI::write('[sitemap] '.CLI::bold($type).': '.count($ids).' urls in '.count($chunks).' file(s)', CLI::LOG_OK);
        }

        // ------------------------------------------------ índice
        $now  = date('Y-m-d');
        $buff = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
                '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($this->files as $f)
            $buff .= '  <sitemap><loc>'.Util::htmlEscape($this->host.'/'.$f).'</loc><lastmod>'.$now."</lastmod></sitemap>\n";

        $buff .= '</sitemapindex>'."\n";

        if (!CLISetup::writeFile('sitemap.xml', $buff))
        {
            $this->success = false;
            return false;
        }

        CLI::write('[sitemap] '.CLI::bold((string)$total).' urls total across '.count($this->files).' sitemap files', CLI::LOG_OK);

        return true;
    }
});

?>
