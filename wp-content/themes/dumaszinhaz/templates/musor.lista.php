<?php
    /**
     * Template name: musor lista
     */
    $duma    = new Dumaszihaz\Dumaszinhaz();
    $musorok = $duma->getMusorLista();?>

    <div id="musor-lista">

<?php   if (is_array($musorok)){
            foreach ($musorok as $musor){ ?>
                <div class="musor">
                    <p class="kep"><img src="<?php echo $musor->kiemelt_kep?>" /></p>
                    <p class="cim"><a href="<?php echo get_bloginfo('url').'/musor/?seo='.$musor->seo?>"><?php echo $musor->cim?></a></p>
                    <p class="idopont"><?php echo $musor->ido?></p>
                    <p class="helyszin"><?php echo $musor->helyszin?></p>
                    <p class="alkotok">
                        <?php   if (is_array($musor->alkotok)):
                                    foreach ($musor->alkotok as $alkoto):?>
                                        <a href="<?php echo get_bloginfo('url').'/fellepo/'.$alkoto->seo?>"><?php echo $alkoto->nev?></a>
                        <?php      endforeach;
                                endif;?>
                    </p>
                    <p class="info"><?php echo $musor->informacio?></p>

                    <?php // nem marad el az előadás, és van még rá jegy
                          if ($musor->jegy_elfogyott == 0 && $musor->jegy_hu_status == 1):?>
                            <p class="jegyvasarlas"><a href="http://dumaszinhaz.jegy.hu/arrivalorder.php?eid=<?php echo $musor->jegy_hu_id?>&template=201311_vasarlas" target="_blank">jegyvásárlás</a></p>

                   <?php // elmarad az előadás
                        elseif ($musor->jegy_hu_status == 3):?>
                            <p class="eloadas-elmarad">Elmarad az előadás</p>

                   <?php // elfogyott a jegy
                        elseif ($musor->jegy_elfogyott == 1): ?>
                              <p class="jegy-elfogyott">Minden jegy elkelt</p>
                    <?php endif;?>

                </div>
                <hr />
<?php       }
        } ?>
    </div>
