<?php
    require_once('../../../wp-load.php');

    include_once('inc/class/jegyhu.api.class.php');
    include_once('inc/class/jegyhu.sync.class.php');

    $sync = new \jegyhu\sync();
    $sync->sync();