<?php
    namespace jegyhu;

    class sync{
        /**
         * @var array a programok helyszínei egy tömbben jegy.hus program id alapján legyűjtve
         */
        var $addresses;

        /**
         * @var array a db-ben lévő előadások timestampjei egy tömbben jegy_hu_id-ra indexelve
         */
        var $existedEventsTS;

        /**
         * @var array ide gyűjtjük a jegy.hu API-ból listázott előadásokat, hogy tudjuk mik törlődtek
         */
        var $eventsInAPIList;

        /**
         * @var array ide gyűjjük a színészek jegy.hu id-jához tartozó belső id-kat
         */
        var $personIDs;

        /**
         * @var array ide gyűjtjük a programokhoz tartozó jegyárakat
         */
        var $prices;


        /**
         * @var array ide gyűjtjük az előadások jegy.hu id-jához tartozó belső id-kat
         */
        var $programIDs;


        /**
         * Konstruktor
         */
        public function __construct(){
            global $wpdb;

            $this->API  = new API();
            $this->wpdb = $wpdb;
        }

        /**
         * Ez a function kezeli a teljes szinkront
         *
         * @throws \Exception
         */
        public function sync(){
            try {
                $this->log(' ************* Szinkron indul ************* ');

                // módosítsuk az adatbázist, ha szükséges
                $this->updateDB();
                $this->loadExistedEventsTS();
                $this->syncEvents();
                $this->saveLastSync();
            }
            catch (\Exception $e){
                $this->log($e->getFile().' hiba a '.$e->getLine().'. sorban: '.$e->getMessage());
            }
            //finally {
                $this->log(' ************* Szinkron vége ************* '."\r\n");
            //}
        }

        /**
         * Események szinkronizálása
         *
         * @throws \Exception
         */
        public function syncEvents(){
            $events   = array();
            $programs = array();

            $params = array('venues'=>array('Dumaszínház'),'per_page'=>500);

            $results = $this->API->getEventList($params);

            /**
             * Ellenőrzi az eredményt
             */
            if (!is_array($results['payload'])){
                throw new \Exception(' Nincsenek meg az események!');
            }

            if (!is_array($results['payload']['Events'])){
                throw new \Exception(' Nincsenek meg az események!');
            }


            /**
             * Feldolgozás
             */
            if (is_array($results)){
                foreach ($results['payload']['Events'] as $event){
                    $events[]                = $event;
                    $this->eventsInAPIList[] = $event['NetEvent_Id'];


                    // csak akkor kell szinkronolni a programokat, ha az előadás timestampje kisebb, mint a
                    // jegy.hu api-ban található timestamp
                    if ($this->isEventTSUpdated($event['LastModMax'],$event['NetEvent_Id'])){
                        // program id - minden programot csak egyszer szinronizáljunk
                        if (!in_array($event['NetProgram_Id'],$programs)){
                            $programs[] = $event['NetProgram_Id'];
                        }
                    }
                }

                $this->deleteEvents();    // műsorszámok törlése API alapján
                $this->syncPrograms($programs);  // előadások
                $this->saveEvents($events);      // műsorok
                $this->setAlkotoStatus();
            }
        }

        /**
         * Szinkronolja a programokat (volt repertoár menüpont) a jegy.hu szerverről
         *
         * @param array $programs
         *
         * @throws \Exception
         */
        public function syncPrograms($programs){
            $this->setProgramIDs();

            if (empty($programs)){
                throw new \Exception('Nincsenek megadva a szinkronolandó programok!');
            }

            if (!is_array($programs)){
                throw new \Exception('A programook id-jai rossz formátumban lettek megadva!');
            }

            foreach ($programs as $programId){
                $params = array(
                    'netprogram_id' => $programId
                );

                $program = $this->API->getProgram($params);

                // előadás frissítése
                $this->saveEloadas($program);

                // képek frissítése
                $this->saveEloadasKepek($program);

                // közreműködők frissítése
                $this->saveEloadasAlkotok($program);
            }
        }

        /**
         * Sync venues from jegy.hu server
         *
         * @uses self::$wpdb
         *
         * @throws \Exception ha vmi hiba történt a szinkron folyamán
         */
        public function syncVenues(){
            $this->log('Helyszín szinkron indul');

            $params = array(
                'city'          => '',
                'order_by_name' => true
            );

            $venuesInDB   = $this->getVenuesFromDB();
            $jegyHuVenues = $this->API->getVenueList($params);

            if (!is_array($venuesInDB)){
                $venuesInDB = array();
            }

            /**
             * Megnézi van-e hiba az eredményben
             */
            if (!is_array($jegyHuVenues)){
                throw new \Exception('Nincsenek meg az előadóhelyek!');
            }

            if (!is_array($jegyHuVenues['payload'])){
                throw new \Exception('Nincsenek meg az előadóhelyek!');
            }

            if (!is_array($jegyHuVenues['payload']['venue_list'])){
                throw new \Exception('Nincsenek meg az előadóhelyek!');
            }

            if (!count($jegyHuVenues['payload']['venue_list'])){
                throw new \Exception('Nincsenek meg az előadóhelyek!');
            }

            /**
             * Nem volt hiba, indulhat a feldolgozás
             */
            foreach ($jegyHuVenues['payload']['venue_list'] as $nev => $adatok){
                $existsInDB = false;

                if (!empty($venuesInDB[$adatok['id']])){
                    $existsInDB = true;
                }

                if ($existsInDB){
                    $sql = "UPDATE ";
                }
                else{
                    $sql = "INSERT INTO ";
                }

                $sql .= " `helyszinek` SET
                            `jegy_hu_id`    = '".$this->_e($adatok['id'])."',
                            `nev`           = '".$this->_e($adatok['name'])."',
                            `varos`         = '".$this->_e($adatok['city'])."',
                            `cim`           = '".$this->_e($adatok['address'])."',
                            `telefon`       = '".$this->_e($adatok['Phone'])."',
                            `web`           = '".$this->_e($adatok['Website'])."',
                            `email`         = '".$this->_e($adatok['Email'])."',
                            `google_map`    = '".$this->_e($adatok['map'])."',
                            `last_mod`      = NOW()";

                if ($existsInDB){
                    $sql .= " WHERE `jegy_hu_id` = '".$adatok['id']."'";
                }

                if ($this->wpdb->query($sql) === false){
                    throw new \Exception($this->wpdb->last_error);
                }
            }

            // Felszabadítja a memóriát
            unset($venuesInDB);
            unset($jegyHuVenues);

            $this->log('Helyszín szinkron vége');
        }

        /**
         * Escape-eli a string-et mysql parancs fogadásához
         *
         * @param string $str
         *
         * @return string
         */
        private function _e($str){
            return $this->wpdb->_real_escape($str);
        }

        /**
         * Átállítja a jegy.hu API-ból már kimaradt előadások státuszát -1-re
         */
        private function deleteEvents(){
            if (is_array($this->eventsInAPIList)){
                $sql = "UPDATE `musor`
                        SET `status` = '-1'
                        WHERE
                                `ido` > '".date('Y-m-d H:i:s')."'
                            AND `jegy_hu_id` NOT IN ('".implode("','",$this->eventsInAPIList)."')";
                $this->wpdb->query($sql);
            }
        }

        /**
         * Visszaadja az alkotó id-ját jegy.hu Actor_Id alapján
         *
         * @param int $actorId
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return int
         */
        private function getAlkotoIdByActorId($actorId){
            $sql = "SELECT `id` FROM `alkoto` WHERE `actor_id` = %d";
            $rs = $this->wpdb->get_row($this->wpdb->prepare($sql, $actorId));
            if ($rs === false){
                throw new \Exception($this->wpdb->last_error.'!');
            }

            return $rs->id;
        }

        /**
         * Visszaadja az aktív alkotók id-jeit egy tömbben
         *
         * @return array
         */
        private function getActiveAlkotoIds(){
            $ids = array();

            $sql = "SELECT
                        ea.`alkoto_id`
                   FROM
                             `eloadas_alkoto` ea
                        JOIN `musor` m ON m.`eloadas_id` = ea.`eloadas_id`
                   WHERE
                        m.`ido` > NOW()";
            $rs = $this->wpdb->get_results($sql);

            foreach ($rs as $row){
                $ids[] = $row->alkoto_id;
            }

            return $ids;
        }

        /**
         * Visszaadja a local adatbázisban szereplő előadóhelyek jegy.hu id-ját egy tömbben
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return array
         */
        private function getVenuesFromDB(){
            $ids = array();

            $sql = "SELECT `id`,`jegy_hu_id`,`last_mod` FROM `helyszinek`";
            if (!$rs  = $this->wpdb->get_results($sql)){
                throw new \Exception($this->wpdb->last_error.'!');
            }

            if (!is_array($rs)){
                throw new \Exception('Nincs meg a lekérés eredménye!');
            }

            foreach ($rs as $row){
                $ids[$row->jegy_hu_id] = $row;
            }

            return $ids;
        }

        /**
         * Megnézi az alkotó létezik-e már a rendszerben jegy.hu Actor_Id alapján
         *
         * @param int $actorId
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return bool
         */
        private function isAlkotoExists($actorId){
            $sql = "SELECT COUNT(*) as nr FROM `alkoto` WHERE `actor_id` = %d";
            if (!$rs = $this->wpdb->get_row($this->wpdb->prepare($sql,$actorId))){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' Hiba a '.__LINE__.'. sorban: '.$this->wpdb->last_error);
            }

            return $rs->nr > 0?true:false;
        }

        /**
         * Megnézi szerepel-e már az adatbázisban ez a műsor
         *
         * @param int $NetEvent_Id
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return bool
         */
        private function isEventExists($NetEvent_Id){
            /**
             * Adatok ellenőrzése
             */
            if (empty($NetEvent_Id)){
                throw new \Exception('Nincs megadv a keresett NetEvent_Id!');
            }

            if (!is_numeric($NetEvent_Id)){
                throw new \Exception('A NetEvvent_Id csak szám lehet('.$NetEvent_Id.')!');
            }

            $sql = "SELECT COUNT(*) as nr FROM `musor` WHERE `jegy_hu_id` = %d";
            if (!$rs = $this->wpdb->get_row($this->wpdb->prepare($sql,$NetEvent_Id))){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' Hiba a '.__LINE__.' sorban: '.$this->wpdb->last_error);
            }

            return $rs->nr > 0?true:false;
        }

        /**
         * Megnézi van-e ilyen mező a táblában
         *
         * @param string $field
         * @param string $table
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         *
         * @return bool
         */
        private function isFieldInTable($field, $table){
            $sql = "SHOW FIELDS FROM `".$table."`";
            $rs  = $this->wpdb->get_results($sql);

            if (mysql_error()){
                throw new \Exception(mysql_error().'!');
            }

            foreach ($rs as $row){
                if ($row->Field == $field){
                    return true;
                }
            }

            return false;
        }

        /**
         * Megnézi módosult-e a timestamp az eventhez
         *
         * @param int $lastModMax
         * @param int $netEventId
         *
         * @return bool
         */
        private function isEventTSUpdated($lastModMax, $netEventId){
            if (empty($this->existedEventsTS[$netEventId])){
                return true;
            }

            if ($lastModMax > $this->existedEventsTS[$netEventId]){
                return true;
            }

            return false;
        }

        private function loadExistedEventsTS(){
            $sql = "SELECT `jegy_hu_id`, `ts`, `status` FROM `musor` WHERE `ido` > '".date('Y-m-d H:i:s')."'";
            $rs  = $this->wpdb->get_results($sql);

            if (is_array($rs)){
                foreach ($rs as $row){
                    // ha az előadás státusza -1, mindenképp frissíteni kell
                    $ts = $row->status == -1 ? 0 : $row->ts;
                    $this->existedEventsTS[$row->jegy_hu_id] = $ts;
                }
            }
        }

        /**
         * Beír a logba egy sort
         *
         * @param string $msg
         */
        private function log($msg){
            if (!empty($msg)){ // üres sorokat ne írjunk :)
                error_log(date('Y.m.d H:i:s').': '.$msg."\r\n",3,dirname(__FILE__).'/jegyhu.log');
            }
        }

        /**
         * Normalizálja a megadott stringet seo-hoz
         *
         * @param string $txt
         *
         * @return string a normalizálst string
         */
        private function normalize($txt){
            $accents    = array('á','é','í','ó','ö','ő','ú','ü','ű','Á','É','Í','Ó','Ö','Ő','Ú','Ü','Ű',' ',':','.',',');
            $nonAccents = array('a','e','i','o','o','o','u','u','u','A','E','I','O','O','O','U','U','U','-','','','');

            $i = 0;

            foreach ($accents as $accent){
                $txt = str_replace($accent,$nonAccents[$i],$txt);
                $i++;
            }

            return $txt;
        }

        /**
         * Törli az előadás képeit
         *
         * @param int $eloadasId
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function removeEloadasKepek($eloadasId){
            if (empty($eloadasId)){
                throw new \Exception('Nincs megadva az előadás id-ja!');
            }

            $sql = "DELETE FROM `eloadas_kepek` WHERE `eloadas_id` = '".$this->_e($eloadasId)."'";
            if ($this->wpdb->query($sql) === false){
                throw new \Exception($this->wpdb->last_error);
            }
        }

        /**
         * Átnevez egy táblát
         *
         * @param $oldName
         * @param $newName
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function renameTable($oldName, $newName){
            $sql = "SHOW TABLES FROM `".$this->wpdb->dbname."`";
            if (!$rs = $this->wpdb->get_results($sql)){
                throw new \Exception('Nincsenek meg a táblák a db-ben ('.$this->wpdb->dbname.')');
            }

            if (is_array($rs)){
                foreach ($rs as $row){
                    if($row->{'Tables_in_'.$this->wpdb->dbname} == $oldName){
                        $sql = "RENAME TABLE `".$this->_e($oldName)."` TO `".$this->_e($newName)."`";
                        if (!$this->wpdb->query($sql)){
                            throw new \Exception($this->wpdb->last_error." ".$sql);
                        }
                    }
                }
            }
        }

        /**
         * Elmenti / módosítja  az alkotót
         *
         * @param array $creator
         *
         * @throws \Exception
         */
        private function saveAlkoto($creator){
            echo '';

            /**
             * Adatok ellenőrzése
             */
            if (empty($creator)){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' Hiba a '.__LINE__.' sorban: nincsenek megadva a creator adatok!');
            }

            if (!is_array($creator)){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' Hiba a '.__LINE__.' sorban: a megadott creator adatok nem tömbben vannak megadva!');
            }

            // adatok lekérése a jegy.hu szerverről
            $person = $this->API->getPerson(array('person_id'=>$creator['Actor_Id']));

            if (empty($person['payload'])){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' Hiba a '.__LINE__.' sorban: Nincsenek meg a person adatok a jegy.hu szerverről!'.print_R($person,true));
            }

            $p = $person['payload'];

            if (!count($person['payload'])){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' Hiba a '.__LINE__.' sorban: Nincsenek meg a person adatok a jegy.hu szerverről!'.print_R($person,true));
            }

            /**
             * Minden rendben, mehet a mentés
             */
            if ($this->isAlkotoExists($creator['Actor_Id'])){
                $sql    = "UPDATE ";
                $where  = " WHERE `actor_id` = '".$this->_e($creator['Actor_Id'])."'";
            }
            else{
                $sql    = "INSERT INTO ";
                $where  = "";
            }

            $sql .= " `alkoto` SET
                            `actor_id`      = '".$this->_e($creator['Actor_Id'])."',
                            `name`          = '".$this->_e($creator['LastName'].' '.$creator['FirstName'])."',
                            `seo`           = '".$this->_e($p['name_url'])."',
                            `city`          = '',
                            `url`           = '',
                            `blog`          = '',
                            `twitter`       = '',
                            `facebook`      = '',
                            `youtube`       = '',
                            `email`         = '',
                            `txt`           = '',
                            `bemutatkozas`  = '".$this->_e($p['info'])."',
                            `onkritika`     = '',
                            `vendeg`        = '".(preg_match('/Dumasz&iacute;nh&aacute;z t&aacute;rsulat&aacute;nak tagja/i',$p['info'])?'0':'1')."',
                            `status`        = '1'";
            $sql .= $where;

            if ($this->wpdb->query($sql) === false){
                throw new \Exception(' '.$this->wpdb->last_error.' '.$sql);
            }

            $alkotoId = $this->getAlkotoIdByActorId($creator['Actor_Id']);

            // képek mentése
            $this->saveAlkotoKepek($alkotoId, $p);

            $this->personIDs[$creator['Actor_Id']] = $alkotoId;
        }

        /**
         * Elmenti az alkotókhoz tartozó képeket
         *
         * @param int    $alkotoId
         * @param array  $p a jegy.hu-ból API által visszaadott adatok
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function saveAlkotoKepek($alkotoId, $p){
            /**
             * Adatok ellenőrzése
             */
            if (empty($alkotoId)){
                throw new \Exception(' Nincs megadva az alkotó id-ja!');
            }

            if (!is_numeric($alkotoId)){
                throw new \Exception(' Az alkotó id-ja, csak szám lehet! ('.$alkotoId.')');
            }

            if (empty($p)){
                throw new \Exception(' Nincsenek megadva a jegy.hu-s alkotó adatok!');
            }

            if (!is_array($p)){
                throw new \Exception(' A jegy.hu-s alkotó adatok nem a megfelelő formátumban (tömb) lett megadva!'.print_r($p,true));
            }

            /**
             * Minden ok, mehet a feldolgozás
             */

            // képek törlése
            $sql = "DELETE FROM `alkoto_kepek` WHERE `alkoto_id` = %d";
            $rs  = $this->wpdb->query($this->wpdb->prepare($sql,$alkotoId));
            if ($rs === false){
                throw new \Exception($this->wpdb->last_error);
            }

            // default image mentése
            if (is_array($p['default_image'])){
                $sql = "INSERT INTO `alkoto_kepek` SET
                            `alkoto_id` = '".$this->_e($alkotoId)."',
                            `kepnev`    = '".$this->_e(!empty($p['default_image']['Title'])?$p['default_image']['Title']:$p['default_image']['Tags'])."',
                            `thumb`     = '".$this->_e($p['default_image']['ImageURLThumb'])."',
                            `medium`    = '".$this->_e($p['default_image']['ImageURLMedium'])."',
                            `big`       = '".$this->_e($p['default_image']['ImageURLBig'])."',
                            `listakep`  = '1',
                            `datum`     = NOW()";
                if (!$this->wpdb->query($sql)
                ){
                    throw new \Exception($this->wpdb->last_error);
                }
            }


            // további képek mentése
            if (is_array($p['images'])){
                foreach ($p['images'] as $img){
                    // a default image már el van mentve, azt nem kell menteni
                    if ($img['Id'] != $p['default_image']['Id']){
                        $sql = "INSERT INTO `alkoto_kepek` SET
                            `alkoto_id` = '".$this->_e($alkotoId)."',
                            `kepnev`    = '".$this->_e(!empty($img['Title'])?$img['Title']:$img['Tags'])."',
                            `thumb`     = '".$this->_e($img['ImageURLThumb'])."',
                            `medium`    = '".$this->_e($img['ImageURLMedium'])."',
                            `big`       = '".$this->_e($img['ImageURLBig'])."',
                            `listakep`  = '0',
                            `datum`     = NOW()";
                        if (!$this->wpdb->query($sql)){
                            throw new \Exception($this->wpdb->last_error);
                        }
                    }
                }
            }

        }

        /**
         * Elmenti az előadás változtatásait
         *
         * @param $program
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function saveEloadas($program){
            /**
             * Adatok ellenőrzése
             */
            if (empty($program) || empty($program['payload'])){
                throw new \Exception('Nincsenek meg a program adatai!');
            }

            if (!is_array($program) || !is_array($program['payload'])){
                throw new \Exception('A program adatai nem a megfelelő formátumban (array) lettek megadva!');
            }

            /**
             * Minden rendben, indulhat a beillesztés / módosítás
             */
            if (!empty($this->programIDs[$program['payload']['NetProgram_Id']])){
                $sql    = " UPDATE ";
                $where  = " WHERE `id` = '".$this->programIDs[$program['payload']['NetProgram_Id']]."'";
            }
            else{
                $sql    = "INSERT INTO ";
                $where  = "";
            }

            $p = $program['payload'];

            $sql .= " `eloadas` SET
                        `jegy_hu_id`    = '".$this->_e($program['payload']['NetProgram_Id'])."',
                        `cim`           = '".$this->_e($p['Name'])."',
                        `seo`           = '".$this->_e($p['NameURL'])."',
                        `bevezeto`      = '".$this->_e($p['ShortDescription'])."',
                        `leiras`        = '".$this->_e($p['Description'])."',
                        `status`        = '1'";
            $sql .= $where;
            $rs = $this->wpdb->query($sql);

            if ($rs === false){
                throw new \Exception($this->wpdb->last_error);
            }

            // mentés a programok közé
            if (empty($this->programIDs[$p['NetProgram_Id']])){
                $this->programIDs[$p['NetProgram_Id']] = $this->wpdb->insert_id;
            }

            // mentés a címek közé
            if (empty($this->addresses[$p['NetProgram_Id']])){
                $this->addresses[$p['NetProgram_Id']] = $p['AuditAddress'];
            }

            // mentés az árak közé
            if (empty($this->prices[$p['NetProgram_Id']])){
                $this->prices[$p['NetProgram_Id']]['min'] = $p['MinPrice'];
                $this->prices[$p['NetProgram_Id']]['max'] = $p['MaxPrice'];
            }
        }

        /**
         * Elmenti az előadás képeit
         *
         * @param array $program
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function saveEloadasKepek($program){
            /**
             * Adatok ellenőrzése
             */
            if (empty($program) || empty($program['payload'])){
                throw new \Exception('Nincsenek meg a program adatai!');
            }

            if (!is_array($program) || !is_array($program['payload'])){
                throw new \Exception('A program adatai nem a megfelelő formátumban (array) lettek megadva!');
            }

            /**
             * Minden rendben, indulhat a beillesztés / módosítás
             */
            $p          = $program['payload'];
            $eloadasId  = $this->programIDs[$p['NetProgram_Id']];

            if (!empty($eloadasId)){
                if (is_array($p['Images'])) {

                    // töröljük a mostani kép bejegyzéseket
                    $this->removeEloadasKepek($eloadasId);

                    foreach ($p['Images'] as $image){
                        $sql = "INSERT INTO `eloadas_kepek` SET
                                    `eloadas_id` = '".$this->_e($eloadasId)."',
                                    `thumb`      = '".$this->_e($image['ImageURLThumb'])."',
                                    `original`   = '".$this->_e($image['ImageURLOriginal'])."',
                                    `medium`     = '".$this->_e($image['ImageURLMedium'])."',
                                    `big`        = '".$this->_e($image['ImageURLBig'])."'";

                        if ($this->wpdb->query($sql) === false){
                            throw new \Exception(__CLASS__.'::'.__FUNCTION__.' Hiba a '.__LINE__.' sorban: '.$this->wpdb->last_error);
                        }

                    }
                }
            }
        }

        /**
         * Elmenti az előadás közreműködőit a színlaphoz
         *
         * @param array $program
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function saveEloadasAlkotok($program){
            /**
             * Adatok ellenőrzése
             */
            if (empty($program) || empty($program['payload'])){
                throw new \Exception('Nincsenek meg a program adatai!');
            }

            if (!is_array($program) || !is_array($program['payload'])){
                throw new \Exception('A program adatai nem a megfelelő formátumban (array) lettek megadva!');
            }

            // előadás id
            $p          = $program['payload'];
            $eloadasId  = $this->programIDs[$p['NetProgram_Id']];

            // mentett közreműködők törlése
            $sql = "DELETE FROM `eloadas_alkoto` WHERE `eloadas_id` = '".$eloadasId."'";
            if ($this->wpdb->query($sql) === false){
                throw new \Exception($this->wpdb->last_error);
            }


            // közreműködők mentése
            if (is_array($p['Creators'])){
                foreach ($p['Creators'] as $creator){
                    /**
                     * alkotó id meghatározása
                     */

                    // ha nincs még mentve a szinkronolt alkotók közé
                    if (empty($this->personIDs[$creator['Actor_Id']])){
                        // elmenti / módosítja az alkotót
                        $this->saveAlkoto($creator);
                    }

                    // már szinkronolva lett, nem kell mégegyszer szinkronolni
                    $alkotoId = $this->personIDs[$creator['Actor_Id']];

                    $sql = "INSERT INTO `eloadas_alkoto` SET
                                `eloadas_id`    = '".$this->_e($eloadasId)."',
                                `szerepkor`     = '".$this->_e($creator['ActorType'])."',
                                `nev`           = '".$this->_e($creator['LastName'].' '.$creator['FirstName'])."',
                                `alkoto_id`     = '".$this->_e($alkotoId)."'";

                    if (!$this->wpdb->query($sql)){
                        throw new \Exception($this->wpdb->last_error);
                    }

                }
            }
            
            // színészek mentése
            if (is_array($p['Actors'])){
                foreach ($p['Actors'] as $actor){
                    /**
                     * alkotó id meghatározása
                     */

                    // ha nincs még mentve a szinkronolt alkotók közé
                    if (empty($this->personIDs[$actor['Actor_Id']])){
                        // elmenti / módosítja az alkotót
                        $this->saveAlkoto($actor);
                    }

                    // már szinkronolva lett, nem kell mégegyszer szinkronolni
                    $alkotoId = $this->personIDs[$actor['Actor_Id']];

                    $sql = "INSERT INTO `eloadas_alkoto` SET
                                `eloadas_id`    = '".$this->_e($eloadasId)."',
                                `szerepkor`     = '".$this->_e($actor['ActorType'])."',
                                `nev`           = '".$this->_e($actor['LastName'].' '.$actor['FirstName'])."',
                                `alkoto_id`     = '".$this->_e($alkotoId)."'";

                    if (!$this->wpdb->query($sql)){
                        throw new \Exception($this->wpdb->last_error);
                    }

                }
            }

        }

        /**
         * Elementi a műsor adatait
         *
         * @param array $events
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function saveEvents($events){
            if (empty($events)){
                throw new \Exception("Nincsenek meg az előadások!");
            }

            if (!is_array($events)){
                throw new \Exception(" Az event-ek listája csak tömb lehet! (".print_r($events,true).")");
            }

            foreach ($events as $event){
                if ($event['LastModMax'] > $this->existedEventsTS[$event['NetEvent_Id']]){
                    $this->saveMusor($event);
                }
            }
        }

        /**
         * Elmenti a műsort
         *
         * @param array $event jegy.hu event adatok
         * 
         * @throws \Exception
         */
        private function saveMusor($event){
            /**
             * Adatok ellenőrzése
             */
            if (empty($event)){
                throw new \Exception('Nincsenek megadva a elmentésre szánt event adatok!');
            }
            
            if (!is_array($event)){
                throw new \Exception('Az event adatok nem jó formátumban (tömb) lettek megadva!'.print_r($event,true));
            }

            /**
             * Minden ok, kezdődhet a mentés
             */

            // cím beállítás
            $helyszin = '';
            $varos    = '';

            if (!empty($this->addresses[$event['NetProgram_Id']])){
                $a = $this->addresses[$event['NetProgram_Id']];
                $helyszin   = $a['ZipCode'].' '.$a['City'].' '.$a['Street'].', '.$a['Country'];
                $varos      = $a['City'];
            }

            // ár beállítás
            $ar = 0;
            if (!empty($this->prices[$event['NetProgram_Id']])){
                $ar = str_replace(' ','',
                        str_replace('Ft','',$this->prices[$event['NetProgram_Id']]['min']));
            }

            $maxAr = 0;
            if (!empty($this->prices[$event['NetProgram_Id']])){
                $maxAr = str_replace(' ','',
                        str_replace('Ft','',$this->prices[$event['NetProgram_Id']]['max']));
            }

            // seo url generálás
            $seo = $event['NameURL'].'-'                                 // jegy.hu seo URL
                .$this->normalize($varos).'-'                            // város
                .substr(                                                 // időpont
                    str_replace(' ','-',
                        str_replace(':','',
                            str_replace('-','',$event['EventDate'])))
                    ,0,-2);

            // műsor mentése
            if ($this->isEventExists($event['NetEvent_Id'])){
                $sql    = "UPDATE ";
                $where  = " WHERE `jegy_hu_id` = '".$event['NetEvent_Id']."'";
            }
            else{
                $sql    = "INSERT INTO ";
                $where  = "";
            }

            $sql .= " `musor` SET
                                `helyszin_nev`              = '".$this->_e($event['AuditName'])."',
                                `helyszin`                  = '".$this->_e($helyszin)."',
                                `varos`                     = '".$this->_e($varos)."',
                                `ido`                       = '".$this->_e($event['EventDate'])."',
                                `cim`                       = '".$this->_e($event['ProgramName'])."',
                                `seo`                       = '".$this->_e($seo)."',
                                `kiemelt_kep`               = '".$this->_e($event['ThumbURL'])."',
                                `informacio`                = '".$this->_e($event['ShortDescription'])."',
                                `jegyrendeles`              = '1',
                                `jegy_hu_id`                = '".$this->_e($event['NetEvent_Id'])."',
                                `jegy_hu_audit_id`          = '".$this->_e($event['NetAudit_Id'])."',
                                `eloadas_id`                = '".$this->_e($this->programIDs[$event['NetProgram_Id']])."',
                                `jegy_hu_elerheto_jegyek`   = '".$event['LastAvailableTickets']."',
                                `jegy_hu_status`            = '".$this->_e($event['EventStatus_Id'])."',
                                `jegy_elfogyott`            = '".($event['TicketAvailable'] == 'N'?'1':'0')."',
                                `ar`                        = '".$this->_e($ar)."',
                                `max_ar`                    = '".$this->_e($maxAr)."',
                                `dumaklub`                  = '".(preg_match('/dumaklub/i',$event['AuditName'])?'1':'0')."',
                                `gyermekeloadas`            = '".(preg_match('/gyermekelőadás/i',$event['ShortDescription'])?'1':'0')."',
                                `status`                    = '1',
                                `ts`                        = '".$event['LastModMax']."'";
            $sql .= $where;

            if ($this->wpdb->query($sql) === false){
                throw new \Exception($this->wpdb->last_error);
            }
        }

        /**
         * Beállítja az alkotók státuszát attól függően, hogy vannak-e hozzá előadások a jövőben, vagy nem
         */
        private function setAlkotoStatus(){
            // aktív alkotók id-jának legyűjtése
            $aktivAlkotoIds = $this->getActiveAlkotoIds();

            // aktív alkotók beállítása
            $sql = "UPDATE `alkoto` SET `status` = '1' WHERE `id` IN (".implode(',',$aktivAlkotoIds).")";
            $this->wpdb->query($sql);

            // inaktív alkotók beállítása
            $sql = "UPDATE `alkoto` SET `status` = '0' WHERE `id` NOT IN (".implode(',',$aktivAlkotoIds).")";
            $this->wpdb->query($sql);
        }

        /**
         * Betölti a helyi db-ből a programok id-ját, és a hozzájuk tartozó jegy.hu id-t (netprogram_id)
         *
         * @throws \Exception
         */
        private function setProgramIDs(){
            $sql = "SELECT `id`,`jegy_hu_id` FROM `eloadas` WHERE `jegy_hu_id` <> '0'";
            $rs  = $this->wpdb->get_results($sql);

            if ($rs === false){
                throw new \Exception($this->wpdb->last_error.'!');
            }

            if (is_array($rs)){
                foreach ($rs as $row){
                    $this->programIDs[$row->jegy_hu_id] = $row->id;
                }
            }
        }

        /**
         * Elemti az utolsó szinkronolás dátumát a szinkron végén
         */
        private function saveLastSync(){
            $this->wpdb->insert('jegy_hu_sync',array('last_sync'=>date('Y-m-d H:i:s')),array('%s'));
        }

        /**
         * Módosítja az adatbázist, ha szükséges a jegy.hu szinkronhoz
         */
        private function updateDB(){
            $this->renameTable('fellepo','alkoto');                                 // fellepo -> alkoto
            $this->renameTable('eloadas_szinlap_kozremukodo','eloadas_alkoto');     // eloadas_szinlap_kozremukodo -> eloadas_alkoto
            $this->renameTable('fellepo_kepek','alkoto_kepek');                     // fellepo_kepek -> alkoto_kepek
            $this->updateTableAlkoto();                                             // alkoto
            $this->updateTableAlkotoKepek();                                        // alkoto_kepek
            $this->updateTableEloadas();                                            // eloadas
            $this->updateTableEloadasKepek();                                       // eloadas_kepek
            $this->updateTableEloadasAlkoto();                                      // eloadas_alkoto
            $this->updateTableMusor();                                              // műsor
        }

        /**
         * Módosítja az alkoto táblát, ha szükséges
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function updateTableAlkoto(){
            if (!$this->isFieldInTable('actor_id','alkoto')){
                $sql = "ALTER TABLE `alkoto` ADD `actor_id` BIGINT NOT NULL COMMENT 'jegy.hu Actor_Id' AFTER `id`, ADD INDEX (`actor_id`) ;";
                if (!$this->wpdb->query($sql)){
                    throw new \Exception($this->wpdb->last_error.'!');
                }
            }
        }

        /**
         * Módosítja az alkoto táblát, ha szükséges
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function updateTableAlkotoKepek(){
            if (!$this->isFieldInTable('alkoto_id','alkoto_kepek')) {
                $sql = "ALTER TABLE `alkoto_kepek` CHANGE `fellepo_id` `alkoto_id` INT(11) NOT NULL DEFAULT '0';";
                if (!$this->wpdb->query($sql)) {
                    throw new \Exception($this->wpdb->last_error . '!');
                }
            }

            // felesleges mezők eldobása
            if ($this->isFieldInTable('leiras','alkoto_kepek')) {
                $sql = "ALTER TABLE `alkoto_kepek` DROP `leiras`";
                if (!$this->wpdb->query($sql)) {
                    throw new \Exception($this->wpdb->last_error . '!');
                }
            }

            if ($this->isFieldInTable('feltolto','alkoto_kepek')) {
                $sql = "ALTER TABLE `alkoto_kepek` DROP `feltolto`";
                if (!$this->wpdb->query($sql)) {
                    throw new \Exception($this->wpdb->last_error . '!');
                }
            }

            // új mezők hozzáadása
            if (!$this->isFieldInTable('thumb','alkoto_kepek')) {
                $sql = "ALTER TABLE `alkoto_kepek` ADD `thumb` VARCHAR(512) NOT NULL AFTER `kepnev`, ADD `medium` VARCHAR(512) NOT NULL AFTER `thumb`, ADD `big` VARCHAR(512) NOT NULL AFTER `medium`;";
                if (!$this->wpdb->query($sql)) {
                    throw new \Exception($this->wpdb->last_error . '!');
                }
            }
        }

        /**
         * Módosítja az eloadas táblát, ha szükséges
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function updateTableEloadas(){
            // jegy_hu_id
            if (!$this->isFieldInTable('jegy_hu_id','eloadas')){
                $sql = "ALTER TABLE `eloadas` ADD `jegy_hu_id` BIGINT NOT NULL AFTER `id`, ADD INDEX (`jegy_hu_id`) ;";
                if (!$this->wpdb->query($sql)){
                    throw new \Exception($this->wpdb->last_error.'!');
                }
            }
        }

        /**
         * Módosítja az eloadas_szinlap_kozremukodok táblát
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function updateTableEloadasAlkoto(){
            if (!$this->isFieldInTable('alkoto_id','eloadas_alkoto')) {
                $sql = "ALTER TABLE `eloadas_alkoto` ADD `alkoto_id` INT NOT NULL , ADD INDEX (`alkoto_id`);";
                if (!$this->wpdb->query($sql)) {
                    throw new \Exception($this->wpdb->last_error . '!');
                }
            }
        }

        /**
         * Módosítja az eloadas_kepek táblát, ha szükséges
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function updateTableEloadasKepek(){
            // thumb
            if (!$this->isFieldInTable('thumb','eloadas_kepek')){
                $sql = "ALTER TABLE `eloadas_kepek` ADD `thumb` VARCHAR(512) NOT NULL AFTER `eloadas_id`;";
                if (!$this->wpdb->query($sql)){
                    throw new \Exception($this->wpdb->last_error.'!');
                }
            }

            // original
            if (!$this->isFieldInTable('original','eloadas_kepek')){
                $sql = "ALTER TABLE `eloadas_kepek` ADD `original` VARCHAR(512) NOT NULL AFTER `thumb`;";
                if (!$this->wpdb->query($sql)){
                    throw new \Exception($this->wpdb->last_error.'!');
                }
            }

            // medium
            if (!$this->isFieldInTable('medium','eloadas_kepek')){
                $sql = "ALTER TABLE `eloadas_kepek` ADD `medium` VARCHAR(512) NOT NULL AFTER `original`;";
                if (!$this->wpdb->query($sql)){
                    throw new \Exception($this->wpdb->last_error.'!');
                }
            }

            // big
            if (!$this->isFieldInTable('big','eloadas_kepek')){
                $sql = "ALTER TABLE `eloadas_kepek` ADD `big` VARCHAR(512) NOT NULL AFTER `medium`;";
                if (!$this->wpdb->query($sql)){
                    throw new \Exception($this->wpdb->last_error.'!');
                }
            }
        }

        /**
         * Módosítja a musor táblát, ha szükséges
         *
         * @uses self::$wpdb
         *
         * @throws \Exception
         */
        private function updateTableMusor(){
            if (!$this->isFieldInTable('helyszin','musor')){
                $sql = "ALTER TABLE `musor` ADD `helyszin` VARCHAR(512) NOT NULL AFTER `helyszin_id`;";
                if (!$this->wpdb->query($sql)){
                    throw new \Exception($this->wpdb->last_error.'!');
                }
            }

            if (!$this->isFieldInTable('varos','musor')){
                $sql = "ALTER TABLE `musor` ADD `varos` VARCHAR(512) NOT NULL AFTER `helyszin`;";
                if (!$this->wpdb->query($sql)){
                    throw new \Exception($this->wpdb->last_error.'!');
                }
            }

            if (!$this->isFieldInTable('jegy_elfogyott','musor')){
                $sql = "ALTER TABLE `musor` ADD `jegy_elfogyott` TINYINT(1) NOT NULL AFTER `jegyrendeles`;";
                if (!$this->wpdb->query($sql)){
                    throw new \Exception($this->wpdb->last_error.'!');
                }
            }

            if (!$this->isFieldInTable('helyszin_nev','musor')){
                $sql = "ALTER TABLE `musor` ADD `helyszin_nev` VARCHAR(512) NOT NULL AFTER `helyszin_id`;";
                if (!$this->wpdb->query($sql)){
                    throw new \EXception($this->wpdb->last_error.'!');
                }
            }

            if (!$this->isFieldInTable('jegy_hu_audit_id','musor')){
                $sql = "ALTER TABLE `musor` ADD `jegy_hu_audit_id` VARCHAR(512) NOT NULL AFTER `jegy_hu_id`;";
                if (!$this->wpdb->query($sql)){
                    throw new \EXception($this->wpdb->last_error.'!');
                }
            }

            if (!$this->isFieldInTable('ts','musor')){
                $sql = "ALTER TABLE `musor` ADD `ts` VARCHAR(512) NOT NULL AFTER `dumaklub`;";
                if (!$this->wpdb->query($sql)){
                    throw new \EXception($this->wpdb->last_error.'!');
                }
            }

            if (!$this->isFieldInTable('jegy_hu_elerheto_jegyek','musor')){
                $sql = "ALTER TABLE `musor` ADD `jegy_hu_elerheto_jegyek` INT NOT NULL AFTER `jegy_hu_event_id`;";
                if (!$this->wpdb->query($sql)){
                    throw new \EXception($this->wpdb->last_error.'!');
                }
            }

            if (!$this->isFieldInTable('gyermekeloadas','musor')){
                $sql = "ALTER TABLE `musor` ADD `gyermekeloadas` INT NOT NULL AFTER `dumaklub`;";
                if (!$this->wpdb->query($sql)){
                    throw new \EXception($this->wpdb->last_error.'!');
                }
            }

            if (!$this->isFieldInTable('max_ar','musor')){
                $sql = "UPDATE `musor` SET `ts` = '0' WHERE `ido` > NOW()";
                if (!$this->wpdb->query($sql)){
                    throw new \EXception($this->wpdb->last_error.'!');
                }

                $sql = "ALTER TABLE `musor` ADD `max_ar` INT NOT NULL AFTER `ar`;";
                if (!$this->wpdb->query($sql)){
                    throw new \EXception($this->wpdb->last_error.'!');
                }
            }


        }
    }