<?php
    /**
     * Template name: fellépő
     */
    $duma = new Dumaszihaz\Dumaszinhaz();
    $f    = $duma->getFellepoBySEO($_REQUEST['seo']);

    if ($f === false){
        die('A fellépő nem található!');
    }
?>
    <div id="fellepo">
        <p class="nev"><h2><?php echo $f->name?></h2></p>
        <p class="kepek">
            <?php if (is_array($f->kepek)):
                    foreach ($f->kepek as $kep):?>
                        <img src="<?php echo $kep->thumb?>" />
            <?php   endforeach;
                  endif;?>
        </p>
        <p class="info"><?php echo $f->bemutatkozas?></p>
    </div>

