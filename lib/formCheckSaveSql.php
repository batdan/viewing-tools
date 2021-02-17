<?php
namespace tools;

/**
 * Sauvegarde et mise à jour des formulaires
 * en base de données
 *
 * Table CTA template :
 *
 * id               int(11)         primary auto-increment
 * id_project       int(11)
 * date_crea        datetime
 * date_modif       datetime
 * ip               varchar(15)
 * step             varchar(5)
 * email            varchar(100)
 *
 * Tous les autres champs avec l'option NULL
 * Les champs en base doivent porter le même nom que dans les formulaires
 *
 * @author Daniel Gomes
 */
class formCheckSaveSql extends formCheckSave
{
    /**
     * Attributs
     */
    private $_stepLeadOk;       // Etape à partir de laquelle un lead est considéré valide
    private $_stepEndForm;      // Dernière étape du formulaire


    /**
     * Constructeur
     *
     * @param       string      $cta        Formulaire sélectionné
     * @param       string      $step       Etape du formulaire
     * @param       string      $chp        Champs envoyés
     */
    public function __construct($cta, $step, $chp)
    {
        parent::__construct($cta, $step, $chp, 'saveSql');

        // Etape à partir de laquelle un lead est considéré valide
        $this->_stepLeadOk = $this->_form['stepLeadOk'];

        // Dernière étape du formulaire
        $this->_stepEndForm = $this->_form['stepEndForm'];
    }


