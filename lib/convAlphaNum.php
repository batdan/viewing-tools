<?php
namespace tools;

/**
 * Chiffrement de l'url en 6 à 7 chiffres
 * Opération inverse
 *
 * @author Daniel Gomes
 */
class convAlphaNum
{
	/**
	 * On encode la table, l'id et le rang sur 6 à 7 lettres (alpha)
	 *
	 * @param 	string		$table
	 * @param 	integer 	$id
	 * @param 	integer 	$rang
	 * @return	string
	 */
	public static function encodeAlpha($table, $id, $rang)
	{
		/**
		 * Le nom de la table contenant les textes est codé sur le premier caractère
		 */
		switch($table)
		{
			case 'projects_actualites'	: 	$codeTable = 1;		break;
			case 'projects_dossiers' 	: 	$codeTable = 2;		break;
			case 'projects_pages' 		: 	$codeTable = 3;
		}

		/**
		 * L'id est chiffré sur les 4 caractères suivants (2 à 5)
		 * On lui ajout de rang pour brouiller les pistes
		 */
		$codeId 	= $id + $rang;
		$codeId 	= str_pad($codeId, 4, '0', STR_PAD_LEFT);

		/**
		 * Le rang est codé sur les 4 caractères suivants (6 à 9)
		 * On le multiplie par 3 pour brouiller les pistes
		 */
		$codeRang	= $rang * 3;
		$codeRang	= str_pad($codeRang, 4, '0', STR_PAD_LEFT);

		/**
		 * Ce qui nous donne un code sur 9 caractères
		 */
		$codeSpin 	= intval($codeTable . $codeId . $codeRang);

		/**
		 * Ce code est ensuite converti en alpha et comprendra entre 6 et 7 lettres
		 */
		$codeSpinAlpha = self::num2alpha($codeSpin);

		return $codeSpinAlpha;
	}


	/**
	 * On décode le code alapha pour récupérer la table, l'id et le rang
	 *
	 * @param 	string		$alpha
	 * @return	array
	 */
	public static function decodeAlpha($alpha)
	{
		$res = array( 'check'=>true );

		// On vérifie l'intégrité du code alpha
		if (! self::checkAlpha($alpha)) {
			return array( 'check'=>false );
		}

		/**
		 * Conversion du code alpha en numérique
		 */
		$codeSpin = strval(self::alpha2num($alpha));

		// On vérifie l'intégrité du code alpha une fois converti en N°
		if (! self::checkCodeSpin($codeSpin)) {
			return array( 'check'=>false );
		}

		/**
		 * Du premier caractère, on récupère le nom de la table
		 */
		$codeTable 	= substr($codeSpin, 0, 1);
		switch($codeTable)
		{
			case '1' : 	$res['table'] = 'projects_actualites';		break;
			case '2' : 	$res['table'] = 'projects_dossiers';		break;
			case '3' : 	$res['table'] = 'projects_pages';
		}

		/**
		 * Des caractères 6 à 9, on récupère le rang
		 */
		$codeRang = intval(substr($codeSpin, 5, 4)) / 3;
		$res['rang']= $codeRang;

		/**
		 * Des caractères 2 à 5, on récupère l'id dans la table
		 */
		$codeId = intval(substr($codeSpin, 1, 4)) - $codeRang;
		$res['id']	= $codeId;

		return $res;
	}


	/**
	 * Conversion numérique vers alpha
	 *
	 * @param	integer		$n		Nombre à convertir
	 * @return	string
	 */
	public static function num2alpha($n)
	{
		$r = '';

		for ($i = 1; $n >= 0 && $i < 10; $i++) {
			$r = chr(0x61 + ($n % pow(26, $i) / pow(26, $i - 1))) . $r;
			$n -= pow(26, $i);
		}

		return $r;
	}


	/**
	 * Conversion alpha vers numérique
	 *
	 * @param	string		$n		Code alpha à convertir
	 * @return	integer
	 */
	public static function alpha2num($a)
	{
	    $r = 0;
	    $l = strlen($a);

	    for ($i = 0; $i < $l; $i++) {
			$r += pow(26, $i) * (ord($a[$l - $i - 1]) - 0x60);
		}

		return $r - 1;
	}


	/**
	 * On vérifie l'intégrité du code alpha
	 *
	 * @param	string		$alpha		Code alpha à vérifier
	 * @return	boolean
	 */
	private static function checkAlpha($alpha)
	{
		// La chaine doit être uniquement alphabétique
		if (! ctype_alpha($alpha)) {
			return false;
		}

		// La chaine doit contenir entre 6 et 7 lettres
		if (strlen($alpha) < 6 || strlen($alpha) > 7) {
			return false;
		}

		return true;
	}


	/**
	 * On vérifie l'intégrité du code alpha une fois converti en N°
	 *
	 * @param	string		$codeSpin	Vérification du nombre issu du code alpha
	 * @return	boolean
	 */
	private static function checkCodeSpin($codeSpin)
	{
		$codeTable 	= substr($codeSpin, 0, 1);

		// Le premier caractère (table bdd) doit être compris entre 1 et 3
		if ($codeTable < 1 || $codeTable > 3) {
			return false;
		}

		// Les 4 caractères du rang doivent être un divisible de 3
		$codeRang = intval(substr($codeSpin, 5, 4)) / 3;
		if (! is_int($codeRang)) {
			return false;
		}

		// Intégrité de l'id bdd
		$codeId = intval(substr($codeSpin, 1, 4)) - $codeRang;
		if ($codeId <= 0) {
			return false;
		}

		return true;
	}
}
