<?php
    /**
     * Template name: előadás
     */
    $duma    = new Dumaszihaz\Dumaszinhaz();
    $eloadas = $duma->getEloadasBySEO($_REQUEST['seo']);

    if ($eloadas === false){
        die('Nincs iyen előadás!');
    }
?>

    <div id="eloadas">
        <p class="cim"><h2><?php echo $eloadas->cim?></h2></p>
        <p class="alkotok">
            <?php if (is_array($eloadas->alkotok)):
                foreach ($eloadas->alkotok as $alkoto):?>
                    <a href="<?php echo get_bloginfo('url').'/fellepo/?seo='.$alkoto->seo?>"><?php echo $alkoto->nev?></a>,
                <?php   endforeach;
            endif;?>
        </p>
        <p class="kepek">
            <?php if (is_array($eloadas->kepek)):
                    foreach ($eloadas->kepek as $kep):?>
                        <img src="<?php echo $kep->thumb?>" />
            <?php   endforeach;
                  endif;?>
        </p>
        <p class="bevezeto"><?php echo $eloadas->bevezeto?></p>
        <p class="leiras"><?php echo $eloadas->leiras?></p>
    </div>