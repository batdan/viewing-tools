<?php
namespace tools;

use tools\config;
use tools\dbSingleton;

/**
 * Class for scraping
 */
class wgetImages
{
    /**
     * Instance PDO
     * @var object
     */
    private $dbh;

    /**
     * Get Ip & User Agent
     * @var array
     */
    private $rotateIp;

    /**
     * Nom server
     * @var string
     */
    private $srvName;

    /**
     * IP disponibles
     * @var array
     */
    private $ipList;

    /**
     * Users Agents
     * @var array
     */
    private $userAgentList;

    /**
     * Récupération du dernier enregistrement : ip / plateforme / datetime
     * @var array
     */
    private $lastInsert;

    /**
     * Dernière clé du tableau de la liste des IPs
     * @var integer
     */
    private $endKeysIpLists;

    /**
     * Attente si le temps entre deux actions n'est pas écoulé
     * @var boolean
     */
    private $wait = false;

    /**
     * Plateforme
     * @var string
     */
    private $platform;

    /**
     * Temps entre 2 requêtes
     * @var integer
     */
    private $intervalTime;

    /**
     * Activation de la rotation d'ip
     * @var boolean
     */
    private $rotateIps;

    /**
     * Activation de l'analyse du nombre de requêtes par minutes / heures / jours
     * @var boolean
     */
    private $analyseQty;

    /**
     * Requête d'ajout d'action avec une IP
     * @var object
     */
    private $sqlInsert;

    /**
     * Requête de suppression d'action avec une IP
     * @var object
     */
    private $sqlDelete;

    /**
     * Url de l'image
     * @var string
     */
    private $url;

    /**
     * Status du téléchagement de l'image
     * @var string
     */
    private $status;


    /**
     * Constructor
     *
     * @param string    $platform           Nom de la plateforme
     * @param integer   $intervalTime       Temps entre 2 actions sur une IP (en millisecondes)
     * @param boolean   $rotateIps          Activation de la rotation des IP est des Users Agents
     * @param boolean   $analyseQty         Activation de l'analyse du nombre de requêtes par minutes / heures / jours
     */
    public function __construct($platform, $intervalTime, $rotateIps=false, $analyseQty=false)
    {
        $this->platform     = $platform;
        $this->intervalTime = $intervalTime;
        $this->rotateIps    = $rotateIps;
        $this->analyseQty   = $analyseQty;

        // Instance PDO
        $this->dbh = dbSingleton::getInstance('cron');

        // Récupération des configurations
        $this->srvName        = config::getConfig('srvName');
        $this->ipList         = config::getConfig('ipServer');
        $this->userAgentList  = config::getConfig('userAgentList');

        // Dernière clé du tableau de la liste des IPs
        $getKeys = array_keys($this->ipList);
        $this->endKeysIpLists = end($getKeys);

        // Préparation des requêtes
        $this->prepareReq();
    }


    /**
     * Permet d'attendre son créneau avant d'exécuter l'action suivante
     *
     * @param  string   $url            Url de l'image
     * @param  string   $saveToDir      Répertoire de destination
     * @param  string   $imageName      Nouveau nom de l'image (sans l'extension)
     * @param  boolean  $checkRedir     Permet de vérifier s'il y a une redirection et de récupérer vrai lien
     */
    public function saveImg($url, $saveToDir, $imageName, $checkRedir=true)
    {
        $this->url = $url;

        // Boucle sur 5 minutes le temps d'obtenir un créneau
        for ($i=0; $i<50000; $i++) {

            if ($this->status == 'success') {
                $this->status = null;
                return;
            }

            // Attente 0.1 seconde de seconde
            usleep(100000);

            // Execution tous les 5 secondes
            $ret = $this->saveImgAux($saveToDir, $imageName, $checkRedir);

            if ($this->wait) {
                continue;
            }
        }

        throw new \Exception("Error Processing Request Wget : " . $url, 1);
    }


    /**
     * Sauvegarde de l'image
     *
     * @param  string   $url            Url de l'image
     * @param  string   $saveToDir      Répertoire de destination
     * @param  string   $imageName      Nouveau nom de l'image (sans l'extension)
     * @param  boolean  $checkRedir     Permet de vérifier s'il y a une redirection et de récupérer vrai lien
     */
    public function saveImgAux($saveToDir, $imageName, $checkRedir)
    {
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Rotation des IP (disponibles sur le serveur) et des User Agent
        $this->rotateIps();

        if ($this->wait) {
            return;
        }

        curl_setopt($ch, CURLOPT_INTERFACE, $this->rotateIp['ip']);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->rotateIp['userAgent']);

