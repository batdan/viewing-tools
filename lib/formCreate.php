<?php
namespace tools;

use tools\dbSingleton;
use tools\config;
use tools\formFormat;

/**
 * Création de formulaires
 *
 * @author Daniel Gomes
 */
class formCreate
{
    /**
     * Attributs
     */
    private $_dbh;                  // Instance PDO

    private $_dom;                  // Objet DOM du formulaire
    private $_form;                 // Informations du formulaire
    private $_prevCta;              // Sauvegarde du formulaire appelé dans l'étape précédente
    private $_cta;                  // Formulaire choisi
    private $_prevStep;             // Etape du formulaire qui vient d'être envoyée
    private $_step;                 // Etape du formulaire à afficher
    private $_H1;                   // Titre H1

    private $_focus = 'null';       // Champ sur lequel se fera le focus au chargement de l'étape

    private $_classLabel = 'col-lg-4 col-sm-4 col-xs-12';   // Classe par défaut des éléments de formulaire
    private $_classInput = 'col-lg-5 col-sm-7 col-xs-12';   // Classe par défaut des éléments de formulaire

    private $_attrFilter = array('tag', 'label', 'alert', 'icon', 'required');  // Liste d'exclusion d'Attributs lors de la création de champs


    /**
     * Constructeur
     *
     * @param       string      $cta        Formulaire sélectionné
     * @param       string      $prevStep   Etape du formulaire qui vient d'être envoyée
     */
    public function __construct($cta, $prevStep)
    {
        // Instance PDO
        $this->_dbh = dbSingleton::getInstance();

        // Déclaration de l'élément DOM
        $this->_dom = new \DOMDocument("1.0", "utf-8");

        // Récupération des informations du formulaire
        $path = 'confForms/';
        $this->_form = formFormat::checkFormatForm($cta, $path);

        // Récupération du cta, de l'étape et des champs
        $this->_cta      = $cta;
        $this->_prevCta  = $cta;
        $this->_prevStep = $prevStep;

        // Récupération de l'étape à afficher
        $this->recupStep();

        // Récupération du titre H1 s'il est renseigné
        $this->_H1 = '';
        if (! empty($this->_form['steps'][$this->_step]['H1'])) {
            $this->_H1 = $this->_form['steps'][$this->_step]['H1'];
        }
    }


    /**
     * Getters
     */
    public function getCta() {
        return $this->_cta;
    }

    public function getStep() {
        return $this->_step;
    }

    public function getH1() {
        return $this->_H1;
    }

    public function getFocus() {
        return $this->_focus;
    }


    /**
     * Récupération de l'étape à afficher
     * et mise à jour du CTA si nécessaire
     */
    private function recupStep()
    {
        // Le formulaire précédent était un fomulaire classique
        if (isset($this->_form['steps'][$this->_prevStep]['fields'])) {

            // Passage à la nouvelle étape
            $this->_step = $this->_form['steps'][$this->_prevStep]['nextStep'];
        }

        // Le formulaire précédent était un switch
        if (isset($this->_form['steps'][$this->_prevStep]['switch'])) {

            // Récupération du nom du champ
            $keys = array_keys($this->_form['steps'][$this->_prevStep]['switch']);
            $chp  = $keys[0];

            // Récupération de la valeur retournée par le switch
            $val = $_SESSION['form'][$chp];

            // Passage à la nouvelle étape
            $this->_step = $this->_form['steps'][$this->_prevStep]['nextStep'][$val]['nextStep'];
        }

        // On tag le lead comme ayant atteint l'étape de validation
        if ($this->_prevStep == $this->_form['stepLeadOk']) {
            $this->stepLeadStatut(1);
        }

        // Actions fin de formulaire
        if ($this->_step == 'fin') {

            // On tag le lead comme ayant atteint l'étape de fin
            $this->stepLeadStatut(2);

            // On détruit la session de formulaire
            unset($_SESSION['form']);
        }
    }


    /**
     * On tag le lead s'il passe l'étape de validation
     * et une seconde fois à l'étape de fin
     */
    private function stepLeadStatut($statut)
    {
        $table = $this->_form['table'];

        $req = "UPDATE          $table

                SET             step = :step,
                                stepLeadStatut = :stepLeadStatut

                WHERE           email = :email
                AND             DATE_FORMAT(date_modif, '%Y-%m-%d') = CURDATE()";

        $sql = $this->_dbh->prepare($req);
        $sql->execute( array(
                            ':step'             => $this->_step,
                            ':stepLeadStatut'   => $statut,
                            ':email'            => $_SESSION['form']['email'],
        ));
    }


