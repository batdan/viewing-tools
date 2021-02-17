<?php
namespace tools;

/**
 * Affichage de planing mensuels pour un silo de lien
 *
 * @author Daniel Gomes
 */
class planing
{
	/**
	 * Permet de récupérer la liste des années (hors date du jour)
	 * afin de créer un silo de liens avec les anciens évènements
	 *
	 * @param 	string 		$table
	 * @return 	array
	 */
	public static function list_year($table, $chpDate='date_crea')
	{
		$listYear = array();

		// Instance PDO
		$dbh = \tools\dbSingleton::getInstance();

		$req = "SELECT 		DISTINCT( DATE_FORMAT($chpDate, '%Y') ) as recup_year
				FROM 		$table
				WHERE 		$chpDate <> CURDATE()
				ORDER BY 	recup_year ASC";
		$sql = $dbh->query($req);

		if ($sql->rowCount() > 0) {
			while ( $res = $sql->fetch() ) {
				$listYear[] = $res->recup_year;
			}
		}

		return $listYear;
	}


	/**
	 * Permet de récupérer la liste des mois d'une année années (hors date du jour)
	 * afin de créer un silo de liens avec les anciens évènements
	 *
	 * @param 	string 		$table
	 * @param 	integer		$year
	 * @return 	array
	 */
	public static function list_month($table, $year, $chpDate='date_crea')
	{
		$listMonth = array();

		// Instance PDO
		$dbh = \tools\dbSingleton::getInstance();

		$req = "SELECT 		DISTINCT( DATE_FORMAT($chpDate, '%m') ) as recup_month
				FROM 		$table
				WHERE 		$chpDate <> CURDATE()
				AND 		$chpDate LIKE :year
				ORDER BY 	recup_month ASC";
		$sql = $dbh->prepare($req);
		$sql->execute( array( ':year' => $year . '%' ));

		if ($sql->rowCount() > 0) {
			while ( $res = $sql->fetch() ) {
				$listMonth[] = $res->recup_month;
			}
		}

		return $listMonth;
	}


	/**
	 * Retourne le tableau d'un mois
	 * Les jours contenant des données deviendront cliquables
	 *
	 * @param 	string 		$table			// Nom de la table en BDD
	 * @param 	integer		$month			// Mois à afficher
	 * @param 	integer		$year			// Année à afficher
	 * @param 	string		$link			// Format du lien (la variable sera remplacée par la méthode)
	 * @param 	boolean		$linkAlpha		// Activation ou non du camouflage de la date par une conversion en alpha
	 * @param 	string 		$lang			// Choix de la langue à récupérer
	 * @return 	string
	 */
	public static function planing_mois($table, $year, $month, $link="/monlien/___VAR_DATE___/etc", $linkAlpha=true, $lang='FR_FR', $chpDate='date_crea')
	{
		// Instance PDO
		$dbh = \tools\dbSingleton::getInstance();

		// Récupération des dates contenant des données
		$req = "SELECT 		$chpDate
				FROM 		$table
				WHERE 		$chpDate LIKE :chpDate
				AND			$chpDate <> CURDATE()
				AND 		lang = :lang";

		$sql = $dbh->prepare($req);
		$sql->execute( array(
							':chpDate' => $year.'-'.$month.'%',
							':lang'    => $lang,
		));

		if ($sql->rowCount() > 0) {
			$dateOk = array();
			while ( $res = $sql->fetch() ) {
				$dateOk[] = $res->{$chpDate};
			}
		}

		$month_timestamp = mktime( 0, 0, 0, $month, 1, $year);

		$month_year	= ucfirst( strftime("%B %Y", $month_timestamp) );
		// echo $month_year . '<br>';

		$nb_days = date("t",$month_timestamp);
		// echo $nb_days.'<br>';

		for($i=1; $i<=$nb_days; $i++) {
			$day_timestamp = mktime( 0, 0, 0, $month, $i, $year);
			$day_lib[$i]	= substr( strftime("%A", $day_timestamp), 0, 2);
		}

		switch ($day_lib[1])
		{
			case 'lu' :	$nb_cases = $nb_days;		$diff_first_day=0;		break;
			case 'ma' :	$nb_cases = $nb_days+1;		$diff_first_day=1;		break;
			case 'me' :	$nb_cases = $nb_days+2;		$diff_first_day=2;		break;
			case 'je' :	$nb_cases = $nb_days+3;		$diff_first_day=3;		break;
			case 've' :	$nb_cases = $nb_days+4;		$diff_first_day=4;		break;
			case 'sa' :	$nb_cases = $nb_days+5;		$diff_first_day=5;		break;
			case 'di' :	$nb_cases = $nb_days+6;		$diff_first_day=6;		break;
			default   : $diff_first_day=0;
		}

		//$nb_lignes	= ceil( $nb_cases / 7 );
		$nb_lignes	= 6;

		$id			= array();
		$tableau	= array();
		$class		= array();

		$j = 1 - $diff_first_day;

	    for ($l=1; $l<=$nb_lignes; $l++)
		{
			for ($i=1; $i<=7; $i++)
			{
				$memDate			= $year . '-' . $month . '-' . str_pad($j, 2, "0", STR_PAD_LEFT);

				$id[$l][$i]			= $memDate;

				$class[$l][$i]		= '';
				$tableau[$l][$i]	= '&nbsp;';

				if ($j<=$nb_days && $j>0) {

					if ( in_array( $memDate, $dateOk )) {

						$class[$l][$i] = 'day-link';

						if ($linkAlpha) {
							$prepDate = intval ( str_replace ('-', '', $memDate) );
							$convDate = \tools\convAlphaNum::num2alpha($prepDate);

							$newLink = str_replace( '___VAR_DATE___', $convDate, $link);
						} else {
							$newLink = str_replace( '___VAR_DATE___', $memDate, $link);
						}

						$tableau[$l][$i]  = '<a href="' . $newLink . '">' . $j . '<a>';

					} else {
						$tableau[$l][$i]  = $j;
					}
				}

				if ( intval($j) == intval(date('d'))  &&  intval($month) == intval(date('m')) ) {
					$class[$l][$i] = 'today';
				}

				$j++;
			}
		}

		$html = <<<eof
<div class="col-lg-4">
  <div class="planing_month">
	<div class="planing_lib_mois">$month_year</div>

	<table>
		<thead>
			<tr>
				<td>lu</td>
				<td>ma</td>
				<td>me</td>
				<td>je</td>
				<td>ve</td>
				<td>sa</td>
				<td>di</td>
			</tr>
		</thead>

		<tbody>
eof;

        $i=1;

		// Semaines --------------------------------------------------------
        for ($l=1; $l<=$nb_lignes; $l++) {

			$html .= <<<eof
			<tr>
				<td id="{$id[$l][1]}" class="{$class[$l][1]}">{$tableau[$l][1]}</td>
				<td id="{$id[$l][2]}" class="{$class[$l][2]}">{$tableau[$l][2]}</td>
				<td id="{$id[$l][3]}" class="{$class[$l][3]}">{$tableau[$l][3]}</td>
				<td id="{$id[$l][4]}" class="{$class[$l][4]}">{$tableau[$l][4]}</td>
				<td id="{$id[$l][5]}" class="{$class[$l][5]}">{$tableau[$l][5]}</td>
				<td id="{$id[$l][6]}" class="{$class[$l][6]}">{$tableau[$l][6]}</td>
				<td id="{$id[$l][7]}" class="{$class[$l][7]}">{$tableau[$l][7]}</td>
			</tr>
eof;
		}
		// -----------------------------------------------------------------

        $html .= '</tbody>';
        $html .= '</table>';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}


