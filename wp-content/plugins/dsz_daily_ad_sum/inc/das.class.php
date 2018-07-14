<?php
    /**
     * Dumaszínház napi reklám összesítő
     *
     * Összesítí az eladható jegyek számát és kiírja egy táblázatban régióra és eladható jegyek számára rendezve
     */
    class DAS{
        /**
         * @var wpdb object
         */
        private $db;

        /**
         * Beállítás hibák
         *
         * @var string
         */
        private static $option_errror;

        /**
         * DAS constructor.
         */
        public function __construct(){
            global $wpdb;

            $this->db = $wpdb;
        }

        /**
         * Admin menü létrehozása a plugin beállítások oldalhoz
         */
        public static function add_admin_menu(){
            if (is_admin()){
                add_action('admin_init',array('DAS','register_settings'));
            }
            add_options_page('Napi hírdetés összesítő','Napi hírdetés összesítő','manage_options','dsz-daily-ad-sum',
                array('DAS','admin_options'));
        }

        /**
         * Kirakja a beállítások oldalt
         */
        public static function admin_options(){
            if (!current_user_can('manage_options')){
                wp_die(__('Access denied!'));
            }

            include_once(__DIR__.'/../template/options.phtml');
        }

        /**
         * Visszaadja a beállítás aktuális értékét
         *
         * @param string $name
         * @param string $default
         *
         * @return string
         */
        public static function get_option($name,$default=''){
            // küldött érték
            if (!empty($_POST[$name])){
                return $_POST[$name];
            }

            // mentett érték
            $option_name = esc_attr( get_option($name));
            if (!empty($option_name)){
                return $option_name;
            }

            // default érték
            if (!empty($default)){
                return $default;
            }

            // üres string
            return '';
        }

        /**
         * Visszaadja reklám összesítő alapján az első előadási id-ját
         *
         * @param string $location (: bp | * )
         *
         * @return int
         */
        public function get_lead_show_id($location){
            $tableData = $this->get_table_data($location);

            if (is_array($tableData)){
                foreach ($tableData as $row){
                    if ($row['gyerek_eloadas'] != '1' && $row['hatralevo_napok_szama'] >= 2){
                        return $row['id'];
                    }
                }
            }

            return 0;
        }

        /**
         * Kiírja a táblázatot
         *
         * @return string
         */
        public function print_table(){
            global $budapestiEloadasok, $videkiEloadasok;

            $budapestiEloadasok = $this->get_table_data('bp');
            $videkiEloadasok    = $this->get_table_data('videk');

            ob_start();
            include_once(__DIR__.'/../template/table.phtml');
            $out = ob_get_clean();

            echo $out;
        }

        /**
         * Regisztrálja a beállításokat
         */
        public static function register_settings(){
            register_setting('dsz-das-settings','dsz_das_emails');
            register_setting('dsz-das-settings','dsz_das_weeks_nr');
            register_setting('dsz-das-settings','dsz_das_daily_limit_multiplier');
        }

        /**
         * Elmenti a beállításokat
         */
        public static function save_options(){
            if ($_POST['save']){
                if (self::validate_options()){
                    update_option('dsz_das_emails',$_POST['dsz_das_emails']);
                    update_option('dsz_das_weeks_nr',$_POST['dsz_das_weeks_nr']);
                    update_option('dsz_das_daily_limit_multiplier',$_POST['dsz_das_daily_limit_multiplier']);

                    remove_action('send_daily_ad_sum',array('DAS','send_daily_ad_sum_mail'));
                    add_action('send_daily_ad_sum',array('DAS','send_daily_ad_sum_mail'));

                    echo '<div class="updated settings-error notice is-dissimble">
                        <p>
                            <strong>'.__('Beállítások elmentve!').'</strong>
                        </p>
                     </div>';
                }
                else{
                    echo '<div class="error settings-error notice is-dissimble">
                        <p>
                            <strong>'.__(self::$option_errror).'</strong>
                        </p>
                     </div>';
                }
            }
        }

        /**
         * Kiküldi a leveleket naponta a megfelelő email címekre
         */
        public static function send_daily_ad_sum_mail(){
            global $budapestiEloadasok, $videkiEloadasok;

            $DAS = new DAS();

            $budapestiEloadasok = $DAS->get_table_data('bp');
            $videkiEloadasok    = $DAS->get_table_data('videk');

            add_filter('wp_mail_content_type', function(){return 'text/html';});

            // beállítások lekérése
            $to         = explode(',',self::get_option('dsz_das_emails'));
            $subject    = 'Dumaszínház napi reklám összesítő';
            $headers    = 'From: Dumaszínház rendezvény <rendezveny@dumaszinhaz.hu>';

            ob_start();
            include_once(__DIR__.'/../template/table.phtml');
            $body = ob_get_clean();

            wp_mail($to, $subject, $body, $headers);
        }

        /**
         * Visszaadja a napi költési limitet
         *
         * Az eladható jegyek összértéke szorozva 0,07-tel és osztva az előadásig hátralévő napok számával
         *
         * @param array $musor
         *
         * @return int
         */
        private function get_koltesi_limit($musor){
            $dailyLimitMultiplier   = DAS::get_option('dsz_das_daily_limit_multiplier');

            $musorIdopont = new DateTime($musor->ido, new DateTimeZone('Europe/Budapest'));
            $aktIdopont   = new DateTime('now', new DateTimeZone('Europe/Budapest'));

            // az előadásig hátralévő napok száma
            $dayNr = $musorIdopont->diff($aktIdopont)->format('%d');

            $limit = ($musor->jegy_hu_elerheto_jegyek*$musor->ar*$dailyLimitMultiplier)/$dayNr;

            return number_format($limit,0,'',' ').' Ft';
        }

        /**
         * Visszaadja az előadásig hátralévő napok számát
         *
         * @param string $show_time
         *
         * @return int
         */
        private function get_left_days($show_time){
            $musorIdopont = new DateTime($show_time, new DateTimeZone('Europe/Budapest'));
            $aktIdopont   = new DateTime('now', new DateTimeZone('Europe/Budapest'));

            // az előadásig hátralévő napok száma
            $dayNr = $musorIdopont->diff($aktIdopont)->format('%d');

            return $dayNr;
        }


        /**
         * Visszaadja a műsor összértékét
         *
         * @param array $musor
         *
         * @return float
         */
        private function get_musor_osszertek($musor){
            $dayNr = $this->get_left_days($musor->ido);

            if ($dayNr > 0){
                // előadásig hátralévő napok számának a reciproka és az eladható jegyek összértékének szorzata
                $ertek = (1/$dayNr)*($musor->jegy_hu_elerheto_jegyek*$musor->ar);
            }
            else{
                $ertek = 0;
            }

            return !empty($ertek)?(int)$ertek:0;
        }

        /**
         * Visszaadja a táblázat sorokat
         *
         * @param string $locType a helyszín típusa (:bp, videk)
         *
         * @return string
         */
        private function get_table_data($locType){
            $rows       = array();
            $sortedRows = array();
            $osszertek  = array();

            $weeksNr                = DAS::get_option('dsz_das_weeks_nr');

            // olyan előadások, amik
            //      - x héten belül vannak
            //      - legalább 1 nap van még hátra a kezdésig
            //      - a $locType helyszínen lesznek
            $sql = "SELECT * FROM `musor`
                    WHERE
                                `jegy_hu_elerheto_jegyek` > 0
                            AND `ido` < '".date('Y-m-d H:i:s',strtotime('+'.$weeksNr.' week'))."'
                            AND `ido` >= '".date('y-m-d H:i:s',strtotime('+1 day'))."'
                            AND `varos` ".($locType == 'bp'?" = 'Budapest'":"!= 'Budapest'");
            $rs  = $this->db->get_results($sql);

            if (!empty($rs)){
                if (is_array($rs)){
                    foreach ($rs as $row){
                        $ertek       = $this->get_musor_osszertek($row);
                        $osszertek[] = $ertek;
                        $ido[]       = $row->ido;
                        $rows[] = array(
                            'id'                    => $row->id,
                            'osszertek'             => $ertek,
                            'eladhato_jegyek_szama' => $row->jegy_hu_elerheto_jegyek,
                            'koltesi_limit'         => $this->get_koltesi_limit($row),
                            'idopont'               => substr($row->ido,0,-3),
                            'helyszin'              => $row->helyszin_nev,
                            'cim'                   => $row->cim,
                            'gyerek_eloadas'        => $row->gyermekeloadas,
                            'hatralevo_napok_szama' => $this->get_left_days($row->ido)
                        );
                    }
                }
            }

            if (count( $osszertek ) > 0){
                // rendezés
                array_multisort($osszertek, SORT_DESC, $ido, SORT_ASC,$rows);

                // limit
                if (is_array($rows)){
                    $i = 0;
                    foreach ($rows as $row){
                        if ($i < 20){
                            $sortedRows[] = $row;
                        }
                        $i++;
                    }
                }
            }

            return $sortedRows;
        }

        /**
         * Ellenőrzi a napi költésoi limit szorzót
         *
         * @uses self::$option_error
         *
         * @return bool
         */
        private static function validate_daily_limit_multiplier(){
            $dlm = $_POST['dsz_das_daily_limit_multiplier'];

            if (!is_numeric($dlm)){
                self::$option_errror = __('Nem megfelelő szorzó lett beírva! Írj be egy 2 tizedesre kerekített számot (pl: 0.07)!');
                return false;
            }

            return true;
        }

        /**
         * Ellenőrzi az email címeket
         *
         * @uses self::$option_error
         *
         * @return bool
         */
        private static function validate_emails(){
            $emails = explode(',',$_POST['dsz_das_emails']);

            if (!count($emails)){
                self::$option_errror = __('Legalább 1 email címet kötelező megadni!');
                return false;
            }

            $email_accepted = 0;

            foreach ($emails as $email){
                if (strlen(trim($email))){
                    if (!filter_var(trim($email), FILTER_VALIDATE_EMAIL)){
                        self::$option_errror = __('Az email cím nem a megfelelő formátumban lett megadva: '.$email.'!');
                        return false;
                    }

                    $email_accepted++;
                }
            }

            if (!$email_accepted){
                self::$option_errror = __('Legalább 1 email címet kötelező megadni!');
                return false;
            }

            return true;
        }

        /**
         * Ellenőrzi a beállítások értékeit
         *
         * @return bool
         */
        private static function validate_options(){
            if (!self::validate_emails()){
                return false;
            }

            if (!self::validate_weeks_nr()){
                return false;
            }

            if (!self::validate_daily_limit_multiplier()){
                return false;
            }

            return true;
        }

        /**
         * Ellenőrzi a hetek számát
         *
         * @uses self::$option_error
         *
         * @return bool
         */
        private static function validate_weeks_nr(){
            if ((int)$_POST['dsz_das_weeks_nr'] != $_POST['dsz_das_weeks_nr']){
                self::$option_errror = __('A hetek száma nem a megfelelő formátumban lehett megadva (egész szám)!');
                return false;
            }

            if ($_POST['dsz_das_weeks_nr'] < 2 || $_POST['dsz_das_weeks_nr'] > 8){
                self::$option_errror = __('A hetek számának 2 és 8 között kell lennie!');
                return false;
            }

            return true;
        }
    }
