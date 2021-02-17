<?php
namespace tools;

/**
 * Récupération des listes de foemulaire
 *
 * @author Daniel Gomes
 */
class listForm
{
	/**
	 * Récupération du libelle
	 *
	 * @param 	integer 	$id_champ
	 * @param 	string		$bdd			N'est pas nécessaire s'il s'agit de la configuration PDO par défaut
	 *
	 * @return	string
	 */
	public static function infosChamp($id_champ=null, $bdd='')
	{
		$result = array();

		if (! is_null($id_champ) && is_numeric($id_champ)) {

			$dbh = \tools\dbSingleton::getInstance($bdd);

			$req = "SELECT libelle, obligatoire FROM lead_variables_champs WHERE id = :id";
			$sql = $dbh->prepare($req);
			$sql->execute( array(':id'=>$id_champ));

			if ($sql->rowCount() > 0) {
				$res = $sql->fetch();

				$result['libelle'] 		= $res->libelle;
				$result['obligatoire'] 	= $res->obligatoire;
			}
		}

		return $result;
	}


	/**
	 * Récupération d'un dataSet de formulaire
	 *
	 * @param 	integer 	$id_champ
	 * @param 	string		$bdd			N'est pas nécessaire s'il s'agit de la configuration PDO par défaut
	 *
	 * @return	array
	 */
	public static function devisProxListForm($id_champ=null, $bdd='')
	{
		$result 	 			= array();
		$result['keySelected']	= null;

		if (! is_null($id_champ) && is_numeric($id_champ)) {

			$dbh = \tools\dbSingleton::getInstance($bdd);

			// On vérifie s'il s'agit d'un tableau assiciatif ou non
			$req = "SELECT tableau_associatif FROM lead_variables_champs WHERE id = :id";
			$sql = $dbh->prepare($req);
			$sql->execute( array(':id'=>$id_champ));

			if ($sql->rowCount() > 0) {

				$res = $sql->fetch();
				$tableau_associatif = $res->tableau_associatif;

				$req = "SELECT * FROM lead_variables WHERE id_champ = :id_champ";
				$sql = $dbh->prepare($req);
				$sql->execute( array(':id_champ'=>$id_champ));

				$i=1;
				while ($res = $sql->fetch()) {

					// Tableau non associatif - commence par la clé 1
					if ($tableau_associatif == 0) {

						if ($res->selected == 1) {
							$result['keySelected'] = $i;
						}

						$result['values'][$i] = $res->valeur;

					// Tableau associatif
					} else {

						if ($res->selected == 1) {
							$result['keySelected'] = $res->cle;
						}

						$result['values'][$res->cle] = $res->valeur;
					}

					$i++;
				}
			}
		}

		return $result;
	}
}
