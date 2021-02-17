<?php
namespace tools;

/**
 * Outils divers
 *
 * @author Daniel Gomes
 */
class tools
{
    /**
     * Conversion string en élément de menu
     *
     * @param       string      $texte      chaîne à transformer
     * @return      string
     */
    public static function filter_url_key($texte)
	{
		$texte = strip_tags($texte);

		$texte = trim($texte);
		$texte = trim($texte, '-');

		// suppression des accents, tréma et cédilles + qlq autres car. spéciaux
		$aremplacer 	= 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿ&#340;&#341;';
    	$enremplacement = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyrr';
		$texte = utf8_decode($texte);
		$texte = strtr($texte, utf8_decode($aremplacer), $enremplacement);
		$texte = strtolower($texte);

		// suppression des espaces et car. non-alphanumériques
		$texte = str_replace(" ",'-',$texte);
        $texte = preg_replace('/([^a-z0-9-_\/])/','-',$texte);

		// suppression des tirets multiples
		$texte = preg_replace('#([-]+)#','-',$texte);

		return trim($texte, '-');
	}


    /**
     * Compte le nombre de décimales d'un nombre
     */
    public static function countDecimal($num)
    {
        $num = floatval($num);
        for ($i=0; $num!=round($num, $i); $i++);

        return $i;
    }


    /**
     * Permet de vérifier si une chaine est un JSON valide
     *
     * @return boolean
     */
    public static function isValidJson($string) {

        json_decode($string);

        if (json_last_error() == JSON_ERROR_NONE) {

            if( $string[0] == "{" || $string[0] == "[" ) {

                $first = $string [0];

                if( substr($string, -1) == "}" || substr($string, -1) == "]" ) {

                    $last = substr($string, -1);

                    if($first == "{" && $last == "}"){
                        return true;
                    }

                    if($first == "[" && $last == "]"){
                        return true;
                    }

                    return false;

                }
                return false;
            }

            return false;
        }

        return false;
    }
}
