<?php
/**
 * Plugin name: Dumaszínház napi hírdetés összesítés
 *
 * Description: Ez a plugin küldi ki naponta a hírdetendő előadásokról a napi összesítést.
 *
 * Author: Mátyás Péter
 * Version: 0.1 béta
 */
    if ( !function_exists( 'add_action' ) ) {
        echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
        exit;
    }

    include_once(__DIR__.'/inc/das.class.php');

    // Új admin menü létrehozása
    add_action('admin_menu',array('DAS','add_admin_menu'));

    // WP cron mentés
    register_activation_hook(__FILE__,'add_send_mail_to_cron');

    /**
    * Hozzáadja a cron-hoz a levélküldés időzítését
    */
    function add_send_mail_to_cron(){
        wp_schedule_event(time(), 'daily', 'send_daily_ad_sum');
    }
