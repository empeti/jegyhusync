<?php
    /**
     * Template name: műsor
     */
    $duma = new \Dumaszihaz\Dumaszinhaz();

    if (empty($_REQUEST['seo'])){
        die('Nincs ilyen előadás');
    }

    $musor = $duma->getMusorBySEO($_REQUEST['seo']);

    // nem volt meg a műsor
    if ($musor === false){
        die ('Nincs ilyen előadás!');
    }
?>

<?php // megvan a műsor, rakjuk ki?>
    <div id="musor">
        <h2 class="cim"><?php echo $musor->cim?></h2>
        <p class="idopont"><?php echo $musor->ido?></p>
        <p class="helyszin"><?php echo $musor->helyszin?></p>
        <p class="kepek">
            <?php // kiemelt kép ?>
            <img src="<?php echo $musor->kiemelt_kep?>" />

            <?php // előadás képei ?>
            <?php if (is_array($musor->eloadas_kepek)):
                    foreach ($musor->eloadas_kepek as $eKep):?>
                        <img src="<?php echo $eKep->thumb?>" />
            <?php   endforeach;
                  endif; ?>

            <?php // alkotók képei ?>
            <?php if (is_array($musor->alkotok)):
                    foreach ($musor->alkotok as $alkoto):
                        if (is_array($alkoto->kepek)):
                            foreach ($alkoto->kepek as $aKep):?>
                                <img src="<?php echo $aKep->thumb?>" />
            <?php           endforeach;
                        endif;
                    endforeach;
                  endif; ?>
        </p>
        <p class="alkotók">
            <?php if (is_array($musor->alkotok)):
                    foreach ($musor->alkotok as $alkoto):?>
                        <a href="<?php echo get_bloginfo('url').'/fellepo/?seo='.$alkoto->seo?>"><?php echo $alkoto->nev?></a>,
            <?php   endforeach;
                  endif; ?>
        </p>
        <p class="info">
            <?php echo $musor->informacio?>
        </p>
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
