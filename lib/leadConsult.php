<?php
namespace tools;

/**
 * Affichage des leads
 */
class leadConsult
{
    /**
     * Attributs
     */
    private $_crypt;                // Objet de chiffrement, déchiffrement
    private $_dbh;                  // Instance PDO

    private $_query;                // Requête GET
    private $_table;                // Table du CTA
    private $_id;                   // Id du CTA

    private $_listChp;              // Liste des champs de la table
    private $_listChpLib;           // Liste des libellés des champs (stockés dans les commentaires de la structure de la table)

    private $_lines;                // Lignes du tableau avec les informations du lead

    private $_tableCtaName;         // Table de correspondance entre les noms de tables CTA et les nom des leads

    private $_notIn = array(        // Tableau des champs à ne pas prendre en compte dans les tables CTA
        'id',
        'id_project',
        'domain',
        'date_crea',
        'date_modif',
        'ip',
        'step',
        'stepLeadStatut',
        'sendLeadStatut',
        'relances',
        'spam',
        'lu',
        'spoolName',
        'retourWS'
    );


    /**
     * Constructeur
     */
	public function __construct()
	{
        // Instance PDO
        $this->_dbh = dbSingleton::getInstance();

        // Objet de chiffrement
        $this->_crypt = new crypt('consultation des leads');

        // Récupération variable GET
        $this->checkGet();

        // Récuparation de la table et de l'id du lead
        $this->whereIsCta();

        // Récupération de la structure de la table
        $this->structureCta();

        // Récupération du lead
        $this->myLead();

        // Sauvegarde de la consultation du lead
        $this->logConsult();
	}


    /**
     * Setters
     */
    public function setTableCtaName()
    {
        $this->_tableCtaName = array();

        $req = "SHOW TABLE STATUS WHERE Name LIKE 'cta_%'";
        $sql = $this->_dbh->query($req);

        while ($res = $sql->fetch()) {
            $this->_tableCtaName[$res->Name] = $res->Comment;
        }
    }


    /**
     * Récupération de la variable GET
     */
    private function checkGet()
    {
        if ( !isset($_GET['query']) || empty($_GET['query']) ) {
            die('arguments manquants !');
        } else {
            $this->_query = urldecode( $_GET['query'] );
            $this->_query = str_replace(' ', '+', $this->_query);
        }
    }


    /**
     * Récuparation de la table et de l'id du lead
     */
    private function whereIsCta()
    {
        $query = $this->_crypt->decrypt($this->_query);

        // Récupération de la table de l'id
        $exp = explode('|', $query);

        if (count($exp) != 2) {
            die('arguments non conformes !');
        }

        $this->_table = $exp[0];
        $this->_id    = $exp[1];
    }


    /**
     * Récupération de la structure de la table
     */
    private function structureCta()
    {
        $notIn = implode("', '", $this->_notIn);
        $notIn = "'" . $notIn . "'";

        $table = $this->_table;

        $req = "SHOW FULL COLUMNS FROM $table WHERE Field NOT IN ( $notIn )";
        $sql = $this->_dbh->query($req);
        $res = $sql->fetchAll();

        $this->_listChp    = array();
        $this->_listChplib = array();

        foreach ($res as $k=>$v) {
            $this->_listChp[]    = $v->Field;
            $this->_listChplib[] = $v->Comment;
        }
    }


    /**
     * Récupération du lead
     */
    private function myLead()
    {
        $listChpReq = implode(', ', $this->_listChp);

        $table = $this->_table;

        $req = "SELECT $listChpReq FROM $table WHERE id = :id";
        $sql = $this->_dbh->prepare($req);
        $sql->execute( array( ':id' => $this->_id ));

        if ($sql->rowCount() > 0) {
            $res = $sql->fetch();

            $i=0;
            $this->_lines = '';

            foreach ($this->_listChp as $chp) {
                if ($res->$chp != '') {
                    $this->_lines .= '<tr>' . chr(10);
                    $this->_lines .=    '<td>' . $this->_listChplib[$i] . '</td>' . chr(10);
                    $this->_lines .=    '<td>' . $this->checkJson($res->$chp) . '</td>' . chr(10);
                    $this->_lines .= '</tr>' . chr(10);
                }

                $i++;
            }
        }
    }


    /**
     * On vérifie s'il s'agit d'un flux JSON et on le décode le cas échéant
     *
     * @param       string      $str        Chaine à vérifier
     * @return      string
     */
    private function checkJson($str)
    {
        $checkStr = @json_decode($str, true);

        if (is_array($checkStr)) {
            $str = implode(', ', $checkStr);
        }

        return $str;
    }


    /**
     * Log de la date et heure de consultation du lead, ainsi que de l'IP
     */
    private function logConsult()
    {
        $ip  = $_SERVER['REMOTE_ADDR'];

        $table = $this->_table;

        // Enregistrement dans la table du CTA
        $req = "UPDATE $table SET lu = NOW() WHERE id = :id";
        $sql = $this->_dbh->prepare($req);
        $sql->execute( array( ':id' => $this->_id ));

        // Enregistrement dans la table des logs
        $req = "INSERT INTO log_consult (lead_table, lead_id, ip, date_crea) VALUES (:lead_table, :lead_id, :ip, NOW())";
        $sql = $this->_dbh->prepare($req);
        $sql->execute( array(
            ':lead_table'   => $this->_table,
            ':lead_id'      => $this->_id,
            ':ip'           => $ip,
        ));
    }




    /**
     * Rendu Html
     */
    public function render()
    {
        // Header du tableau
        $headerTxt = 'Lead : ';

        // Nom du CTA
        $this->setTableCtaName();

        $nameCta  = '';
        foreach ($this->_tableCtaName as $k => $v)
        {
            if ($k == $this->_table) {
                $nameCta = $v;
            }
        }

        if (! empty($nameCta)) {
            $headerTxt .= $nameCta . '<div class="sep">|</div> ';
        }

        // N° du lead
        $headerTxt .= 'N° ' . str_pad($this->_id, 5, "0", STR_PAD_LEFT);

        // Rendu Html
        return <<<eof
<!DOCTYPE html>
<html lang="fr">
    <head>

        <style type="text/css">
            body {
                font-family: 'Arial';
                font-size: 14px;
            }
            table {
                width: calc(100% -40px);
                margin: 20px;
                border-collapse: separate;
                border-spacing: 1px;
            }
            thead td {
                background-color: #666;
                color: #fff;
                text-align: center;
                font-weight: bold;
                padding: 15px 10px;
            }
            td {
                background-color: #efefef;
                padding: 8px 10px;
            }
            .sep {
                display: inline-block;
                padding-left: 15px;
                padding-right: 15px;
            }
        </style>

    </head>

    <body>

        <table>
            <thead>
                <tr>
                    <td colspan="2">$headerTxt</td>
                </tr>
            </thead>

            <tbody>
                {$this->_lines}
            </tbody>
        </table>

    </body>
</html>
eof;
    }
}