    /**
     * Suppression d'un lead si l'internaute change de CTA
     * Création du nouveau lead dans le nouveau CTA
     */
    private function deleteAndCreateNewLead($oldCta)
    {
        $path = 'confForms/';
        $oldForm = formFormat::checkFormatForm($oldCta, $path);

        // Delete old lead -----------------------------------------------------
        $table = $oldForm['table'];

        $req = "DELETE FROM $table WHERE email = :email AND DATE_FORMAT(date_modif, '%Y-%m-%d') = CURDATE()";
        $sql = $this->_dbh->prepare($req);
        $sql->execute( array(':email' => $_SESSION['form']['email']));


        // Create new lead -----------------------------------------------------
        $table = $this->_form['table'];

        $config = config::getConfig('config');

        // Check si existe déjà dans les leads du jour pour cet email
        $reqCheck = "SELECT id FROM $table WHERE email = :email AND DATE_FORMAT(date_modif, '%Y-%m-%d') = CURDATE()";
        $sqlCheck = $this->_dbh->prepare($reqCheck);
        $sqlCheck->execute(array(':email' => $_SESSION['form']['email']));

        // Lead existe, on supprime
        if ($sqlCheck->rowCount() > 0) {
            $req = "DELETE FROM $table WHERE email = :email AND DATE_FORMAT(date_modif, '%Y-%m-%d') = CURDATE()";
            $sql = $this->_dbh->prepare($req);
            $sql->execute(array(':email' => $_SESSION['form']['email']));
        }

        // Création du nouveau lead
        $req = "INSERT INTO $table (id_project, domain, date_crea, date_modif, ip, step, email) VALUES (:id_project, :domain, NOW(), NOW(), :ip, :step, :email)";
        $sql = $this->_dbh->prepare($req);
        $sql->execute(array(
            ':id_project'   => $config['id_project'],
            ':domain'       => $config['domain'],
            ':ip'           => $_SERVER['REMOTE_ADDR'],
            ':step'         => $this->_prevStep,
            ':email'        => $_SESSION['form']['email']
        ));
    }


    /**
     * Retourne le rendu du formulaire
     *
     * @return string
     */
    public function render()
    {
        $form = $this->_dom->createElement('form');
        $form->setAttribute('class', 'form-horizontal');
        $this->_dom->appendChild($form);

        // Formulaire classique
        if (isset($this->_form['steps'][$this->_step]['fields'])) {
            $fields = $this->_form['steps'][$this->_step]['fields'];
        }

        // Switch
        if (isset($this->_form['steps'][$this->_step]['switch'])) {
            $switch = $this->_form['steps'][$this->_step]['switch'];
        }

        // Création des champs cachés par défaut CTA et Step
        if ((isset($fields) && !array_key_exists('cta', $fields)) || (isset($switch) && !array_key_exists('cta', $switch))) {
            $this->chpInputHidden('cta',  $this->_cta);
        }
        if ((isset($fields) && !array_key_exists('step', $fields)) || (isset($switch) && !array_key_exists('step', $switch))) {
            $this->chpInputHidden('step', $this->_step);
        }

        // Création de la progressBar
        if (isset($this->_form['steps'][$this->_step]['progressBar'])) {
            $this->progressBar();
        }

        // Texte de fin de formulaire
        if (isset($this->_form['steps'][$this->_step]['txtEndForm'])) {
            $this->txtEndForm();
        }

        // Texte de fin de formulaire
        if (isset($this->_form['steps'][$this->_step]['addHTML'])) {
            $this->formAddHTML();
        }

        // Code à ajouter sans DOM
        if (isset($this->_form['steps'][$this->_step]['addTxtWithoutDOM'])) {
            $addTxtWithoutDOM = $this->_form['steps'][$this->_step]['addTxtWithoutDOM'];
            $this->addTxtWithoutDOM();
        }

        // Bouton avec un lien spécifique - exemple -> retour à la home
        if (isset($this->_form['steps'][$this->_step]['btnSpe'])) {
            $this->btnSpe();
        }

        // Création du formulaire classique
        if (isset($fields)) {
            $this->createFormElement($fields);
        }

        // Création du Switch
        if (isset($this->_form['steps'][$this->_step]['switch'])) {
            $keys = array_keys($switch);
            $this->chpInputRadio($keys[0], $switch[$keys[0]], 'switch');
        }

        // Récupération des boutons de control
        $this->formControl();

        $html = $this->_dom->saveHTML();

        // Insertion du code sans DOM
        if (isset($addTxtWithoutDOM)) {
            $html = str_replace( "___noDOM___", $addTxtWithoutDOM, $html );
        }

        return $html;
    }


    /**
     * Création de tous les éléments du formulaire
     */
    private function createFormElement($fields)
    {
        foreach ($fields as $k => $v) {

            // Création des champs input de type 'hidden'
            if ($v['tag'] == 'input' && $v['type'] == 'hidden') {
                $this->chpInputHidden($k, $v);
            }

            // Création des champs input de type 'text, passwd, email, url et number'
            $type_classic = array('text', 'password', 'email', 'url', 'number');
            if ($v['tag'] == 'input' && in_array($v['type'], $type_classic)) {
                $this->addFocus($k);
                $this->chpInputClassic($k, $v);
            }

            // Création des champs mot de passe et vérification
            if ($v['tag'] == 'input' && !in_array($v['type'], $type_classic) && isset($v['spe']) && $v['spe'] == 'passwordCreate') {
                $this->addFocus($k);
                $this->chpInputPasswordCreate($k, $v);
            }
            if ($v['tag'] == 'input' && !in_array($v['type'], $type_classic) && isset($v['spe']) && $v['spe'] == 'passwordCompare') {
                $this->addFocus($k);
                $this->chpInputPasswordCompare($k, $v);
            }

            // Création des champs input de type 'text' servant à afficher une date
            if ($v['tag'] == 'input' && $v['type'] == 'date') {
                $this->chpInputDate($k, $v);
            }

            // Création des champs input de type 'radio'
            if ($v['tag'] == 'input' && $v['type'] == 'radio') {
                if (isset($v['orientation']) && ($v['orientation'] == 'horizontal' || $v['orientation'] == 'horizontale')) {
                    $this->chpInputRadioHorizontal($k, $v);
                } else {
                    $this->chpInputRadio($k, $v);
                }
            }

            // Création des champs input de type 'checkbox' (dans le style du navigateur)
            if ($v['tag'] == 'input' && $v['type'] == 'checkbox-classic') {
                $this->chpInputCheckboxClassic($k, $v);
            }

            // Création des champs input de type 'checkbox' (dans un style amélioré)
            if ($v['tag'] == 'input' && $v['type'] == 'checkbox') {
                $this->chpInputCheckbox($k, $v);
            }

            // Création des listes déroulantes
            if ($v['tag'] == 'select') {
                $this->chpSelect($k, $v);
            }

            // Création des champs 'textarea'
            if ($v['tag'] == 'textarea') {
                $this->addFocus($k);
                $this->chpTextarea($k, $v);
            }
        }
    }


