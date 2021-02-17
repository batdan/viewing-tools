<?php
namespace tools;

use tools\config;
use tools\formCreate;

/**
 * Gestion des retours dans un formulaire
 *
 * @author Daniel Gomes
 */
class formBack
{
    /**
     * Attributs
     */
    private $_dbh;              // Instance PDO
    private $_form;             // Configuration du site

    private $_cta;              // CTA
    private $_step;             // Step


    /**
     * Constructeur
     *
     * @param       string      $cta        Formulaire sélectionné
     * @param       string      $step       Etape du formulaire
     */
    public function __construct()
    {
        // Instance PDO
        $this->_dbh = dbSingleton::getInstance();

        // Récupération des informations du formulaire
        $path = 'confForms/';
        $this->_form = formFormat::checkFormatForm($_SESSION['form']['cta'], $path);

        // Hydratation des attributs
        $this->_cta  = $_SESSION['form']['cta'];
        $this->_step = $_SESSION['form']['step'];
    }


    /**
     * Suppression de la dernière entrée en base de données
     */
    public function backBdd()
    {
        $table = $this->_form['table'];
        $chps  = array();

        // Les données ne seront effacés que dans le cas d'un enregistrement d'une étape ultérieure à la validation du lead
        if ($this->checkStepForErase() == 'true') {

            if (isset($this->_form['steps'][$this->_step]['fields'])) {
                $chps = array_keys($this->_form['steps'][$this->_step]['fields']);
            }

            if (isset($this->_form['steps'][$this->_step]['switch']) && !isset($this->_form['steps'][$this->_step]['switch']['cta'])) {
                $chps = array_keys($this->_form['steps'][$this->_step]['switch']);
            }

            if (count($chps) > 0) {

                // Récupération de la structure de la table
                $req = "SHOW COLUMNS FROM $table";
                $sql = $this->_dbh->query($req);

                $fieldType = array();
                while ($res = $sql->fetch()) {

                    $type = explode('(', $res->Type);
                    $type = $type[0];

                    $fieldType[$res->Field] = $type;
                }

                // Type de champs qui doivent passer à 0
                $decType = array('int', 'decimal', 'float');

                // Création d'un tableau avec les champs à mettre à jour dans la requête
                $update  = array();
                foreach($chps as $chp) {
                    $update[] = $chp . ' = :' . $chp;
                }
                $update = implode(', ', $update);

                // Réinitialisation des entrées dans la base de données
                try {
                    $req = "UPDATE $table SET $update WHERE email = :email AND DATE_FORMAT(date_modif, '%Y-%m-%d') = CURDATE()";
                    $sql = $this->_dbh->prepare($req);

                    foreach ($chps as $chp) {

                        if (in_array($fieldType[$chp], $decType)) {
                            $sql->bindValue(":$chp", 0,     \PDO::PARAM_INT);
                        } else {
                            $sql->bindValue(":$chp", NULL,  \PDO::PARAM_NULL);
                        }
                    }

                    $sql->bindParam(":email", $_SESSION['form']['email']);
                    $sql->execute();
                } catch (\Exception $e) {

                }
            }
        }
    }


    /**
     * Les données ne seront effacés que dans le cas d'un enregistrement
     * d'une étape ultérieure à la validation du lead
     */
    private function checkStepForErase()
    {
        $table = $this->_form['table'];
        $steps = array_keys($this->_form['steps']);
        $stepLeadOk = $this->_form['stepLeadOk'];

        $req = "SELECT step FROM $table WHERE email = :email AND DATE_FORMAT(date_modif, '%Y-%m-%d') = CURDATE()";
        $sql = $this->_dbh->prepare($req);
        $sql->execute( array(":email" => $_SESSION['form']['email']));
        $res = $sql->fetch(\PDO::FETCH_ASSOC);

        $lastStep = $res['step'];

        foreach ($steps as $k=>$v) {
            if ($v == $stepLeadOk) {
                $keyStepLeadOk = $k;
            }
            if ($v == $lastStep) {
                $keyLastStep = $k;
            }
        }

        if ($keyLastStep > $keyStepLeadOk) {
            return 'true';
        } else {
            return 'false';
        }
    }


    /**
     * Positionnement des CTA et STEP pour préparer l'affichage de la page précédente
     */
    public function backPage()
    {
        array_pop($_SESSION['form']['parcours']);
        $keys = array_keys($_SESSION['form']['parcours']);
        $prev = max($keys);

        // Mise à jour de la variable de session pour atteindre l'étape précédente
        $_SESSION['form']['cta']  = $_SESSION['form']['parcours'][$prev]['cta'];
        $_SESSION['form']['step'] = $_SESSION['form']['parcours'][$prev]['step'];
    }
}
