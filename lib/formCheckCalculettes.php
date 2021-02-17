<?php
namespace tools;

/**
 * Gestion des appels du Webservice "calculettes"
 *
 * @author Daniel Gomes
 */
class formCheckCalculettes extends formCheckSave
{
    /**
     * Attributs
     */
    private $_config;               // Configuration du site
    private $_calculettesWS;        // Dernière étape du formulaire


    /**
     * Constructeur
     *
     * @param       string      $cta        Formulaire sélectionné
     * @param       string      $step       Etape du formulaire
     * @param       string      $chp        Champs envoyés
     */
    public function __construct($cta, $step, $chp)
    {
        // Récupération de la configuration du site
        $this->_config = config::getConfig('config');

        parent::__construct($cta, $step, $chp, 'callCalculettes');

        // Url du webservice "calculettes"
        $this->_calculettesWS = config::getConfig('calculettesWS');
    }


    /**
     * Interrogation du webservice "calculettes"
     */
    protected function callCalculettes()
    {
        $result         = '';
        $error          = array();

        // Calcul du lien du webservice de la calulatrice
        if (isset($this->_chp['url'])) {

            $uriWS = $this->_chp['url'];

            // Remplacement des noms des champs par leurs valeurs
            foreach( $this->_chp as $k=>$v ) {
                if ($k != 'url') {
                    $uriWS = str_replace('$'.$k, $v, $uriWS);
                }
            }

            $urlWS = $this->_calculettesWS['url'] . $uriWS;

            // Appel du webservice
            $ws = curl_init();

            curl_setopt($ws, CURLOPT_URL, $urlWS);
            curl_setopt($ws, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ws, CURLOPT_HEADER, false);

            $resWS = curl_exec($ws);

            if ($resWS === false) {

                $error[] = 'Erreur curl : ' . curl_error($ws);

            } else {

            	$result = trim($resWS);
            }

            curl_close($ws);

        } else {

            $error[] = 'erreur url !';
        }


        // Retour
        return array(
            /* Debug */
            //'parcours'        => $_SESSION['form']['parcours'],
            //'cta'             => $this->_cta,
            //'urlWS'           => $urlWS,

            /* Important */
            'erreursCount'      => count($error),
            'erreursInfos'      => $error,
            'result'            => $result
        );
    }
}