    /**
     * Champ sur lequel faire un focus au chargement de l'étape
     *
     * @param   string      $name       Nom du champ
     */
    private function addFocus($name)
    {
        if ($this->_focus == 'null' && empty($_SESSION['form'][$name])) {
            $this->_focus = $name;
        }
    }


    /**
	 * Création des champs input de type 'hidden'
	 */
	private function chpInputHidden($k, $v)
	{
        if (is_array($v)) {
            $value = $v['value'];
        } else {
            $value = $v;
        }

        // id champ
        $idChp = $k . '_id';

        $chp = $this->_dom->createElement('input');
        $chp->setAttribute('type',  'hidden');
        $chp->setAttribute('name',  $k);
        $chp->setAttribute('id',    $idChp);
        $chp->setAttribute('value', $value);

        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($chp);
    }


    /**
	 * Affichage des champs input de type 'text', 'password', 'email', 'url' ou 'number'
	 */
	private function chpInputClassic($k, $v)
	{
        // id champ
        $idChp = $k . '_id';

        // Conteneur
        $container = $this->_dom->createElement('div');
        $container->setAttribute('class', 'form-group');

        $label = $this->_dom->createElement('label');
        $label->setAttribute('for', $idChp);
        $label->setAttribute('class', $this->_classLabel . ' control-label');

        if (! empty($v['label'])) {
            $label->appendChild( $this->addHtml($v['label']) );
            unset($v['placeholder']);
        }

        $container->appendChild($label);

        // Bloc champ
        $div = $this->_dom->createElement('div');
        $div->setAttribute('class', $this->_classInput);

        $chp = $this->_dom->createElement( $v['tag'] );
        $chp->setAttribute('name', $k);
        $chp->setAttribute('id', $idChp);

        foreach($v as $attr => $value) {
            if (! in_array($attr, $this->_attrFilter)) {
                $chp->setAttribute($attr, $value);
            }
        }

        // Récupération des champs de formulaires dans la variable de session s'ils sont déjà renseignés
        if (! empty($_SESSION['form'][$k])) {
            $chp->setAttribute('value', $_SESSION['form'][$k]);
        }

        if (! empty($v['icon'])) {
            $divGroup =  $this->_dom->createElement('div');
            $divGroup->setAttribute('class', 'input-group');

            $divIcon = $this->_dom->createElement('div');
            $divIcon->setAttribute('class', 'input-group-addon w40');

            $icon = $this->_dom->createElement('i');
            $icon->setAttribute('class', $v['icon']);

            $chp->setAttribute('style', 'border-top-left-radius:0; border-bottom-left-radius:0; border-left:none;');

            $divIcon->appendChild($icon);
            $divGroup->appendChild($divIcon);
            $divGroup->appendChild($chp);
            $div->appendChild($divGroup);
        } else {
            $div->appendChild($chp);
        }

        $container->appendChild($div);

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
     * Affichage des champs pour la création d'un password et sa vérification
     */
    private function chpInputPasswordCreate($k, $v)
    {
        // id champ
        $idChp = $k . '_id';

        // Conteneur Champ passwd ----------------------------------------------
        $container = $this->_dom->createElement('div');
        $container->setAttribute('class', 'form-group');

        $label = $this->_dom->createElement('label');
        $label->setAttribute('for', $idChp);
        $label->setAttribute('class', $this->_classLabel . ' control-label');

        if (! empty($v['label'])) {
            $label->appendChild( $this->addHtml($v['label']) );
            unset($v['placeholder']);
        }

        $container->appendChild($label);

        // Bloc champ
        $div = $this->_dom->createElement('div');
        $div->setAttribute('class', $this->_classInput);

        $chp = $this->_dom->createElement( $v['tag'] );
        $chp->setAttribute('name', $k);
        $chp->setAttribute('id', $idChp);

        foreach($v as $attr => $value) {
            if (! in_array($attr, $this->_attrFilter)) {
                $chp->setAttribute($attr, $value);
            }
        }

        // Récupération des champs de formulaires dans la variable de session s'ils sont déjà renseignés
        if (! empty($_SESSION['form'][$k])) {
            $chp->setAttribute('value', $_SESSION['form'][$k]);
        }

        if (! empty($v['icon'])) {
            $divGroup =  $this->_dom->createElement('div');
            $divGroup->setAttribute('class', 'input-group');

            $divIcon = $this->_dom->createElement('div');
            $divIcon->setAttribute('class', 'input-group-addon w40');

            $icon = $this->_dom->createElement('i');
            $icon->setAttribute('class', $v['icon']);

            $chp->setAttribute('style', 'border-top-left-radius:0; border-bottom-left-radius:0; border-left:none;');

            $divIcon->appendChild($icon);
            $divGroup->appendChild($divIcon);
            $divGroup->appendChild($chp);
            $div->appendChild($divGroup);
        } else {
            $div->appendChild($chp);
        }

        $container->appendChild($div);

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
     * Affichage des champs pour la création d'un password et sa vérification
     */
    private function chpInputPasswordCompare($k, $v)
    {
        // id champ
        $idChp = $k . '_id';

        // Conteneur Champ check passwd ----------------------------------------
        $container = $this->_dom->createElement('div');
        $container->setAttribute('class', 'form-group');

        $label = $this->_dom->createElement('label');
        $label->setAttribute('for', $idChp);
        $label->setAttribute('class', $this->_classLabel . ' control-label');

        if (! empty($v['label'])) {
            $label->appendChild( $this->addHtml($v['label']) );
            unset($v['placeholder']);
        }

        $container->appendChild($label);

        // Bloc champ
        $div = $this->_dom->createElement('div');
        $div->setAttribute('class', $this->_classInput);

        $chp = $this->_dom->createElement( $v['tag'] );
        $chp->setAttribute('name', $k . '_check');
        $chp->setAttribute('id', $idChp . '_check');

        foreach($v as $attr => $value) {
            if (! in_array($attr, $this->_attrFilter)) {
                $chp->setAttribute($attr, $value);
            }
        }

        // Récupération des champs de formulaires dans la variable de session s'ils sont déjà renseignés
        if (! empty($_SESSION['form'][$k])) {
            $chp->setAttribute('value', $_SESSION['form'][$k]);
        }

        if (! empty($v['icon'])) {
            $divGroup =  $this->_dom->createElement('div');
            $divGroup->setAttribute('class', 'input-group');

            $divIcon = $this->_dom->createElement('div');
            $divIcon->setAttribute('class', 'input-group-addon w40');

            $icon = $this->_dom->createElement('i');
            $icon->setAttribute('class', $v['icon']);

            $chp->setAttribute('style', 'border-top-left-radius:0; border-bottom-left-radius:0; border-left:none;');

            $divIcon->appendChild($icon);
            $divGroup->appendChild($divIcon);
            $divGroup->appendChild($chp);
            $div->appendChild($divGroup);
        } else {
            $div->appendChild($chp);
        }

        $container->appendChild($div);

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
	 * Affichage des champs date
	 */
	private function chpInputDate($k, $v)
    {
        $format   = 'YYYY-MM-DD';
        $iconeImg = 'mdi mdi-calendar-question';

        if (! empty($v['format'])) {
			switch ($v['format']) {
				case 'datetime' :
                    $format   = 'DD-MM-YYYY HH:mm';
                    $iconeImg = 'mdi mdi-calendar-clock';
					break;
				case 'date' :
					$format   = 'DD-MM-YYYY';
					$iconeImg = 'mdi mdi-calendar-question';
					break;
				case 'time' :
					$format   = 'LT';
					$iconeImg = 'mdi mdi-clock';
					break;
			}
		}

        // id champ
        $idChp = $k . '_id';

        // Conteneur
        $container = $this->_dom->createElement('div');
        $container->setAttribute('class', 'form-group');

        $label = $this->_dom->createElement('label');
        $label->setAttribute('for', $idChp);
        $label->setAttribute('class', $this->_classLabel . ' control-label');

        if (! empty($v['label'])) {
            $label->appendChild( $this->addHtml($v['label']) );
            unset($v['placeholder']);
        }

        $container->appendChild($label);

        // Bloc champ
        $div = $this->_dom->createElement('div');
        $div->setAttribute('class', $this->_classInput);

        $chp = $this->_dom->createElement( $v['tag'] );
        $chp->setAttribute('name', $k);
        $chp->setAttribute('id', $idChp);

        foreach($v as $attr => $value) {
            if (! in_array($attr, $this->_attrFilter)) {
                $chp->setAttribute($attr, $value);
            }
        }

        $chp->setAttribute('type', 'text');
        $chp->setAttribute('format', $format);
        $chp->setAttribute('dateTimePicker', '1');

        // Récupération des champs de formulaires dans la variable de session s'ils sont déjà renseignés
        if (! empty($_SESSION['form'][$k])) {
            $chp->setAttribute('value', $_SESSION['form'][$k]);
        }

        $divGroup =  $this->_dom->createElement('div');
        $divGroup->setAttribute('class', 'input-group');

        $divIcon = $this->_dom->createElement('div');
        $divIcon->setAttribute('class', 'input-group-addon w40');

        $icon = $this->_dom->createElement('i');
        $icon->setAttribute('class', $iconeImg);

        $chp->setAttribute('style', 'border-top-left-radius:0; border-bottom-left-radius:0; border-left:none;');

        $divIcon->appendChild($icon);
        $divGroup->appendChild($divIcon);
        $divGroup->appendChild($chp);
        $div->appendChild($divGroup);

        $container->appendChild($div);

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
	 * Affichage des champs textarea
	 */
	private function chpTextarea($k, $v)
    {
        // id champ
        $idChp = $k . '_id';

        // Conteneur
        $container = $this->_dom->createElement('div');
        $container->setAttribute('class', 'form-group');

        $label = $this->_dom->createElement('label');
        $label->setAttribute('for', $idChp);
        $label->setAttribute('class', $this->_classLabel . ' control-label');

        if (! empty($v['label'])) {
            $label->appendChild( $this->addHtml($v['label']) );
            unset($v['placeholder']);
        }

        $container->appendChild($label);

        // Bloc champ
        $div = $this->_dom->createElement('div');
        $div->setAttribute('class', $this->_classInput);

        $chp = $this->_dom->createElement( $v['tag'] );
        $chp->setAttribute('name', $k);
        $chp->setAttribute('id', $idChp);

        foreach($v as $attr => $value) {
            if (! in_array($attr, $this->_attrFilter)) {
                $chp->setAttribute($attr, $value);
            }
        }

        // Désactivé pour les formulaires multiples
        // Récupération du texte dans la variables de session s'il est déjà renseignés
        // if (! empty($_SESSION['form'][$k])) {
        //    $chpTxt = $this->_dom->createTextNode( $_SESSION['form'][$k] );
        //    $chp->appendChild($chpTxt);
        // }

        $div->appendChild($chp);
        $container->appendChild($div);

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
	 * Affichage des champs input de type 'radio' vertical
	 */
	private function chpInputRadio($k, $v, $stepType=null)
    {
        // id champ
        $idChp = $k . '_id';

        // Conteneur
        $container = $this->_dom->createElement('div');
        $container->setAttribute('class', 'form-group');

        // Bloc label
        $blocLabel = $this->_dom->createElement('label');
        $blocLabel->setAttribute('for', $idChp);
        $blocLabel->setAttribute('class', $this->_classLabel . ' control-label');

        if (! empty($v['label'])) {
            $blocLabel->appendChild( $this->addHtml($v['label']) );
        }

        $container->appendChild($blocLabel);

        // Bloc champ
        $div = $this->_dom->createElement('div');
		$div->setAttribute('class', $this->_classInput);

		$radioContainer = $this->_dom->createElement('div');
		$radioContainer->setAttribute('class', 'btn-group-vertical');
		$radioContainer->setAttribute('data-toggle', 'buttons');
		$radioContainer->setAttribute('id' , $idChp);
		$radioContainer->setAttribute('type', 'group-radio');

		if (empty($v['values'])) {
            die ("Le champ radio '" . $k . "' n'a pas de tableau de données");
		} else {

            $i=0;
            foreach ($v['values'] as $key => $val) {

				$label = $this->_dom->createElement('label');
				$label->setAttribute('class', 'btn btn-radio pull-left');
                if (! is_null($stepType)) {
                    $label->setAttribute('stepType', $stepType);
                }

				$input = $this->_dom->createElement('input');
				$input->setAttribute('type', 'radio');
				$input->setAttribute('name',  $k);
				$input->setAttribute('id',    $k . '_id_' . $i);
				$input->setAttribute('value', $key);

                $icon = $this->_dom->createElement('i');
                $icon->setAttribute('class', 'mdi mdi-radiobox-blank pull-left');

                // Si un bouton radio est déjà activé ou une valeur par défaut est poussée
                if (!empty($v['default'])) {
                    if ($key == $v['default']) {
                        $label->setAttribute('class', 'btn btn-radio active pull-left');
                        $input->setAttribute('checked', true);
                        $icon->setAttribute('class', 'mdi mdi-radiobox-marked pull-left');
                    }
                } else {
                    if (isset($_SESSION['form'][$k]) && $_SESSION['form'][$k] == $key) {
                        $label->setAttribute('class', 'btn btn-radio active pull-left');
                        $input->setAttribute('checked', true);
                        $icon->setAttribute('class', 'mdi mdi-radiobox-marked pull-left');
                    }
                }

                $labelText  = $this->_dom->createTextNode($val);

                $spanTxt    = $this->_dom->createElement('span');
                $spanTxt->setAttribute('style', 'display:inline-block; margin-left:10px; position:relative; top:2px;');
                $spanTxt->setAttribute('class', 'pull-left');
                // $spanTxt->appendChild($labelText);

                $spanTxt->appendChild($this->addHtml($val));

				$label->appendChild($icon);
				$label->appendChild($input);
				$label->appendChild($spanTxt);
				$radioContainer->appendChild($label);

				$i++;
			}
		}

		$div->appendChild($radioContainer);
        $container->appendChild($div);

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
	 * Affichage des champs input de type 'radio' horizontal
	 */
	private function chpInputRadioHorizontal($k, $v)
    {
        // id champ
        $idChp = $k . '_id';

        // Conteneur
        $container = $this->_dom->createElement('div');
        $container->setAttribute('class', 'form-group');

        // Bloc label
        $blocLabel = $this->_dom->createElement('label');
        $blocLabel->setAttribute('for', $idChp);
        $blocLabel->setAttribute('class', $this->_classLabel . ' control-label');

        if (! empty($v['label'])) {
            $blocLabel->appendChild( $this->addHtml($v['label']) );
        }

        $container->appendChild($blocLabel);

        // Bloc champ
        $div = $this->_dom->createElement('div');
		$div->setAttribute('class', $this->_classInput);

		$radioContainer = $this->_dom->createElement('div');
		$radioContainer->setAttribute('class',        'btn-group');
		$radioContainer->setAttribute('data-toggle',  'buttons');
		$radioContainer->setAttribute('id' ,          $idChp);
		$radioContainer->setAttribute('type',         'group-radio');

		if (empty($v['values'])) {
            die ("Le champ radio '" . $k . "' n'a pas de tableau de données");
		} else {

            $size = array();
            switch ( count($v['values']) )
            {
                case 2 :
                    $size[0] = 'calc(50% + 1px)';
                    $size[1] = '50%';
                    break;
                case 3 :
                    $size[0] = 'calc(33% + 1px)';
                    $size[1] = 'calc(33% + 1px)';
                    $size[2] = '34%';
                    break;
                case 4 :
                    $size[0] = 'calc(25% + 1px)';
                    $size[1] = 'calc(25% + 1px)';
                    $size[2] = 'calc(25% + 1px)';
                    $size[3] = '25%';
                    break;
            }

            $i=0;
            foreach ($v['values'] as $key => $val) {

				$label = $this->_dom->createElement('label');
				$label->setAttribute('class', 'btn btn-radio pull-left');
				$label->setAttribute('style', 'width:' . $size[$i] . '; padding-top:5px; padding-bottom:5px;');

				$input = $this->_dom->createElement('input');
				$input->setAttribute('type', 'radio');
				$input->setAttribute('name',  $k);
				$input->setAttribute('id',    $k . '_id_' . $i);
				$input->setAttribute('value', $key);

                // Si un bouton radio est déjà activé
                if (isset($_SESSION['form'][$k]) && $_SESSION['form'][$k] == $key) {
                    $label->setAttribute('class', 'btn btn-radio active');
                    $input->setAttribute('checked', true);
                }

                $spanTxt = $this->_dom->createElement('span');

                if (strstr($val, '<')) {
                    $spanTxt->appendChild( $this->addHtml($val) );
                } else {
                    $labelText = $this->_dom->createTextNode($val);
                    $spanTxt->appendChild($labelText);
                }

				$label->appendChild($input);
				$label->appendChild($spanTxt);
				$radioContainer->appendChild($label);

				$i++;
			}
		}

		$div->appendChild($radioContainer);
        $container->appendChild($div);

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
	 * Affichage des champs 'select'
	 */
	private function chpSelect($k, $v)
    {
        // id champ
        $idChp = $k . '_id';

        // Conteneur
        $container = $this->_dom->createElement('div');
        $container->setAttribute('class', 'form-group');

        // Bloc label
        $blocLabel = $this->_dom->createElement('label');
        $blocLabel->setAttribute('for', $idChp);
        $blocLabel->setAttribute('class', $this->_classLabel . ' control-label');

        if (! empty($v['label'])) {
            $blocLabel->appendChild( $this->addHtml($v['label']) );
        }

        $container->appendChild($blocLabel);

        // Bloc champ
        $div = $this->_dom->createElement('div');
        $div->setAttribute('class', $this->_classInput);

        $select = $this->_dom->createElement('select');
        $select->setAttribute('name', $k);
        $select->setAttribute('id', $idChp);

        // Bootstrap - plugin selectpicker
        //$select->setAttribute('class',		'selectpicker form-control');
        //$select->setAttribute('data-width',	'100%');

        // Select bootstrap classique
        $select->setAttribute('class', 'form-control');

        if (empty($v['values'])) {
            die ("Le champ select '" . $k . "' n'a pas de tableau de données");
        } else {

            $option = $this->_dom->createElement('option');
            $option->setAttribute('value', '');

            $optionText = $this->_dom->createTextNode('--');

            $option->appendChild($optionText);
            $select->appendChild($option);

            foreach ($v['values'] as $key => $val) {

                $option = $this->_dom->createElement('option');
                $option->setAttribute('value', $key);

                // Si un élément est déjà sélectionné
                if (isset($_SESSION['form'][$k]) && $_SESSION['form'][$k] == $key) {
                    $option->setAttribute('selected', true);
                }

                $optionText = $this->_dom->createTextNode($val);

                $option->appendChild($optionText);
                $select->appendChild($option);
            }
        }

        if (! empty($v['icon'])) {
            $divGroup =  $this->_dom->createElement('div');
            $divGroup->setAttribute('class', 'input-group');

            $divIcon = $this->_dom->createElement('div');
            $divIcon->setAttribute('class', 'input-group-addon w40');

            $icon = $this->_dom->createElement('i');
            $icon->setAttribute('class', $v['icon']);

            $select->setAttribute('style', 'border-top-left-radius:0; border-bottom-left-radius:0; border-left:none;');

            $divIcon->appendChild($icon);
            $divGroup->appendChild($divIcon);
            $divGroup->appendChild($select);
            $div->appendChild($divGroup);
        } else {
            $div->appendChild($select);
        }

        $container->appendChild($div);

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
	 * Affichage des champs input de type 'checkbox'
	 */
	private function chpInputCheckboxClassic($k, $v)
    {
        // id champ
        $idChp = $k . '_id';

        // Conteneur
        $container = $this->_dom->createElement('div');
        $container->setAttribute('class', 'form-group');

        // Bloc label
        $blocLabel = $this->_dom->createElement('label');
        $blocLabel->setAttribute('for', $idChp);
        $blocLabel->setAttribute('class', $this->_classLabel . ' control-label');

        if (! empty($v['label'])) {
            $blocLabel->appendChild( $this->addHtml($v['label']) );
        }

        $container->appendChild($blocLabel);

        // Bloc champ
        $div = $this->_dom->createElement('div');
		$div->setAttribute('class', $this->_classInput);
		$div->setAttribute('style', 'position:relative; top:6px;');

		if (empty($v['values'])) {
            die ("Le champ radio '" . $k . "' n'a pas de tableau de données");
		} else {

            $i=0;
            foreach ($v['values'] as $key => $val) {

                $divInput = $this->_dom->createElement('div');
                $divInput->setAttribute('style', 'position:relative; left:-6px; line-height:30px;');

				$input = $this->_dom->createElement('input');
				$input->setAttribute('type', 'checkbox');
				$input->setAttribute('name', $k);
				$input->setAttribute('id', $k . '_id_' . $i);
				$input->setAttribute('value', $key);
                $input->setAttribute('style', 'width:30px; position:relative; top:2px;');

                // Si une ou plusieurs des checkbox sont déjà activés
                if (isset($_SESSION['form'][$k]) && is_array($_SESSION['form'][$k])) {
                    foreach ($_SESSION['form'][$k] as $value) {
                        if ($value == $key) {
                            $input->setAttribute('checked', true);
                        }
                    }
                }

                $label = $this->_dom->createElement('label');

                $labelText = $this->_dom->createTextNode($val);
                $label->appendChild($labelText);

				$divInput->appendChild($input);
                $divInput->appendChild($label);
                $div->appendChild($divInput);

				$i++;
			}
		}

        $container->appendChild($div);

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
    * Affichage des champs input de type 'radio' en mode bouton
    */
    private function chpInputCheckbox($k, $v)
    {
        // id champ
        $idChp = $k . '_id';

        // Conteneur
        $container = $this->_dom->createElement('div');
        $container->setAttribute('class', 'form-group');

        // Bloc label
        $blocLabel = $this->_dom->createElement('label');
        $blocLabel->setAttribute('for', $idChp);
        $blocLabel->setAttribute('class', $this->_classLabel . ' control-label');

        if (! empty($v['label'])) {
            $blocLabel->appendChild( $this->addHtml($v['label']) );
        }

        $container->appendChild($blocLabel);

        // Bloc champ
        $div = $this->_dom->createElement('div');
		$div->setAttribute('class', $this->_classInput);

        $divGroup = $this->_dom->createElement('div');
        $divGroup->setAttribute('class', 'btn-group-vertical');

        if (empty($v['values'])) {
            die ("Le champ radio '" . $k . "' n'a pas de tableau de données");
		} else {

            $i=0;
            foreach ($v['values'] as $key => $val) {

                $label = $this->_dom->createElement('label');
                $label->setAttribute('class', 'btn btn-checkbox-button');

                $input = $this->_dom->createElement('input');
                $input->setAttribute('type', 'checkbox');
                $input->setAttribute('name', $k);
                $input->setAttribute('id', $k . '_id_' . $i);
                $input->setAttribute('value', $key);

                $divContent = $this->_dom->createElement('div');
                $divContent->setAttribute('align', 'left');

                $divContentTxt = $this->_dom->createTextNode($val);

                $icon =  $this->_dom->createElement('i');
                $icon->setAttribute('class', 'mdi mdi-checkbox-blank-outline pull-left');

                // Si une ou plusieurs des checkbox sont déjà activés
                if (isset($_SESSION['form'][$k]) && is_array($_SESSION['form'][$k])) {
                    foreach ($_SESSION['form'][$k] as $value) {
                        if ($value == $key) {
                            $input->setAttribute('checked', true);
                            $icon->setAttribute('class', 'mdi mdi-checkbox-marked-outline pull-left');
                        }
                    }
                }

                $divContent->appendChild($icon);
                $divContent->appendChild($divContentTxt);
                $label->appendChild($input);
                $label->appendChild($divContent);

                $divGroup->appendChild($label);
            }

            $div->appendChild($divGroup);
        }

        $container->appendChild($div);

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
     * Affichage d'une ProgressBar simple ou multiple
     */
    private function progressBar()
    {
        $progressBarConf = $this->_form['steps'][$this->_step]['progressBar'];

        if (is_array($progressBarConf)) {

            $container = $this->_dom->createElement('div');

            $progress = $this->_dom->createElement('div');
            $progress->setAttribute('class', 'progress');

            $countBars = count($progressBarConf);

            $i=1;
            foreach($progressBarConf as $k => $v) {

                if ($k == 'label' && ! empty($progressBarConf['label'])) {

                    $label = $this->_dom->createElement('div');
                    $label->setAttribute('class', 'progressbar-label');

                    $progressBarTlabel = $progressBarConf['label'];
                    if (!empty($this->_form['name'])) {
                        $progressBarTlabel = '<div style="margin-bottom:5px;"><span class="lead-name">' . $this->_form['name'] . ' :</span> <i>' . $progressBarTlabel . '</i></div>';
                    }

                    $label->appendChild( $this->addHtml($progressBarTlabel) );
                    $container->appendChild($label);

                } else {

                    if (! empty($v['percent']) && ! empty($v['class'])) {

                        $progressBar = $this->_dom->createElement('div');
                        $progressBar->setAttribute('class', 'progress-bar ' . $v['class']);
                        $progressBar->setAttribute('style', 'width:' . $v['percent'] . '%');

                        if ($i == $countBars && isset($this->_form['steps'][$this->_step]['progressBar']['activPct']) && $this->_form['steps'][$this->_step]['progressBar']['activPct']===true) {
                            $progressBarTxt = $this->_dom->createTextNode($v['percent'] . '%');
                            $progressBar->appendChild($progressBarTxt);
                        }

                        $progress->appendChild($progressBar);
                    }

                    $container->appendChild($progress);
                }

                $i++;
            }

            // Ajout de l'élement
            $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
        }
    }


    /**
     * Texte de fin de formulaire
     */
    private function txtEndForm()
    {
        $txtEndForm = $this->_form['steps'][$this->_step]['txtEndForm'];

        // Conteneur
        $container = $this->_dom->createElement('div');
        $container->appendChild( $this->addHtml($txtEndForm) );

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
     * Ajout de code dans le formulaire
     */
    private function formAddHTML()
    {
        $addHTML = $this->_form['steps'][$this->_step]['addHTML'];

        // Conteneur
        $container = $this->_dom->createElement('div');
        $container->appendChild( $this->addHtml($addHTML) );

        // Ajout de l'élement
        $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
    }


    /**
     * Création d'un bouton spécifique avec le libille et lien libre
     */
    private function btnSpe()
    {
        $btnSpe = $this->_form['steps'][$this->_step]['btnSpe'];


        if (! empty($btnSpe['label']) && ! empty($btnSpe['url'])) {

            $container = $this->_dom->createElement('div');
            $container->setAttribute('class', 'col-lg-12');
            $container->setAttribute('align', 'center');
            $container->setAttribute('style', 'margin-top:30px;');

            $btn = $this->_dom->createElement('button');
            $btn->setAttribute('type',    'button');
            $btn->setAttribute('style',   'min-width:210px;');
            $btn->setAttribute('class',   'btn btn-form-next');
            $btn->setAttribute('onclick', 'document.location.href="' . $btnSpe['url'] . '"');

            $btn->appendChild( $this->addHtml( $btnSpe['label']) );
            $container->appendChild($btn);

            // Ajout de l'élement
            $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
        }
    }

    /**
     *  Création des boutons de control 'Précédent' et 'Suivant'
     */
    private function formControl()
    {
        if ((! empty($this->_form['steps'][$this->_step]['btnTxt']['prev'])) || (! empty($this->_form['steps'][$this->_step]['btnTxt']['next']))) {

            $container = $this->_dom->createElement('div');
            $container->setAttribute('class', 'form-group');
            $container->setAttribute('style', 'margin-top:30px;');

            // Espace du label à vide
            $label = $this->_dom->createElement('label');
            $label->setAttribute('class', $this->_classLabel);

            $container->appendChild($label);

            // Bloc contenant les boutons
            $div = $this->_dom->createElement('div');
            $div->setAttribute('class', $this->_classInput);
            $div->setAttribute('align', 'center');

            // Bouton : précédent
            if (! empty($this->_form['steps'][$this->_step]['btnTxt']['prev'])) {

                $prev = $this->_dom->createElement('button');
                $prev->setAttribute('type', 'button');
                $prev->setAttribute('class','btn btn-form-prev');
                $prev->setAttribute('id',   'btn-prev');
                $prev->setAttribute('cta',  $this->_prevCta);
                $prev->setAttribute('step', $this->_prevStep);

                if (empty($this->_form['steps'][$this->_step]['btnTxt']['next'])) {
                    $prev->setAttribute('style', 'min-width:210px;');
                }

                // $prevTxt = $this->_dom->createTextNode( $this->_form['steps'][$this->_step]['btnTxt']['prev'] );
                // $prev->appendChild($prevTxt);

                $prev->appendChild( $this->addHtml($this->_form['steps'][$this->_step]['btnTxt']['prev']) );
                $div->appendChild($prev);
            }

            // Bouton : suivant
            if (! empty($this->_form['steps'][$this->_step]['btnTxt']['next'])) {

                $next = $this->_dom->createElement('button');
                $next->setAttribute('type',  'button');
                $next->setAttribute('class', 'btn btn-form-next');
                $next->setAttribute('id',    'btn-next');

                if (! empty($this->_form['steps'][$this->_step]['btnTxt']['prev'])) {
                    $next->setAttribute('style', 'margin-left:10px;');
                } else {
                    $next->setAttribute('style', 'min-width:210px;');
                }

                // $nextTxt = $this->_dom->createTextNode( $this->_form['steps'][$this->_step]['btnTxt']['next'] );
                // $next->appendChild($nextTxt);

                $next->appendChild( $this->addHtml($this->_form['steps'][$this->_step]['btnTxt']['next']) );
                $div->appendChild($next);
            }

            $container->appendChild($div);

            // Ajout de l'élement
            $this->_dom->getElementsByTagName('form')->item(0)->appendChild($container);
        }
    }


    /**
     * Permet de merger du code HTML dans le DOM
     *
     * @param       string      $html           Code à insérer
     * @param       string      $colWidth       Taille du container
     * @return      object
     */
    private function addHtml($html, $colWidth='col-lg-12')
    {
    	$container = $this->_dom->createElement('div');
    	$container->setAttribute('class', $colWidth);

    	$divRow = $this->_dom->createElement('div');
    	$divRow->setAttribute('class', 'row');

    	// Récupération du code à insérer
    	$newDom = new \DOMDocument("1.0", "utf-8");
    	$newDom->loadHTML('<?xml encoding="UTF-8">' . $html);

    	$xpath = new \DOMXPath($newDom);
    	$query		= '//body';
		$entries	= $xpath->query($query);
    	$body		= $entries->item(0);

		if ($entries->length > 0) {
	    	foreach ($body->childNodes as $child) {
	    		$newNode = $this->_dom->importNode($child, true);
	    		$divRow->appendChild($newNode);
	    	}
		}

    	$container->appendChild($divRow);

        return $container;
    }
}
