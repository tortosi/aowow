<?php

if (!defined('AOWOW_REVISION'))
    die('illegal access');

if (!CLI)
    die('not in cli mode');


/****************************************/
/* Extract client data (MPQ) end-to-end */
/****************************************/

CLISetup::registerUtility(new class extends UtilityScript
{
    public $optGroup = CLISetup::OPT_GRP_SETUP;

    public const COMMAND      = 'extract-client';
    public const DESCRIPTION  = 'Extract required data from your WoW 3.3.5 client (MPQ) and re-encode audio, guided end-to-end.';
    public const PROMPT       = 'This walks you through extracting the client data AoWoW needs, without having to memorize file order or ffmpeg flags.';
    public const NOTE_ERROR   = 'could not complete extraction:';
    public const REQUIRED_DB  = [];

    // known valid client locales: lowercase code (used for setup/mpqdata/<x>) => Blizzard's on-disk folder casing
    private const LOCALES = [
        'enus' => 'enUS', 'eses' => 'esES', 'dede' => 'deDE',
        'frfr' => 'frFR', 'ruru' => 'ruRU', 'zhcn' => 'zhCN',
    ];

    // folders pulled from every single MPQ, in order (later MPQs overwrite same-named files from earlier ones on disk)
    private const EXTRACT_PATTERNS = [
        'DBFilesClient\*',
        'Interface\WorldMap\*',
        'Interface\FrameXML\GlobalStrings.lua',
        'Interface\TalentFrame\*',
        'Interface\Glues\Credits\*',
        'Interface\Icons\*',
        'Interface\Spellbook\*',
        'Interface\PaperDoll\*',
        'Interface\GLUES\CHARACTERCREATE\*',
        'Interface\Pictures\*',
        'Interface\PvPRankBadges\*',
        'Interface\FlavorImages\*',
        'Interface\Calendar\Holidays\*',
        'Sound\*',
    ];

    // base MPQs, directly under <clientData>/, fixed order
    private const BASE_MPQ = ['common', 'common-2', 'expansion', 'lichking', 'patch', 'patch-2', 'patch-3', 'patch-A'];

    // locale MPQs, under <clientData>/<BlizzLocaleCode>/, fixed order, %s = Blizzard locale code
    private const LOCALE_MPQ = ['locale-%s', 'expansion-locale-%s', 'lichking-locale-%s', 'patch-%s', 'patch-%s-2', 'patch-%s-3'];

    private $extractorBin = '';
    private $clientPath   = '';

    public function run(&$args) : bool
    {
        if (OS_WIN)
            return $this->runWindows();

        return $this->runUnix();
    }


    /*************/
    /* Windows   */
    /*************/

    private function runWindows() : bool
    {
        $wslOk = $this->detectWSL();

        CLI::write('Windows detectado.', CLI::LOG_INFO);

        if ($wslOk)
        {
            CLI::write('[W] Usar WSL (automático, recomendado)');
            CLI::write('[G] Instrucciones guiadas (manual, sin WSL)');

            if (!CLI::read(['c' => ['Elige una opción', false, true, '/^[wg]$/i']], $ui) || !$ui)
                return false;

            if (strtolower($ui['c']) == 'w')
                return $this->runViaWSL();
        }
        else
            CLI::write('WSL no está disponible en este equipo (no se detectó el ejecutable, o no hay ninguna distribución instalada) — se muestran instrucciones manuales.', CLI::LOG_WARN);

        return $this->printGuidedInstructions();
    }

    // WSL is only "usable" if the binary exists AND at least one distro is actually registered
    private function detectWSL() : bool
    {
        exec('where wsl 2>NUL', $_, $whereCode);
        if ($whereCode !== 0)
            return false;

        exec('wsl -l -q 2>&1', $distros, $listCode);
        $distros = array_filter(array_map('trim', $distros));

        return $listCode === 0 && !empty($distros);
    }

    private function runViaWSL() : bool
    {
        CLI::write('Relanzando dentro de WSL...', CLI::LOG_INFO);

        // translate the current project path to its WSL mount equivalent (C:\foo\bar -> /mnt/c/foo/bar)
        $winPath = str_replace('\\', '/', getcwd());
        $wslPath = preg_replace_callback('#^([A-Za-z]):#', fn($m) => '/mnt/'.strtolower($m[1]), $winPath);

        passthru('wsl bash -c '.escapeshellarg('cd '.escapeshellarg($wslPath).' && php aowow --extract-client'), $code);

        return $code === 0;
    }

    private function printGuidedInstructions() : bool
    {
        CLI::write();
        CLI::write('Extracción manual en Windows:', CLI::LOG_INFO);
        CLI::write(' 1. Instala MPQEditor: http://www.zezula.net/en/mpq/download.html', CLI::LOG_BLANK);
        CLI::write(' 2. Abre, en orden, cada uno de estos archivos de Data\\ con MPQEditor y extrae las', CLI::LOG_BLANK);
        CLI::write('    carpetas indicadas más abajo hacia setup\\mpqdata\\<localeCode-en-minúsculas>\\,', CLI::LOG_BLANK);
        CLI::write('    sobrescribiendo si te lo pregunta: '.implode(', ', self::BASE_MPQ).' (.MPQ),', CLI::LOG_BLANK);
        CLI::write('    y luego los de Data\\<TuLocale>\\: '.implode(', ', array_map(fn($p) => sprintf($p, '<TuLocale>'), self::LOCALE_MPQ)).' (.MPQ)', CLI::LOG_BLANK);
        CLI::write(' 3. Carpetas a extraer de cada archivo: '.implode(', ', self::EXTRACT_PATTERNS), CLI::LOG_BLANK);
        CLI::write(' 4. Reencodifica cada .wav extraído en Sound\\ a ogg/vorbis con FFmpeg para Windows', CLI::LOG_BLANK);
        CLI::write('    (http://ffmpeg.zeranoe.com/builds/), con el flag "-f ogg" — es obligatorio, sin', CLI::LOG_BLANK);
        CLI::write('    él la conversión falla en silencio. Ejemplo: ffmpeg -i archivo.wav -f ogg archivo.wav_', CLI::LOG_BLANK);
        CLI::write(' 5. Si alguna imagen sale distorsionada o con problemas de canal alfa, conviértela con', CLI::LOG_BLANK);
        CLI::write('    BLPConverter (https://github.com/PatrickCyr/BLPConverter) — AoWoW prioriza .png sobre .blp', CLI::LOG_BLANK);
        CLI::write();

        return true;
    }


    /********************/
    /* macOS / Linux    */
    /********************/

    private function runUnix() : bool
    {
        if (!$this->checkTools())
            return false;

        if (!$this->ensureExtractor())
            return false;

        if (!$this->promptClientPath())
            return false;

        $locales = $this->promptLocales();
        if (!$locales)
            return false;

        if (!$this->confirmSummary($locales))
        {
            CLI::write('Cancelado.', CLI::LOG_INFO);
            return false;
        }

        foreach ($locales as $locale)
        {
            if (!$this->extractLocale($locale))
                return false;

            if (!$this->reencodeAudio($locale))
                return false;
        }

        CLI::write();
        CLI::write('Extracción completa.', CLI::LOG_OK);
        CLI::write('Siguiente paso: php aowow --database (si no lo has hecho ya) y luego php aowow --setup', CLI::LOG_INFO);

        return true;
    }

    private function checkTools() : bool
    {
        $required = ['git' => 'sudo apt install git', 'cmake' => 'sudo apt install cmake', 'ffmpeg' => 'sudo apt install ffmpeg'];
        $missing  = [];

        foreach ($required as $bin => $installHint)
        {
            exec('command -v '.escapeshellarg($bin).' 2>/dev/null', $_, $code);
            if ($code !== 0)
                $missing[$bin] = $installHint;
        }

        // at least one C/C++ compiler
        $hasCompiler = false;
        foreach (['cc', 'gcc', 'clang'] as $bin)
        {
            exec('command -v '.escapeshellarg($bin).' 2>/dev/null', $_, $code);
            if ($code === 0)
            {
                $hasCompiler = true;
                break;
            }
        }
        if (!$hasCompiler)
            $missing['gcc'] = 'sudo apt install build-essential';

        if ($missing)
        {
            CLI::write('Faltan herramientas necesarias:', CLI::LOG_ERROR);
            foreach ($missing as $bin => $hint)
                CLI::write("  {$bin} -> {$hint}", CLI::LOG_BLANK);

            return false;
        }

        return true;
    }

    private function ensureExtractor() : bool
    {
        $default = 'tools-external/MPQExtractor';

        if (!CLI::read(['p' => ['Ruta a MPQExtractor ya compilado (vacío para clonar y compilar uno nuevo en '.$default.')', false, false, '/.*/']], $ui))
            return false;

        $path = trim($ui['p'] ?? '');

        if ($path)
        {
            $bin = rtrim($path, '/').'/build/bin/MPQExtractor';
            if (!is_file($bin))
            {
                CLI::write('No se encontró un binario en '.$bin, CLI::LOG_ERROR);
                return false;
            }

            $this->extractorBin = $bin;
            return true;
        }

        $bin = $default.'/build/bin/MPQExtractor';
        if (is_file($bin))
        {
            CLI::write('Ya existe un MPQExtractor compilado en '.$bin.', reutilizando.', CLI::LOG_INFO);
            $this->extractorBin = $bin;
            return true;
        }

        CLI::write('Clonando y compilando MPQExtractor en '.$default.'...', CLI::LOG_INFO);

        if (!is_dir($default))
        {
            exec('git clone https://github.com/Sarjuuk/MPQExtractor.git '.escapeshellarg($default).' 2>&1', $out, $code);
            if ($code !== 0)
            {
                CLI::write('Fallo al clonar el repositorio.', CLI::LOG_ERROR);
                return false;
            }
        }

        exec('cmake -S '.escapeshellarg($default).' -B '.escapeshellarg($default.'/build').' 2>&1', $out, $code);
        if ($code !== 0)
        {
            CLI::write('Fallo al configurar cmake.', CLI::LOG_ERROR);
            return false;
        }

        exec('cmake --build '.escapeshellarg($default.'/build').' 2>&1', $out, $code);
        if ($code !== 0 || !is_file($bin))
        {
            CLI::write('Fallo al compilar MPQExtractor.', CLI::LOG_ERROR);
            return false;
        }

        CLI::write('MPQExtractor compilado correctamente.', CLI::LOG_OK);
        $this->extractorBin = $bin;

        return true;
    }

    private function promptClientPath() : bool
    {
        while (true)
        {
            if (!CLI::read(['p' => ['Ruta a la carpeta Data\\ del cliente de WoW', false, false, '/.+/']], $ui) || !$ui)
                return false;

            $path = rtrim(trim($ui['p']), '/');

            if (!is_file($path.'/common.MPQ'))
            {
                CLI::write('No se encontró common.MPQ en '.$path.' — ¿es la carpeta Data\\ correcta?', CLI::LOG_ERROR);
                continue;
            }

            $this->clientPath = $path;
            return true;
        }
    }

    // returns array of lowercase locale codes, or [] on cancel
    private function promptLocales() : array
    {
        CLI::write('Locales disponibles: '.implode(', ', array_keys(self::LOCALES)), CLI::LOG_INFO);

        if (!CLI::read(['l' => ['¿Cuál(es) extraer? (separados por coma)', false, false, '/.+/']], $ui) || !$ui)
            return [];

        $picked = array_filter(array_map('trim', explode(',', strtolower($ui['l']))));
        $valid  = [];

        foreach ($picked as $code)
        {
            if (!isset(self::LOCALES[$code]))
            {
                CLI::write("'{$code}' no es un locale válido, se ignora.", CLI::LOG_WARN);
                continue;
            }

            if (!is_dir($this->clientPath.'/'.self::LOCALES[$code]))
            {
                CLI::write("No se encontró la carpeta ".self::LOCALES[$code]." dentro de Data\\ — se ignora {$code}.", CLI::LOG_WARN);
                continue;
            }

            $valid[] = $code;
        }

        return $valid;
    }

    private function confirmSummary(array $locales) : bool
    {
        CLI::write();
        CLI::write('Resumen:', CLI::LOG_INFO);
        CLI::write('  Cliente: '.$this->clientPath, CLI::LOG_BLANK);
        CLI::write('  Locales: '.implode(', ', $locales), CLI::LOG_BLANK);
        foreach ($locales as $locale)
            CLI::write('  Destino: setup/mpqdata/'.$locale.'/', CLI::LOG_BLANK);
        CLI::write('  Esto puede tardar bastante (varios minutos por locale).', CLI::LOG_BLANK);

        foreach ($locales as $locale)
        {
            $dest = 'setup/mpqdata/'.$locale;
            if (is_dir($dest) && (new FilesystemIterator($dest))->valid())
            {
                if (!CLI::read(['c' => [$dest.' ya tiene contenido. ¿Sobrescribir? (s/n)', false, true, '/^[sn]$/i']], $ui) || !$ui || strtolower($ui['c']) != 's')
                    return false;
            }
        }

        return CLI::read(['c' => ['¿Empezar la extracción? (s/n)', false, true, '/^[sn]$/i']], $ui) && $ui && strtolower($ui['c']) == 's';
    }

    private function extractLocale(string $locale) : bool
    {
        $dest      = 'setup/mpqdata/'.$locale.'/';
        $blizzCode = self::LOCALES[$locale];

        if (!is_dir($dest))
            mkdir($dest, 0755, true);

        $files = self::BASE_MPQ;
        foreach (self::LOCALE_MPQ as $pattern)
            $files[] = $blizzCode.'/'.sprintf($pattern, $blizzCode);

        $n = 0;
        foreach ($files as $rel)
        {
            $n++;
            $src = $this->clientPath.'/'.$rel.'.MPQ';

            if (!is_file($src))
            {
                CLI::write("  [{$n}/".count($files)."] omitido, no existe: ".$rel.'.MPQ', CLI::LOG_WARN);
                continue;
            }

            CLI::write("  [{$n}/".count($files)."] extrayendo de ".$rel.'.MPQ...', CLI::LOG_BLANK);

            $cmd = escapeshellarg($this->extractorBin);
            foreach (self::EXTRACT_PATTERNS as $pattern)
                $cmd .= ' -e '.escapeshellarg($pattern);
            $cmd .= ' -f -o '.escapeshellarg($dest).' '.escapeshellarg($src).' 2>&1';

            exec($cmd, $out, $code);

            if ($code !== 0)
            {
                CLI::write('Fallo extrayendo '.$rel.'.MPQ:', CLI::LOG_ERROR);
                CLI::write(implode("\n", $out), CLI::LOG_BLANK);
                return false;
            }
        }

        CLI::write('Locale '.$locale.' extraído.', CLI::LOG_OK);

        return true;
    }

    private function reencodeAudio(string $locale) : bool
    {
        $soundDir = 'setup/mpqdata/'.$locale.'/Sound';
        if (!is_dir($soundDir))
        {
            CLI::write('Sin carpeta Sound para '.$locale.', se omite el reencodeo.', CLI::LOG_INFO);
            return true;
        }

        CLI::write('Reencodificando audio a ogg/vorbis para '.$locale.'...', CLI::LOG_INFO);

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($soundDir, FilesystemIterator::SKIP_DOTS));
        $n  = 0;

        foreach ($it as $file)
        {
            if (strtolower($file->getExtension()) != 'wav')
                continue;

            $n++;
            $dst = $file->getPathname().'_';

            // -f ogg is mandatory: without it ffmpeg can't infer the output format from the non-standard ".wav_" extension and fails silently
            exec('ffmpeg -y -i '.escapeshellarg($file->getPathname()).' -f ogg '.escapeshellarg($dst).' 2>&1', $out, $code);

            if ($code !== 0 || !is_file($dst))
            {
                CLI::write('Fallo reencodificando '.$file->getPathname(), CLI::LOG_ERROR);
                return false;
            }

            if ($n % 200 === 0)
                CLI::write('  ...'.$n.' archivos reencodificados', CLI::LOG_BLANK);
        }

        CLI::write($n.' archivos de audio reencodificados para '.$locale.'.', CLI::LOG_OK);

        return true;
    }
});

?>
