<?php
    /**
     * Dumaszínház napi reklám összesítő
     *
     * Összesítí az eladható jegyek számát és kiírja egy táblázatban régióra és eladható jegyek számára rendezve
     */
    class DAS{
        private $db;

        /**
         * DAS constructor.
         */
        public function __construct(){
            try{
                $this->db = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8', DB_USER, DB_PASSWORD);
                $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            }
            catch (PDOException $PDOE){
                die($PDOE->getMessage());
            }
        }

        /**
         * Kiírja a táblázatot
         *
         * @return string
         */
        public function printTable(){
            global $budapestiEloadasok, $videkiEloadasok;

            $budapestiEloadasok = $this->getTableData('bp');
            $videkiEloadasok    = $this->getTableData('videk');

            ob_start();
            include_once(__DIR__.'/../template/table.phtml');
            $out = ob_get_clean();

            echo $out;
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
        private function getKoltesiLimit($musor){
            $musorIdopont = new DateTime($musor['ido'], new DateTimeZone('Europe/Budapest'));
            $aktIdopont   = new DateTime('now', new DateTimeZone('Europe/Budapest'));

            // az előadásig hátralévő napok száma
            $dayNr = $musorIdopont->diff($aktIdopont)->format('%d');

            $limit = ($musor['jegy_hu_elerheto_jegyek']*$musor['ar']*0.07)/$dayNr;

            return number_format($limit,0,'',' ').' Ft';
        }

        /**
         * Visszaadja a műsor összértékét
         *
         * @param array $musor
         *
         * @return float
         */
        private function getMusorOsszertek($musor){
            $musorIdopont = new DateTime($musor['ido'], new DateTimeZone('Europe/Budapest'));
            $aktIdopont   = new DateTime('now', new DateTimeZone('Europe/Budapest'));

            // az előadásig hátralévő napok száma
            $dayNr = $musorIdopont->diff($aktIdopont)->format('%d');

            if ($dayNr > 0){
                // előadásig hátralévő napok számának a reciproka és az eladható jegyek összértékének szorzata
                $ertek = (1/$dayNr)*($musor['jegy_hu_elerheto_jegyek']*$musor['ar']);
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
        private function getTableData($locType){
            $rows       = array();
            $sortedRows = array();

            // olyan előadások, amik
            //      - 4 héten belül vannak
            //      - legalább 1 nap van még hátra a kezdésig
            //      - a $locType helyszínen lesznek
            $sql = "SELECT * FROM `musor`
                    WHERE
                                `jegy_hu_elerheto_jegyek` > 0
                            AND `ido` < '".date('Y-m-d H:i:s',strtotime('+4 week'))."'
                            AND `ido` >= '".date('y-m-d H:i:s',strtotime('+1 day'))."'
                            AND `varos` ".($locType == 'bp'?" = 'Budapest'":"!= 'Budapest'");
            $rs  = $this->db->query($sql)->fetchAll();

            if (!empty($rs)){
                if (is_array($rs)){
                    foreach ($rs as $row){
                        $ertek = $this->getMusorOsszertek($row);
                        $osszertek[] = $ertek;
                        $ido[]       = $rs['ido'];
                        $rows[] = array(
                            'osszertek'             => $ertek,
                            'eladhato_jegyek_szama' => $row['jegy_hu_elerheto_jegyek'],
                            'koltesi_limit'         => $this->getKoltesiLimit($row),
                            'idopont'               => substr($row['ido'],0,-3),
                            'helyszin'              => $row['helyszin_nev'],
                            'cim'                   => $row['cim']
                        );
                    }
                }
            }

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

            return $sortedRows;
        }
    }