	/**
	 * Retourne le tableau d'un mois
	 * Les jours contenant des données deviendront cliquables
	 *
	 * @param 	string		$bgHeader			// Mois : Couleur de fond et couleur de bord du planning
	 * @param 	string		$txtHeader			// Mois : Couleur texte
	 *
	 * @param 	string		$bgDaysLib			// Libellé des jours : Couleur de fond
	 * @param 	string		$txtDaysLib			// Libellé des jours : Couleur texte
	 *
	 * @param 	string		$bgToday			// Aujourd'hui : Couleur de fond
	 * @param 	string		$txtToday			// Aujourd'hui : Couleur texte
	 *
	 * @param 	string		$bgDaysoff			// Jours sans lien : Couleur de fond
	 * @param 	string		$txtDaysoff			// Jours sans lien : Couleur texte
	 *
	 * @param 	string		$bgDaysLink			// Jour avec lien : Couleur de fond
	 * @param 	string		$txtDaysLink		// Jour avec lien : Couleur texte
	 *
	 * @param 	string		$bgDaysLinkHover	// Jour avec lien au survol : Couleur de fond
	 * @param 	string		$txtDaysLinkHover	// Jour avec lien au survol : Couleur texte
	 *
	 */
	 public static function cssCrea($bgHeader='#881E82',		$txtHeader='#fff',
	 								$bgDaysLib='#f4f4f4',		$txtDaysLib='#666',
									$bgToday='#231657',			$txtToday='#fff',
									$bgDaysoff='#fff',			$txtDaysoff='#666',
									$bgDaysLink='#fff',			$txtDaysLink='#881E82',
									$bgDaysLinkHover='#881E82',	$txtDaysLinkHover='#fff')
	{
		$css = <<<eof
.planing_month {
	border: 1px solid $bgHeader;
    border-radius: 3px;
    text-align: center;
    padding: 0;
	margin: 0 0 20px 0;
	cursor: default;
}

.planing_month .planing_lib_mois {
    background-color: $bgHeader;
    color: $txtHeader;
    font-weight: bold;
    padding-top: 4px;
    padding-bottom: 4px;
}

.planing_month>table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 1px;
}

.planing_month>table>thead>tr {
    background-color: $bgDaysLib;
}

.planing_month>table>thead>tr>td {
    width: 14%;
	color: $txtDaysLib;
    font-weight: bold;
}

.planing_month>table>tbody>tr>td {
	background-color: $bgDaysoff;
	color: $txtDaysoff;
}

.planing_month .day-link {
	background-color: $bgDaysLink;
	cursor: pointer;
	transition: all 300ms;
}

.planing_month .today {
	font-weight: bold;
	background-color: $bgToday;
	color: $txtToday;
}

.planing_month .day-link>a {
	display: block;
	font-weight: bold;
	transition: all 400ms;
}

.planing_month .day-link>a:link     { color:$txtDaysLink;			background-color: $bgDaysLink;	}
.planing_month .day-link>a:visited  { color:$txtDaysLink;			background-color: $bgDaysLink; 	}
.planing_month .day-link>a:hover    { color:$txtDaysLinkHover;		background-color: $bgDaysLinkHover; }
.planing_month .day-link>a:active   { color:$txtDaysLinkHover;		background-color: $bgDaysLinkHover; }
eof;

		// Minification du CSS
		if (class_exists('\Minify_CSSmin')) {
			$cssMinify = new \Minify_CSSmin();
			$css  = '/* planing.css */' . chr(10) . $cssMinify->minify($css);
		}

		\tools\libIncluder::add_CssScript($css);
	}
}
