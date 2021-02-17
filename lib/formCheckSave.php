<?php
namespace tools;

use tools\dbSingleton;
use tools\config;
use tools\formFormat;

/**
 * Validation des formulaires
 * Gestion des erreurs
 * Stockage du résultat dans une variable de session et en BDD
 *
 * @author Daniel Gomes
 */
class formCheckSave
{
    /**
     * Attributs
     */
    protected $_dbh;            // Instance PDO
    protected $_table;          // Nom de la table en BDD

    protected $_form;           // Informations du formulaire
    protected $_err;            // Stockage des erreurs
    protected $_cta;            // Formulaire choisi
    protected $_step;           // Etape du formulaire
    protected $_nextStep;       // Etape du formulaire
    protected $_chp;            // Tableau des champs retournés par le formulaire
    protected $_callBack;       // Appel méthode callBack


    /**
     * Constructeur
     *
     * @param       string      $cta        Formulaire sélectionné
     * @param       string      $step       Etape du formulaire
     * @param       string      $chp        Champs envoyés
     */
    public function __construct($cta, $step, $chp, $callBack=null)
    {
        // Instance PDO
        $this->_dbh = dbSingleton::getInstance();

        // Récupération des informations du formulaire
        $path = 'confForms/';
        $this->_form = formFormat::checkFormatForm($cta, $path);

        // Récupération du nom de la table
        $this->_table = $this->_form['table'];

        // On génère la variable de session ['form'] si elle n'existe pas
        if (! isset($_SESSION['form'])) {
            $_SESSION['form'] = array();
        }

        // On génère la variable de session ['form']['parcours'] si elle n'existe pas
        if (! isset($_SESSION['form']['parcours'])) {
            $_SESSION['form']['parcours'] = array();
        }

        // Déclaration du tableau des erreurs
        $this->_err = array();

        // Méhode callBack
        $this->_callBack = $callBack;

        // Récupération du cta, de l'étape et des champs
        $this->_cta      = $cta;
        $this->_step     = $step;
        $this->_chp      = $chp;
    }


    /**
     * Getter
     */
    public function getChp()
    {
        return $this->_chp;
    }


    /**
     * Vérification du formulaire
     */
    public function checkForm()
    {
        // Champs à controler
        if (isset($this->_form['steps'][$this->_step]['fields'])) {
            $this->checkFields();
        }

        // Control d'un switch
        if (isset($this->_form['steps'][$this->_step]['switch'])) {
            $this->checkSwitch();
        }

        // Sauvegarde du formulaire dans une variable de session
        $resCallBack = array();
        if (count($this->_err) == 0) {
            $this->saveInSession();

            // Appel d'une méthode callBack
            if (! is_null($this->_callBack)) {
                $resCallBack = $this->{$this->_callBack}();
            }
        }

        // Retour des test sur le formulaire
        $resCheck = array(
            'erreursCount' => count($this->_err),
            'erreursInfos' => $this->_err,
        );

        return array_merge($resCheck, $resCallBack);
    }


    /**
     * Test de tous les champs d'un formulaire
     */
    protected function checkFields()
    {
        $fields = $this->_form['steps'][$this->_step]['fields'];

        // Boucle sur les champs  du post
        foreach ($this->_chp as $k => $v) {

            $check = true;

            // Champs obligatoires
            if (isset($fields[$k]['required']) && $fields[$k]['required'] === true) {
                $check = $this->checkRequired($v);
            }

            // Control si demandé
            if (isset($fields[$k]['control']) && $check === true) {

                // Control champ contenant des nombre de type 'integer' (espaces autorisés)
                switch ($fields[$k]['control'])
                {
                    // Vérifications classiques
                    case 'number'           : $check = $this->checkNumber($v);                              break;
                    case 'telephone'        : $check = $this->checkPhone($v);                               break;
                    case 'cp'               : $check = $this->checkCp($v);                                  break;
                    case 'email'            : $check = $this->checkEmail($v);                               break;

                    // Vérification de l'existance du code d'un parrain
                    case 'affiliateCode'    : $check = $this->checkAffiliateCode($v);                       break;

                    // Vérification de la validité d'un email et de sa présence en bdd
                    case 'emailExist'       :
                        $check = $this->checkEmailExist($v);
                        break;

                    // Vérification conformité mot de passe et vérification
                    case 'passwordCreate'   : $check = $this->checkPasswordCreate($v);                      break;
                    case 'passwordCompare'  :
                        foreach ($this->_chp as $k2 => $v2) {
                            if (isset($fields[$k2]['spe']) && $fields[$k2]['spe'] == 'passwordCreate') {
                                $passwordCreate = $v2;
                            }
                        }
                        $check = $this->checkPasswordCompare($v, $passwordCreate);
                        break;

                    // Vérification d'un couple email / password
                    case 'emailPassword'    :
                        foreach ($this->_chp as $k2 => $v2) {
                            if (isset($fields[$k2]['spe']) && $fields[$k2]['spe'] == 'email') {
                                $email = $v2;
                            }
                        }
                        $check = $this->checkEmailPassword($v, $email);
                        break;

                    // Vérification d'un couple email / password + licence
                    case 'emailPasswordLicence' :
                        foreach ($this->_chp as $k2 => $v2) {
                            if (isset($fields[$k2]['spe']) && $fields[$k2]['spe'] == 'email') {
                                $email = $v2;
                            }
                        }
                        $check = $this->checkEmailPasswordLicence($v, $email);
                        break;
                }
            }

            // Dès qu'un champ est en erreur, la boucle est soppée et le résultat renvoyé
            if (is_array($check) && $check['res'] === false) {
                $alertName = $check['alert'];
                $this->_err[$k] = $fields[$k][$alertName];
                break;
            }

            if (! is_array($check) && $check === false) {
                $this->_err[$k] = $fields[$k]['alert'];
                break;
            }
        }
    }


