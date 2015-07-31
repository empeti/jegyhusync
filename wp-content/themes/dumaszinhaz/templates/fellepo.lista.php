<?php
    /**
     * Template name: fellépő lista
     */
    $duma       = new Dumaszihaz\Dumaszinhaz();
    $fellepok   = $duma->getFellepoLista();

    if ($fellepok === false){
        die('Nincsenek fellépők!');
    }
?>
    <div id="fellepo-lista">
        <?php foreach ($fellepok as $f): ?>
            <div class="fellepo">
                <p class="kep"><img src="<?php echo $f->thumb?>" /></p>
                <p class="nev"><a href="<?php echo get_bloginfo('url').'/fellepo/?seo='.$f->seo?>"><?php echo $f->name?></a></p>
            </div>
        <?php endforeach;?>
    </div>