<?php
namespace tools;

/**
 * Sélecteur de CTA
 * Permet de présélectionner un CTA
 *
 * @author Daniel Gomes
 */
class formCheckSelector extends formCheckSave
{
    /**
     * Attributs
     */
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
        parent::__construct($cta, $step, $chp, 'selector');

        // Dernière étape du formulaire
        $this->_stepEndForm = $this->_form['stepEndForm'];
    }


    /**
     * Sauvegarde du formulaire dans la variable de session et en base de données
     */
    protected function selector()
    {
        // Déclaration des variables -------------------------------------------
        $redir          = 'false';

        // Redirection vers un autre form --------------------------------------
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

                // Enregistrement du CTA
                $_SESSION['form']['cta']  = $switchCta;

                // On se recale sur la bonne étape
                $_SESSION['form']['step'] = 1;

                // Activation de la redirection
                $redir = 'true';
            }
        }


        // Retour --------------------------------------------------------------
        return array(
            /* Debug */
            //'parcours'      => $_SESSION['form']['parcours'],
            //'req'           => $req,
            //'cta'           => $this->_cta,
            //'stepEndForm'   => $this->_stepEndForm,
            //'step'          => $this->_step,

            /* Important */
            'action'            => 'selector',
            'redir'             => $redir,
        );
    }
}