    /**
     * Test d'un formulaire 'switch'
     */
    protected function checkSwitch()
    {
        $switch = $this->_form['steps'][$this->_step]['switch'];

        $keys = array_keys($switch);
        $chp  = $keys[0];

        $val = $this->_chp[$chp];

        $check = $this->checkRequired($val);

        if ($check === false) {
            $this->_err[$chp] = $switch[$chp]['alert'];
        }
    }


    /**
     * Vérification des champs obligatoires
     *
     * @param   integer     $value      Valeur à controler
     * @param   boolean     $res        Valeur du retour par défaut
     * @return  boolean     $res
     */
    protected function checkRequired($value, $res=true)
    {
        if (is_array($value)) {
            if (count($value) == 0) {
                $res = false;
            }
        } else {
            if (strlen(trim($value)) == 0) {
                $res = false;
            }
        }

        return $res;
    }


    /**
     * Vérification des champs de type number
     *
     * @param   float       $value      Valeur à controler
     * @param   boolean     $res        Valeur du retour par défaut
     * @return  boolean     $res
     */
    protected function checkNumber($value, $res=true)
    {
        if (! empty($value)) {
            $value = str_replace(' ', '', $value);

            if (! is_numeric($value)) {
                $res = false;
            }
        }

        return $res;
    }


    /**
     * Vérification des numéros de téléphone
     *
     * @param   string      $value      Valeur à controler
     * @param   boolean     $res        Valeur du retour par défaut
     * @return  boolean     $res
     */
    protected function checkPhone($value, $res=true)
    {
        if (! empty($value)) {
            if (preg_match('/^(?:0|\(?\+33\)?\s?|0033\s?)[1-79](?:[\.\-\s]?\d\d){4}$/', $value) == 0) {
                $res = false;
            }
        }

        return $res;
    }


    /**
    * Vérification des codes postaux
    *
    * @param   integer     $value       Valeur à controler
    * @param   boolean     $res         Valeur du retour par défaut
    * @return  boolean     $res
    */
    protected function checkCp($value, $res=true)
    {
        if (! empty($value)) {
            if ( $this->checkNumber($value) === false || intval($value) < 1000 || intval($value) > 97680) {
                $res = false;
            }
        }

        return $res;
    }


    /**
     * Vérification des adresses emails
     *
     * @param   string      $value      Valeur à controler
     * @param   boolean     $res        Valeur du retour par défaut
     * @return  boolean     $res
     */
    protected function checkEmail($value, $res=true)
    {
        if (! empty($value)) {
            if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $res = false;
            }
        }

