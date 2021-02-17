<?php
namespace tools;

use tools\config;
use tools\tools;

/**
 * Compatibilité du formulaire
 * Array ou Json acceptés
 *
 * @author Daniel Gomes
 */
class formFormat
{
    /**
     * Compatibilité du formulaire
     * Array ou Json
     *
     * @return array
     */
    public static function checkFormatForm($cta, $path='confForms')
    {
        if (substr($path,-1) != '/') {
            $path .= '/';
        }

        // Récupération des informations du formulaire
        $form = config::getConfig($path . $cta);


        // La configuration du formulaire est un tableau
        if (is_array($form)) {
            return $form;

        // La configuration du formulaire est un JSON
        } elseif (tools::isValidJson($form) === true) {
            return json_decode($form, true);

        // Format non reconnu
        } else {
            error_log('Bad configuration form : only Array or Json file !');
        }
    }
}
