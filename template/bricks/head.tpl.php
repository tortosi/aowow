<?php $meta = $this->getMeta(); ?>
    <meta charset="UTF-8">
    <?php /* la maquetación es de ancho fijo (min-width 980px, sin media queries): se declara ese
             ancho para que el móvil lo reduzca a escala en vez de desbordarse horizontalmente */ ?>
    <meta name="viewport" content="width=1024">
    <title><?=Util::htmlEscape(implode(' - ', $this->title)); ?></title>
    <meta name="description" content="<?=Util::htmlEscape($meta['description']); ?>">
<?php if ($meta['noIndex']): ?>
    <meta name="robots" content="noindex, follow">
<?php endif; ?>
    <link rel="canonical" href="<?=Util::htmlEscape($meta['canonical']); ?>" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="SHORTCUT ICON" href="<?=Cfg::get('STATIC_URL'); ?>/images/logos/favicon.ico" />
    <link rel="search" type="application/opensearchdescription+xml" href="<?=Cfg::get('STATIC_URL'); ?>/download/searchplugins/aowow.xml" title="Aowow" />

    <?php /* tarjeta al compartir el enlace en redes sociales, Discord, etc. */ ?>
    <meta property="og:site_name" content="<?=Util::htmlEscape($meta['siteName']); ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="<?=Util::htmlEscape($meta['ogLocale']); ?>">
    <meta property="og:title" content="<?=Util::htmlEscape($meta['title']); ?>">
    <meta property="og:description" content="<?=Util::htmlEscape($meta['description']); ?>">
    <meta property="og:url" content="<?=Util::htmlEscape($meta['canonical']); ?>">
    <meta property="og:image" content="<?=Util::htmlEscape($meta['image']); ?>">
    <meta name="twitter:card" content="<?=$meta['smallImage'] ? 'summary' : 'summary_large_image'; ?>">
    <meta name="twitter:title" content="<?=Util::htmlEscape($meta['title']); ?>">
    <meta name="twitter:description" content="<?=Util::htmlEscape($meta['description']); ?>">
    <meta name="twitter:image" content="<?=Util::htmlEscape($meta['image']); ?>">
<?php
foreach ($this->css as [$type, $css]):
    if ($type == SC_CSS_FILE):
        echo '    <link rel="stylesheet" type="text/css" href="'.$css."\" />\n";
    elseif ($type == SC_CSS_STRING):
        echo '    <style type="text/css">'.$css."</style>\n";
    endif;
endforeach;
?>
    <script type="text/javascript">
        var g_serverTime = new Date('<?=date(Util::$dateFormatInternal); ?>');
        var g_staticUrl = "<?=Cfg::get('STATIC_URL'); ?>";
        var g_host = "<?=Cfg::get('HOST_URL'); ?>";
<?php
if ($this->gDataKey):
        echo "        var g_dataKey = '".$_SESSION['dataKey']."'\n";
endif;
?>
    </script>
<?php
foreach ($this->js as [$type, $js]):
    if ($type == SC_JS_FILE):
        echo '    <script type="text/javascript" src="'.$js."\"></script>\n";
    elseif ($type == SC_JS_STRING):
        echo '    <script type="text/javascript">'.$js."</script>\n";
    endif;
endforeach;
?>
    <script type="text/javascript">
        var g_user = <?=Util::toJSON($this->gUser, JSON_UNESCAPED_UNICODE); ?>;
<?php
if ($this->gFavorites):
    echo "        g_favorites = ".Util::toJSON($this->gFavorites).";\n";
endif;
?>
    </script>

<?php
if (Cfg::get('ANALYTICS_USER')):
?>
    <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

        ga('create', '<?=Cfg::get('ANALYTICS_USER'); ?>', 'auto');
        ga('send', 'pageview');
    </script>
<?php
endif;
?>