        return $res;
    }


    /**
     * Vérification des adresses emails et de leur existance en base de données
     *
     * @param   string      $value      Valeur à controler
     * @param   boolean     $res        Valeur du retour par défaut
     * @return  boolean     $res
     */
    protected function checkEmailExist($value, $res=true)
    {
        if (! empty($value)) {

            // Vérification de la validité de l'email
            if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return array(
                    'res'   => false,
                    'alert' => 'alert1',
                );
            }

            // Vérification de l'existance ou non de l'email en BDD
            $table = $this->_table;

            $req = "SELECT email FROM $table WHERE email = :email";
            $sql = $this->_dbh->prepare($req);
            $sql->execute(array( ':email' => $value ));

            if ($sql->rowCount() > 0) {
                return array(
                    'res'   => false,
                    'alert' => 'alert2',
                );
            }
        }

        return array(
            'res'   => true,
            'alert' => '',
        );
    }


    /**
     * Vérification des adresses emails
     *
     * @param   string      $value      Valeur à controler
     * @param   boolean     $res        Valeur du retour par défaut
     * @return  boolean     $res
     */
    protected function checkAffiliateCode($value, $res=true)
    {
        if (! empty($value)) {

            // Vérification de l'existance du code afflilié en BDD
            $table = $this->_table;

            $req = "SELECT id FROM $table WHERE code_affil = :code_affil";
            $sql = $this->_dbh->prepare($req);
            $sql->execute(array( ':code_affil' => $value ));

            if ($sql->rowCount() == 0) {
                $res = false;
            }
        }

        return $res;
    }


    /**
     * Vérification de la validité des mots de passe
     *
     * @param   integer     $value      Valeur à controler
     * @param   boolean     $res        Valeur du retour par défaut
     * @return  boolean     $res
     */
    protected function checkPasswordCreate($value)
    {
        if (! empty($value)) {
            if (strlen($value) < 8) {
                return array(
                    'res'   => false,
                    'alert' => 'alert2',
                );
            }
            if (!preg_match("#[0-9]+#", $value)) {
                return array(
                    'res'   => false,
                    'alert' => 'alert3',
                );
            }
            if (!preg_match("#[a-z]+#", $value)) {
                return array(
                    'res'   => false,
                    'alert' => 'alert4',
                );
            }
            if (!preg_match("#[A-Z]+#", $value)) {
                return array(
                    'res'   => false,
                    'alert' => 'alert5',
                );
            }
        }

        return array(
            'res'   => true,
            'alert' => '',
        );
    }


    /**
     * Vérification des adresses emails
     *
     * @param   string      $value      Deuxième mot de passe pour vérifier
     * @param   string      $password   Permier mot de passe saisi
     * @param   boolean     $res        Valeur du retour par défaut
     * @return  boolean     $res
     */
    protected function checkPasswordCompare($value, $password, $res=true)
    {
        if (! empty($value)) {
            if ($value != $password) {
                $res = false;
            }
        }

        return $res;
    }


    /**
     * Vérification du couple email / password pour une connexion
     *
     * @param   string      $value      Deuxième mot de passe pour vérifier
     * @param   string      $password   Permier mot de passe saisi
     * @param   boolean     $res        Valeur du retour par défaut
     * @return  boolean     $res
     */
    protected function checkEmailPassword($value, $email)
    {
        if (! empty($value)) {

            // Vérification de l'existance du couple email mot de passe
            $table = $this->_table;

            $req = "SELECT passwd FROM $table WHERE email = :email";
            $sql = $this->_dbh->prepare($req);
            $sql->execute(array( ':email' => $email ));

            if ($sql->rowCount() > 0) {

                $res = $sql->fetch();

                $crypt  = new \tools\crypt();
                $passwd = $crypt->decrypt($res->passwd);

                if ($value != $passwd) {
                    return array(
                        'res'   => false,
                        'alert' => 'alert2',
                    );
                }

            } else {

                return array(
                    'res'   => false,
                    'alert' => 'alert2',
                );
            }
        }

        return array(
            'res'   => true,
            'alert' => '',
        );
    }


    /**
     * Vérification des adresses emails
     *
     * @param   string      $value      Deuxième mot de passe pour vérifier
     * @param   string      $password   Permier mot de passe saisi
     * @param   boolean     $res        Valeur du retour par défaut
     * @return  boolean     $res
     */
    protected function checkEmailPasswordLicence($value, $email)
    {
        $check = $this->checkEmailPassword($value, $email);

        // Couple email / password incorrect
        if ( $check['res'] == false ) {
            return $check;
        }

        // Vérification de la licence
        $table = $this->_table;

        $req = "SELECT id FROM $table WHERE email = :email";
        $sql = $this->_dbh->prepare($req);
        $sql->execute(array( ':email' => $email ));

        $res = $sql->fetch();
        $id_user = $res->id;

        $req = "SELECT id FROM orders WHERE id_user = :id_user AND date_start <= CURDATE() AND date_end >= CURDATE()";
        $sql = $this->_dbh->prepare($req);
        $sql->execute(array( ':id_user' => $id_user ));

        if ( $sql->rowCount() > 0 ) {
            return array(
                'res'   => true,
                'alert' => '',
            );
        } else {
            return array(
                'res'   => false,
                'alert' => 'alert3',
            );
        }
    }


    /**
     * Sauvegarde du formulaire dans la variable de session
     */
    protected function saveInSession()
    {
        // Stockage du parcours dans le formulaire - pour la navigation (prev|next)
        $_SESSION['form']['parcours'][] = array(
            'cta'  => $this->_cta,
            'step' => $this->_step,
        );

        $_SESSION['form']['cta']  = $this->_cta;
        $_SESSION['form']['step'] = $this->_step;

        // Merge l'étape envoyé dans SESSION['form']
        $_SESSION['form'] = array_merge($_SESSION['form'], $this->_chp);
    }
}
