<?php
    namespace Dumaszihaz;

    class Dumaszinhaz{
        /**
         * Konstruktor
         *
         * @uses $wpdb
         */
        public function __construct(){
            global $wpdb;

            $this->wpdb = $wpdb;
        }

        /**
         * Lekéri az előadás adatait seo kód alapján
         *
         * @param string $seo
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return object | false
         */
        public function getEloadasBySEO($seo){
            try{
                $sql = "SELECT * FROM `eloadas` WHERE `seo` LIKE %s";
                $rs  = $this->wpdb->get_row($this->wpdb->prepare($sql, $seo));

                // wpdb hiba
                if ($rs === false) {
                    throw new \Exception($this->wpdb->last_error);
                }

                // nincs ilyen seo-val előadás mentve
                if ($rs === null){
                    return false;
                }

                $rs->alkotok = $this->getEloadasAlkotok($rs->id);
                $rs->kepek   = $this->getEloadasKepek($rs->id);

                return $rs;
            }
            catch (\Exception $e){
                $this->log($e->getFile().' hiba a '.$e->getLine().'. sorban:'.$e->getMessage());
                return false;
            }
        }

        /**
         * Visszaadja az előadások listáját
         *
         * @uses self::$wpdb
         *
         * @return array|bool
         * @throws \Exception
         */
        public function getEloadasLista(){
            try{
                $sql = "SELECT * FROM `eloadas` ORDER BY `cim`";
                $rs = $this->wpdb->get_results($sql);

                // wpdb hiba
                if ($rs === false) {
                    throw new \Exception($this->wpdb->last_error);
                }

                // nincs semmi az eloadas táblában
                if (!count($rs)) {
                    return false;
                }

                $eloadasok = array();

                foreach ($rs as $e){
                    $e->kepek       = $this->getEloadasKepek($e->id);
                    $e->alkotok     = $this->getEloadasAlkotok($e->id);
                    $eloadasok[]    = $e;
                }

                return $eloadasok;
            }
            catch (\Exception $e){
                $this->log($e->getFile().' hiba a '.$e->getLine().'. sorban:'.$e->getMessage());
                return false;
            }
        }

        /**
         * Lekéri a fellépő adatait seo kód alapján
         *
         * @param string $seo
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return object | false
         */
        public function getFellepoBySEO($seo){
            try {
                $sql = "SELECT * FROM `alkoto` WHERE `seo` LIKE %s";
                $rs = $this->wpdb->get_row($this->wpdb->prepare($sql, $seo));

                // wpdb hiba
                if ($rs === false) {
                    throw new \Exception($this->wpdb->last_error);
                }

                // nincs ilyen seo-val alkotó mentve
                if ($rs === null){
                    return false;
                }

                $rs->kepek = $this->getAlkotoKepek($rs->id);

                return $rs;

            }
            catch (\Exception $e){
                $this->log($e->getFile().' hiba a '.$e->getLine().'. sorban:'.$e->getMessage());
                return false;
            }
        }

        /**
         * Visszaadja a fellépők listáját
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return array | false
         */
        public function getFellepoLista(){
            try {
                $sql = "SELECT * FROM `alkoto` a JOIN `alkoto_kepek` ak ON ak.`alkoto_id` = a.`id` WHERE ak.`listakep` = '1' ORDER BY a.`name`";
                $rs = $this->wpdb->get_results($sql);

                // wpdb hiba
                if ($rs === false) {
                    throw new \Exception($this->wpdb->last_error);
                }

                // nincs semmi a fellépő táblában
                if (!count($rs)) {
                    return false;
                }

                $fellepok = array();

                foreach ($rs as $fellepo) {
                    $fellepok[] = $fellepo;
                }

                return $fellepok;
            }
            catch (\Exception $e){
                $this->log($e->getFile().' hiba a '.$e->getLine().'. sorban:'.$e->getMessage());
                return false;
            }
        }

        /**
         * SEO név alapján visszaadja a műsor adatait
         *
         * @param string $seo
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return array
         */
        public function getMusorBySEO($seo){
            try{
                $sql = "SELECT * FROM `musor` m  JOIN `eloadas` e ON e.`id` = m.`eloadas_id` WHERE m.`seo` LIKE %s";
                $rs  = $this->wpdb->get_row($this->wpdb->prepare($sql, $seo));

                // wpdb hiba
                if ($rs === false){
                    throw new \Exception($this->wpdb->last_error);
                }

                // nincs ilyen előadás
                if ($rs === null){
                    return false;
                }

                // előadás képek
                $rs->eloadas_kepek = $this->getEloadasKepek($rs->eloadas_id);

                // előadás alkotók
                $rs->alkotok = $this->getEloadasAlkotok($rs->eloadas_id, true);

                // következő előadás
                $rs->kovetkezo_eloadas = $this->getKovetkezoEloadas($rs);

                return $rs;
            }
            catch (\Exception $e){
                $this->log($e->getFile().' hiba a '.$e->getLine().'. sorban:'.$e->getMessage());
                return false;

                echo '';
            }

        }

        /**
         * Visszaadja a műsorok listáját
         *
         * @throws \Exception
         *
         * @uses self::$wpdb
         *
         * @return array
         */
        public function getMusorLista(){
            try{
                $musorok = array();

                $sql = "SELECT * FROM `musor` WHERE `ido` > NOW() ORDER BY `ido` ASC";
                $rs  = $this->wpdb->get_results($sql);

                if ($rs === false){
                    throw new \Exception($this->wpdb->last_error);
                }

                foreach ($rs as $musor){
                    $musor->alkotok           = $this->getEloadasAlkotok($musor->eloadas_id, false);
                    $musor->kovetkezo_eloadas = $this->getKovetkezoEloadas($musor);
                    $musorok[]                = $musor;
                }

                return $musorok;

            }
            catch (\Exception $e){
                $this->log($e->getFile().' hiba a '.$e->getLine().'. sorban:'.$e->getMessage());
                return false;
            }
        }

        /**
         * Visszaadja az alkotóhoz tartozó képeket
         *
         * @param int   $alkotoId   az alkotó id-ja
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return array
         */
        private function getAlkotoKepek($alkotoId){
            $kepek = array();

            $sql = "SELECT * FROM `alkoto_kepek` WHERE `alkoto_id` = '".$alkotoId."'";
            $rs  = $this->wpdb->get_results($sql);

            if ($rs === false){
                throw new \Exception($this->wpdb->last_error);
            }

            foreach ($rs as $kep){
                $kepek[] = $kep;
            }

            return $kepek;
        }

        /**
         * Visszaadja az előadáshoz tartozó alkozókat
         *
         * @param int   $eloadasId
         * @param bool  $kepek      kellenek-e a képek
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return array
         */
        private function getEloadasAlkotok($eloadasId, $kepek=true){
            $alkotok = array();

            $sql = "SELECT * FROM `eloadas_alkoto` ea JOIN `alkoto` a ON a.`id` = ea.`alkoto_id` WHERE `eloadas_id` = '".$eloadasId."'";
            $rs  = $this->wpdb->get_results($sql);

            if ($rs === false){
                throw new \Exception($this->wpdb->last_error);
            }

            foreach ($rs as $alkoto){
                // ha kellenek a képek töltsük be őket
                if ($kepek){
                    $alkoto->kepek = $this->getAlkotoKepek($alkoto->id);
                }

                $alkotok[]     = $alkoto;
            }

            return $alkotok;
        }

        /**
         * Visszaadja az előadás képeit
         *
         * @param $eloadasId
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return array | false
         */
        private function getEloadasKepek($eloadasId){
            // nincs meg az előadás id, vagy üres
            if (empty($eloadasId)){
                throw new \Exception('Nincs megadva az előadás id-ja!');
            }

            // nem szám lett átadva előadás id-nak
            if (!is_numeric($eloadasId)){
                throw new \Exception('Az előadás id-ja csak szám lehet ('.$eloadasId.' /'.gettype($eloadasId).'/ lett átadva)!');
            }

            $sql = "SELECT * FROM `eloadas_kepek` WHERE `eloadas_id` = %d";
            $rs  = $this->wpdb->get_results($this->wpdb->prepare($sql, $eloadasId));

            // wpdb hiba
            if ($rs === false){
                throw new \Exception($this->wpdb->last_error);
            }

            // nincs kép ehhez az előadáshoz
            if (!count($rs)){
                return false;
            }

            $kepek = array();

            foreach ($rs as $kep) {
                $kepek[] = $kep;
            }

            return $kepek;

        }

        /**
         * Visszaadja a következő alőadást adott programból, ha elfogyott a jegy, vagy elmarad az előadás
         *
         * @param object $musor
         *
         * @return object | false
         */
        private function getKovetkezoEloadas($musor){
            // ha nem fogyott el a jegy és nem marad el az előadás sem, akkor nem kell lekérni a következő előadást
            if ($musor->jegy_elfogyott == 0 && $musor->jegy_hu_status == 1){
                return false;
            }

            $sql = "SELECT * FROM `musor` WHERE `eloadas_id` = '".$musor->eloadas_id."' AND `ido` > '".$musor->ido."' AND `jegy_elfogyott` = '0' AND `jegy_hu_status` = '1' ORDER BY `ido` ASC LIMIT 1";
            $rs  = $this->wpdb->get_row($sql);

            return $rs;
        }

        /**
         * Kiírja a hibákat egy log fileban
         *
         * @param string $txt
         */
        private function log($txt){
            error_log(date('Y.m.d H:i').':'.$txt."!\r\n",3,ABSPATH.'/wp-content/themes/dumaszinhaz/logs/error.log');
        }
    }