        $erroNo = curl_errno($ch);

        if (!$erroNo) {

            $fp = fopen($saveToDir . $imageName, 'wb');

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);

            $wget   = curl_exec($ch);
            $cinfos = curl_getinfo($ch);

            if ($checkRedir) {
                if (in_array($cinfos['http_code'], [301,302])) {
                    $this->url = $cinfos['redirect_url'];
                    return;
                }

                // Grande image Youtube absente
                if ($cinfos['http_code'] == 404 && strstr($this->url, 'maxresdefault')) {
                    $this->url = str_replace('maxresdefault', 'sddefault', $this->url);
                    return;
                }

            } else {
                curl_close($ch);
                fclose($fp);
            }

            if ($cinfos['http_code'] != 200) {
                throw new \Exception('HTTP Code : ' . $cinfos['http_code']);
            }

            $this->status = 'success';

        } else {

            throw new \Exception('Curl Errno : ' . $erroNo . ' - ' . $this->curlErrNo($erroNo));
        }
    }


    /**
     * Gestion de la rotation des ips et des User Agents
     *
     * @return array                        Ip et User Agent à utiliser
     */
    private function rotateIps()
    {
        if (!isset($this->lastInsert)) {

            // Initialisation de toutes les ip disponibles
            foreach ($this->ipList as $ip) {
                $this->lastInsert[$ip] = round(microtime(true) * 1000);
            }

            // 1ère requête sur la 1ère ip
            $this->rotateIp = [
                'status'    => 'success',
                'ip'        => $this->ipList[0],
                'userAgent' => $this->userAgentList[0],
            ];

        } else {

            // Activation ou non de la rotation d'ip
            if ($this->rotateIps) {

                $lastIp = $this->rotateIp['ip'];

                // Positionnement sur l'ip suivante
                $key = array_search($lastIp, $this->ipList);

                if ($key == $this->endKeysIpLists) {
                    $newKey = 0;
                } else {
                    $newKey = $key+1;
                }

                $newIp = $this->ipList[$newKey];

            } else {
                $newIp = $this->ipList[0];
            }

            if (round(microtime(true) * 1000) < ($this->lastInsert[$newIp] + $this->intervalTime)) {

                $this->rotateIp['ip'] = $newIp;
                $this->wait = true;
                return;

            } else {

                $this->wait = false;

                // Enregistrement du nouveau temps
                $this->lastInsert[$newIp] = round(microtime(true) * 1000);

                // Ip et User Agent sélectionnés
                $this->rotateIp = [
                    'status'    => 'success',
                    'ip'        => $this->ipList[$newKey],
                    'userAgent' => $this->userAgentList[$newKey]
                ];
            }
        }

        // Enregistrement de la nouvelle action
        $this->sqlInsert->execute([
            ':srv'      => $this->srvName,
            ':ip'       => $this->rotateIp['ip'],
            ':platform' => $this->platform,
            ':millitimestamp' => round(microtime(true) * 1000)
        ]);

        // Suppression des anciennes entrée avec ce couple ip / platform
        $this->sqlDelete->execute([
            ':srv'      => $this->srvName,
            ':ip'       => $this->rotateIp['ip'],
            ':platform' => $this->platform,
            ':id'       => $this->dbh->lastInsertId()
        ]);

        // Enregistrement du nombre de requêtes par jours, heures et minutes
        if ($this->analyseQty) {
            $this->analyseQty();
        }
    }


    /**
     * Enregistrement du nombre de requêtes par jours, heures et minutes
     */
    private function analyseQty()
    {
        // Journée
        $reqDay = "SELECT   id, count_actions
                   FROM     analyse_actions
                   WHERE    DATE_FORMAT(date_crea, '%Y-%m-%d') = CURDATE()
                   AND      plage = :plage
                   AND      ip = :ip
                   LIMIT    1";

        $sqlDay = $this->dbh->prepare($reqDay);
        $sqlDay->execute([
            ':plage' => 'DAY',
            ':ip' => $this->rotateIp['ip']
        ]);

        if ($sqlDay->rowCount()) {
            $resDay = $sqlDay->fetch();

            $reqDayUpdate = "UPDATE     analyse_actions
                             SET        count_actions   = :count_actions,
                                        date_modif      = NOW()
                             WHERE      id              = :id";

            $sqlDayUpdate = $this->dbh->prepare($reqDayUpdate);
            $sqlDayUpdate->execute([
                ':count_actions' => $resDay->count_actions + 1,
                ':id' => $resDay->id,
            ]);
        } else {
            $reqDayInsert = "INSERT INTO analyse_actions
                             (srv, ip, platform, plage, count_actions, date_crea, date_modif)
                             VALUES
                             (:srv, :ip, :platform, :plage, :count_actions, NOW(), NOW())";

            $sqlDayInsert = $this->dbh->prepare($reqDayInsert);
            $sqlDayInsert->execute([
                ':srv'              => $this->srvName,
                ':ip'               => $this->rotateIp['ip'],
                ':platform'         => $this->platform,
                ':plage'            => 'DAY',
                ':count_actions'    => 1
            ]);
        }

        // Heure
        $reqHour = "SELECT  id, count_actions
                    FROM    analyse_actions
                    WHERE   DATE_FORMAT(date_crea, '%Y-%m-%d %H') = DATE_FORMAT(NOW(), '%Y-%m-%d %H')
                    AND     plage = :plage
                    AND     ip = :ip
                    LIMIT   1";

        $sqlHour = $this->dbh->prepare($reqHour);
        $sqlHour->execute([
            ':plage' => 'HOUR',
            ':ip' => $this->rotateIp['ip']
        ]);

        if ($sqlHour->rowCount()) {
            $resHour = $sqlHour->fetch();

            $reqHourUpdate = "UPDATE     analyse_actions
                              SET        count_actions   = :count_actions,
                                         date_modif      = NOW()
                              WHERE      id              = :id";

            $sqlHourUpdate = $this->dbh->prepare($reqHourUpdate);
            $sqlHourUpdate->execute([
                ':count_actions' => $resHour->count_actions + 1,
                ':id' => $resHour->id,
            ]);
        } else {
            $reqHourInsert = "INSERT INTO analyse_actions
                              (srv, ip, platform, plage, count_actions, date_crea, date_modif)
                              VALUES
                              (:srv, :ip, :platform, :plage, :count_actions, NOW(), NOW())";

            $sqlHourInsert = $this->dbh->prepare($reqHourInsert);
            $sqlHourInsert->execute([
                ':srv'              => $this->srvName,
                ':ip'               => $this->rotateIp['ip'],
                ':platform'         => $this->platform,
                ':plage'            => 'HOUR',
                ':count_actions'    => 1
            ]);
        }

        // Minutes
        $reqMinute = "SELECT    id, count_actions
                      FROM      analyse_actions
                      WHERE     DATE_FORMAT(date_crea, '%Y-%m-%d %H-%i') = DATE_FORMAT(NOW(), '%Y-%m-%d %H-%i')
                      AND       plage = :plage
                      AND     ip = :ip
                      LIMIT     1";

        $sqlMinute = $this->dbh->prepare($reqMinute);
        $sqlMinute->execute([
            ':plage' => 'MINUTE',
            ':ip' => $this->rotateIp['ip']
        ]);

        if ($sqlMinute->rowCount()) {
            $resMinute = $sqlMinute->fetch();

            $reqMinuteUpdate = "UPDATE     analyse_actions
                                SET        count_actions   = :count_actions,
                                           date_modif      = NOW()
                                WHERE      id              = :id";

            $sqlMinuteUpdate = $this->dbh->prepare($reqMinuteUpdate);
            $sqlMinuteUpdate->execute([
                ':count_actions' => $resMinute->count_actions + 1,
                ':id' => $resMinute->id,
            ]);
        } else {
            $reqMinuteInsert = "INSERT INTO analyse_actions
                                (srv, ip, platform, plage, count_actions, date_crea, date_modif)
                                VALUES
                                (:srv, :ip, :platform, :plage, :count_actions, NOW(), NOW())";

            $sqlMinuteInsert = $this->dbh->prepare($reqMinuteInsert);
            $sqlMinuteInsert->execute([
                ':srv'              => $this->srvName,
                ':ip'               => $this->rotateIp['ip'],
                ':platform'         => $this->platform,
                ':plage'            => 'MINUTE',
                ':count_actions'    => 1
            ]);
        }
    }


    /**
     * Préparation des requêtes
     */
    private function prepareReq()
    {
        // Requête d'ajout d'action avec une IP
        $reqInsert = "INSERT INTO last_action (srv, ip, platform, last_date, millitimestamp) VALUES (:srv, :ip, :platform, NOW(), :millitimestamp)";
        $this->sqlInsert = $this->dbh->prepare($reqInsert);

        // Requête de suppression d'action avec une IP
        $reqDelete = "DELETE FROM last_action WHERE srv = :srv AND platform = :platform AND ip = :ip AND id != :id";
        $this->sqlDelete = $this->dbh->prepare($reqDelete);

    }


    /**
     * Messages pour interpréter les codes d'erreur Curl
     *
     * @param  int      $erroNo     ID de l'erreur Curl
     * @return array                Description des erreurs co
     */
    private function curlErrNo($erroNo)
    {
        $error_codes = [
            'CURLE_UNSUPPORTED_PROTOCOL',
            'CURLE_FAILED_INIT',
            'CURLE_URL_MALFORMAT',
            'CURLE_URL_MALFORMAT_USER',
            'CURLE_COULDNT_RESOLVE_PROXY',
            'CURLE_COULDNT_RESOLVE_HOST',
            'CURLE_COULDNT_CONNECT',
            'CURLE_FTP_WEIRD_SERVER_REPLY',
            'CURLE_REMOTE_ACCESS_DENIED',
            'CURLE_FTP_WEIRD_PASS_REPLY',
            'CURLE_FTP_WEIRD_PASV_REPLY',
            'CURLE_FTP_WEIRD_227_FORMAT',
            'CURLE_FTP_CANT_GET_HOST',
            'CURLE_FTP_COULDNT_SET_TYPE',
            'CURLE_PARTIAL_FILE',
            'CURLE_FTP_COULDNT_RETR_FILE',
            'CURLE_QUOTE_ERROR',
            'CURLE_HTTP_RETURNED_ERROR',
            'CURLE_WRITE_ERROR',
            'CURLE_UPLOAD_FAILED',
            'CURLE_READ_ERROR',
            'CURLE_OUT_OF_MEMORY',
            'CURLE_OPERATION_TIMEDOUT',
            'CURLE_FTP_PORT_FAILED',
            'CURLE_FTP_COULDNT_USE_REST',
            'CURLE_RANGE_ERROR',
            'CURLE_HTTP_POST_ERROR',
            'CURLE_SSL_CONNECT_ERROR',
            'CURLE_BAD_DOWNLOAD_RESUME',
            'CURLE_FILE_COULDNT_READ_FILE',
            'CURLE_LDAP_CANNOT_BIND',
            'CURLE_LDAP_SEARCH_FAILED',
            'CURLE_FUNCTION_NOT_FOUND',
            'CURLE_ABORTED_BY_CALLBACK',
            'CURLE_BAD_FUNCTION_ARGUMENT',
            'CURLE_INTERFACE_FAILED',
            'CURLE_TOO_MANY_REDIRECTS',
            'CURLE_UNKNOWN_TELNET_OPTION',
            'CURLE_TELNET_OPTION_SYNTAX',
            'CURLE_PEER_FAILED_VERIFICATION',
            'CURLE_GOT_NOTHING',
            'CURLE_SSL_ENGINE_NOTFOUND',
            'CURLE_SSL_ENGINE_SETFAILED',
            'CURLE_SEND_ERROR',
            'CURLE_RECV_ERROR',
            'CURLE_SSL_CERTPROBLEM',
            'CURLE_SSL_CIPHER',
            'CURLE_SSL_CACERT',
            'CURLE_BAD_CONTENT_ENCODING',
            'CURLE_LDAP_INVALID_URL',
            'CURLE_FILESIZE_EXCEEDED',
            'CURLE_USE_SSL_FAILED',
            'CURLE_SEND_FAIL_REWIND',
            'CURLE_SSL_ENGINE_INITFAILED',
            'CURLE_LOGIN_DENIED',
            'CURLE_TFTP_NOTFOUND',
            'CURLE_TFTP_PERM',
            'CURLE_REMOTE_DISK_FULL',
            'CURLE_TFTP_ILLEGAL',
            'CURLE_TFTP_UNKNOWNID',
            'CURLE_REMOTE_FILE_EXISTS',
            'CURLE_TFTP_NOSUCHUSER',
            'CURLE_CONV_FAILED',
            'CURLE_CONV_REQD',
            'CURLE_SSL_CACERT_BADFILE',
            'CURLE_REMOTE_FILE_NOT_FOUND',
            'CURLE_SSH',
            'CURLE_SSL_SHUTDOWN_FAILED',
            'CURLE_AGAIN',
            'CURLE_SSL_CRL_BADFILE',
            'CURLE_SSL_ISSUER_ERROR',
            'CURLE_FTP_PRET_FAILED',
            'CURLE_FTP_PRET_FAILED',
            'CURLE_RTSP_CSEQ_ERROR',
            'CURLE_RTSP_SESSION_ERROR',
            'CURLE_FTP_BAD_FILE_LIST',
            'CURLE_CHUNK_FAILED'
        ];

        return $error_codes[$erroNo];
    }
}