    /**
     * Sauvegarde du formulaire dans la variable de session et en base de données
     */
    protected function saveSql()
    {
        $action = '';

        // Changement de CTA
        /*
        if ( isset($this->_form['steps'][$this->_step]['switch']) && isset($this->_form['steps'][$this->_step]['switch']['cta']) ) {
            return array('action' => 'next');
        }
        */

        if (isset($this->_form['steps'][$this->_step]['switch'])) {

            $keys       = array_keys($this->_chp);
            $lastKey    = $keys[0];
            $switchKey  = $this->_chp[$lastKey];
            $switchCta  = $this->_form['steps'][$this->_step]['nextStep'][$switchKey]['cta'];

            if ($switchCta != $this->_cta) {

                // Enregistrement du parcours classique du CTA appelé
                $_SESSION['form']['parcours'][] = array(
                    'cta'  => $switchCta,
                    'step' => '0',
                );

                $_SESSION['form']['parcours'][] = array(
                    'cta'  => $switchCta,
                    'step' => '1',
                );

                // On se recale sur la bonne étape
                $_SESSION['form']['cta']  = $switchCta;
                $_SESSION['form']['step'] = 1;
            }

            return array('action' => 'next');
        }


        // pas d'email
        if (! isset($_SESSION['form']['email'])) {
            return array('action' => 'next');
        }

        $table = $this->_table;

        $this->_dbh->beginTransaction();

        $email = '' . $_SESSION['form']['email'];

        // Check si INSERT ou UPDATE
        $reqCheck = "SELECT     id, stepLeadStatut, nom, prenom, email
                     FROM       $table
                     WHERE      DATE_FORMAT(date_modif, '%Y-%m-%d') = CURDATE()
                     AND        (email = :email  OR  stepLeadStatut = :stepLeadStatut)";

        $sqlCheck = $this->_dbh->prepare($reqCheck);
        $sqlCheck->execute( array(
                                ':email'            => $email,
                                ':stepLeadStatut'   => 0,
        ));

        $sqlChp = array();
        $sqlVal = array();
        $sqlHydrate = array();

        foreach ($this->_chp as $k => $v) {
            $sqlChp[] = $k;
            $sqlVal[] = ':' . $k;

            // Suppression des caractères indésirables pour les champs ayant le contrôle number
            if (isset($this->_form['steps'][$this->_step]['fields'][$k]['control']) && $this->_form['steps'][$this->_step]['fields'][$k]['control'] == 'number') {
                $v = intval( preg_replace('`[^0-9]`', '', $v) );
            }

            // Stockage des checkbox en json
            if (isset($this->_form['steps'][$this->_step]['fields'][$k]['type']) && strstr('checkbox', $this->_form['steps'][$this->_step]['fields'][$k]['type'])) {
                $v = json_encode($v);
            }

            // Tableau d'hydratation des requêtes PDO
            $sqlHydrate[':' . $k] = $v;
        }

        // Merge de l'étape en cours pour l'hydratation des requêtes
        $nextStep = array(':step' => $this->_step);
        $sqlHydrate = array_merge($sqlHydrate, $nextStep);


        // INSERT --------------------------------------------------------------
        if ($sqlCheck->rowCount() == 0) {

            $config = config::getConfig('config');

            $idProject  = array(':id_project' => $config['id_project']);
            $domain     = array(':domain'     => $config['domain']);
            $addReqIp   = array(':ip'         => $_SERVER['REMOTE_ADDR']);
            $sqlHydrate = array_merge($sqlHydrate, $idProject, $domain, $addReqIp);

            // Mise en forme des champs à mettre à jour
            $sqlChp = implode(', ', $sqlChp);
            $sqlVal = implode(', ', $sqlVal);

            // Requête
            $req = "INSERT INTO         $table
                    ( id_project,  domain, date_crea, date_modif,  ip,  step, $sqlChp)
                    VALUES
                    (:id_project, :domain, NOW(),     NOW(),      :ip, :step, $sqlVal)";

            $sql = $this->_dbh->prepare($req);
            $sql->execute($sqlHydrate);

            $action = 'insert';


        // UPDATE --------------------------------------------------------------
        } else {

            $resCheck = $sqlCheck->fetch();

            if (!empty($email) && !empty($resCheck->email) && $this->_step != '0') {

                // Merge de l'email pour l'hydratation des requêtes
                $addReqEmail = array(':email' => $email);
                $sqlHydrate  = array_merge($sqlHydrate, $addReqEmail);

                // Mise en forme des champs à mettre à jour
                $addReqChp = array();
                for ($i=0; $i<count($sqlChp); $i++) {
                    if ($sqlChp[$i] != 'email') {
                        if ($sqlChp[$i] == 'stepLeadStatut' && $sqlVal[$i] <= $resCheck->stepLeadStatut) {
                            continue;
                        }

                        $addReqChp[] = $sqlChp[$i] . " = " . $sqlVal[$i];
                    }
                }
                $addReq  = "date_modif = NOW(), ";
                $addReq .= "step = :step, ";
                $addReq .= implode(', ', $addReqChp);

                // Requête
                $req = "UPDATE $table SET $addReq WHERE email = :email AND DATE_FORMAT(date_modif, '%Y-%m-%d') = CURDATE()";
                $sql = $this->_dbh->prepare($req);
                $sql->execute($sqlHydrate);

                $action = 'update';

            }

            if (isset($_SESSION['form']['nom']) && isset($_SESSION['form']['prenom']) && !empty($resCheck->nom) && !empty($resCheck->prenom) && $resCheck->stepLeadStatut == 0) {

                // Merge des nom et prénom pour l'hydratation des requêtes
                $addReqNom = array(
                                ':nom'    => $_SESSION['form']['nom'],
                                ':prenom' => $_SESSION['form']['prenom'],
                );

                $sqlHydrate  = array_merge($sqlHydrate, $addReqNom);

                // Mise en forme des champs à mettre à jour
                $addReqChp = array();
                for ($i=0; $i<count($sqlChp); $i++) {
                    $addReqChp[] = $sqlChp[$i] . " = " . $sqlVal[$i];
                }
                $addReq  = "date_modif = NOW(), ";
                $addReq .= "step = :step, ";
                $addReq .= implode(', ', $addReqChp);

                // Requête
                $req = "UPDATE $table SET $addReq WHERE nom = :nom AND prenom = :prenom AND stepLeadStatut = 0 AND DATE_FORMAT(date_modif, '%Y-%m-%d') = CURDATE()";
                $sql = $this->_dbh->prepare($req);
                $sql->execute($sqlHydrate);

                $action = 'update';
            }
        }


        // On rend lisibles les requêtes exécutées pour le débug ---------------
        $readReqCheck = str_replace(':email', "'" . $email . "'", $reqCheck);;

        if (isset($req)) {
            $readReq = $req;
            foreach ($sqlHydrate as $k => $v) {
                if (! is_numeric($v)) {
                    $v = "'" . $v . "'";
                }
                $readReq = str_replace($k, $v, $readReq);
            }
        } else {
            $readReq = '';
        }

        $this->_dbh->commit();

        return array(
            /* Debug */
            //'parcours'        => $_SESSION['form']['parcours'],   // Pour debug
            //'session'         => $_SESSION['form'],               // Pour debug
            //'readReqCheck'    => $readReqCheck,                   // Pour débug
            //'readReq'         => $readReq,                        // Pour débug
            //'hydrate'         => $sqlHydrate,                     // Pour débug

            /* Important */
            'action'          => $action,
        );
    }
}
