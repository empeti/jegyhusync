<?php
    /**
     * Template name: előadás lista
     */
    $duma = new Dumaszihaz\Dumaszinhaz();
    $eloadasok = $duma->getEloadasLista();

    if ($eloadasok === false){
        die('Nincsenek listázható előadások!');
    } ?>

    <div id="eloadas-lista">
        <?php foreach ($eloadasok as $e):?>
                <div class="eloadas">
                    <a class="cim"><a href="<?php echo get_bloginfo('url').'/eloadas/?seo='.$e->seo?>"><?php echo $e->cim?></a>
                    <p class="kep">
                        <?php if (is_array($e->kepek)):?>
                            <img src="<?php echo $e->kepek[0]->thumb?>" />
                        <?php endif?>
                    </p>
                    <p class="bevezeto"><?php echo $e->bevezeto?></p>
                    <p class="alkotok">
                        <?php if (is_array($e->alkotok)):
                                foreach ($e->alkotok as $alkoto):?>
                                    <a href="<?php echo get_bloginfo('url').'/fellepo/?seo='.$alkoto->seo?>"><?php echo $alkoto->nev?></a>,
                        <?php   endforeach;
                              endif;?>
                    </p>
                </div>
                <hr />
        <?php endforeach;?>
    </div>