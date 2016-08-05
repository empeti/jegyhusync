<?php
    namespace jegyhu;

    class API{
        var $sessionId, $token;

        var $httpStatusCodes = array(
            100 => "Continue",
            101 => "Switching Protocols",
            102 => "Processing",
            200 => "OK",
            201 => "Created",
            202 => "Accepted",
            203 => "Non-Authoritative Information",
            204 => "No Content",
            205 => "Reset Content",
            206 => "Partial Content",
            207 => "Multi-Status",
            300 => "Multiple Choices",
            301 => "Moved Permanently",
            302 => "Found",
            303 => "See Other",
            304 => "Not Modified",
            305 => "Use Proxy",
            306 => "(Unused)",
            307 => "Temporary Redirect",
            308 => "Permanent Redirect",
            400 => "Bad Request",
            401 => "Unauthorized",
            402 => "Payment Required",
            403 => "Forbidden",
            404 => "Not Found",
            405 => "Method Not Allowed",
            406 => "Not Acceptable",
            407 => "Proxy Authentication Required",
            408 => "Request Timeout",
            409 => "Conflict",
            410 => "Gone",
            411 => "Length Required",
            412 => "Precondition Failed",
            413 => "Request Entity Too Large",
            414 => "Request-URI Too Long",
            415 => "Unsupported Media Type",
            416 => "Requested Range Not Satisfiable",
            417 => "Expectation Failed",
            418 => "I'm a teapot",
            419 => "Authentication Timeout",
            420 => "Enhance Your Calm",
            422 => "Unprocessable Entity",
            423 => "Locked",
            424 => "Failed Dependency",
            424 => "Method Failure",
            425 => "Unordered Collection",
            426 => "Upgrade Required",
            428 => "Precondition Required",
            429 => "Too Many Requests",
            431 => "Request Header Fields Too Large",
            444 => "No Response",
            449 => "Retry With",
            450 => "Blocked by Windows Parental Controls",
            451 => "Unavailable For Legal Reasons",
            494 => "Request Header Too Large",
            495 => "Cert Error",
            496 => "No Cert",
            497 => "HTTP to HTTPS",
            499 => "Client Closed Request",
            500 => "Internal Server Error",
            501 => "Not Implemented",
            502 => "Bad Gateway",
            503 => "Service Unavailable",
            504 => "Gateway Timeout",
            505 => "HTTP Version Not Supported",
            506 => "Variant Also Negotiates",
            507 => "Insufficient Storage",
            508 => "Loop Detected",
            509 => "Bandwidth Limit Exceeded",
            510 => "Not Extended",
            511 => "Network Authentication Required",
            598 => "Network read timeout error",
            599 => "Network connect timeout error");

        // API URI
        var $URI = 'http://dumaszinhaz.jegy.hu/api';

        // API verzió
        var $version = 2;


        /**
         * Konstruktor
         */
        public function __construct(){
            $this->login();
        }

        /**
         * Visszaadja az események listáját
         *
         * @param array $params
         *
         * @throws \Exception
         *
         * @return array
         */
        public function getEventList(array $params){
            $result = $this->getResults('get_event_list',$params);
            $events = json_decode($result, true);

            return $events;
        }

        /**
         * Visszaadja a person adatait a jegy.hu szerverről
         *
         * @param array $params
         *
         * @throws \Exception
         *
         * @return array
         */
        public function getPerson(array $params){
            /**
             * Adatok ellenőrzése
             */
            if (empty($params['person_id'])){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' hiba a '.__LINE__.'. sorban: Nincs megadva a person_id!');
            }

            if ((int)$params['person_id'] !== $params['person_id']){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' hiba a '.__LINE__.'. sorban: A person_id csak szám lehet!');
            }

            /**
             * Nincs hiba, kérjük le az adatokat
             */
            $result = $this->getResults('get_person',$params);
            $events = json_decode($result, true);

            return $events;
        }


        /**
         * Visszaadja a program adatait a jegy.hu szerverről
         *
         * @param array $params
         *
         * @throws \Exception
         *
         * @return array
         */
        public function getProgram(array $params){
            /**
             * Adatok ellenőrzése
             */
            if (empty($params['netprogram_id'])){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' hiba a '.__LINE__.'. sorban: nincs megadva a lekérni kívánt program id-ja!');
            }

            if ($params['netprogram_id'] != (int)$params['netprogram_id']){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' hiba a '.__LINE__.'. sorban: a program id-ja csak szám lehet!');
            }

            /**
             * Minden rendben, adatok lekérése
             */
            $result  = $this->getResults('get_program',$params);
            $program = json_decode($result, true);

            if (!is_array($program)){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' hiba a '.__LINE__.'. sorban: hibás jegy.hu válasz:'.print_r($program,true));
            }

            return $program;
        }

        /**
         * Visszaadja a helyszín részletes adatait
         *
         * @param string $name
         *
         * @throws \Exception
         *
         * @return array
         */
        public function getVenue($name){
            if (empty($name) || strlen(trim($name)) == 0){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' hiba a '.__LINE__.'. sorban: nincs megadva a keresett helyszín neve: '.$name.'!');
            }

            $params = array('venue_name'=>$name);

            $result = $this->getResults('get_venue',$params);
            $venue  = json_decode($result,true);

            if (!is_array($venue)){
                throw new \Exception(__CLASS__.'::'.__FUNCTION__.' hiba a '.__LINE__.'. sorban: hibás jegy.hu válasz:'.print_r($venue,true));
            }

            return $venue;
        }

        /**
         * Visszaadja a helyszínek listáját
         *
         * @param array $params
         *
         * @throws \Exception ha vmiért nem sikerült a lekérés
         *
         * @return array
         */
        public function getVenueList($params){
            $venues = array();

            /**
             * adatok ellenőrzése
             */

            // order_by_name
            if (!empty($params['order_by_name'])){
                if (!is_bool($params['order_by_name'])){
                    throw new \Exception(__CLASS__.'::'.__FUNCTION__.': az order_by_name paraméter ('.$params['order_by_name'].') csak boolean típusú lehet!');
                }
            }

            // source_id
            if (!empty($params['source_id'])){
                if (preg_match('/[^0-9]/',$params['source_id'])){
                    throw new \Exception(__CLASS__.'::'.__FUNCTION__.': a source_id ('.$params['source_id'].') paraméter csak számokat tartalmazhat!');
                }
            }

            /**
             * Nincs hiba, kérjük le az adatokat
             */

            $result = $this->getResults('get_venue_list',$params);
            $venues = json_decode($result, true);

            return $venues;

        }

        /**
         * Visszaadja a lekérés eredményét a jegy.hu szerverről
         *
         * @param string $func
         * @param array  $params
         *
         * @throws \Exception ha nem sikerült a lekérés valamiért, vagy ha a szerver hibát dob
         *
         * @return string json válasz
         */
        private function getResults($func, $params){
            /**
             * Paraméterek beállítása
             */
            $params['_request']         = json_encode($params);
            $params['_function_name']   = $func;
            $params['_session_id']      = $this->sessionId;
            $params['_version']         = $this->version;

            /**
             * Lekérés elküldése
             */
            $ch = curl_init($this->URI);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST,           true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,     $params);
            curl_setopt($ch, CURLOPT_HEADER,         1);

            $response    = curl_exec($ch);

            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body        = substr($response, $header_size);

            /**
             * Válasz ellenőrzése
             */
            if ($http_code != 200){
                throw new \Exception($func.': hibás jegy.hu szerver válasz:'.$this->httpStatusCodes[$http_code].'!');
            }

            if (empty($body)){
                throw new \Exception($func.': üres jegy.hu szerver válasz!');
            }

            return $body;
        }

        /**
         * Belépés, sessionId, token beállítás
         */
        private function login(){
            // @todo: ide majd a logint le kell fejleszteni, ha lesz login
            $this->sessionId    = '';
            $this->token        = '';
        }
    }