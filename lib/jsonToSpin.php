<?php
namespace tools;

/**
 * Client JSON
 * Récupère les textes d'un projets
 *
 * @author Daniel Gomes
 */
class jsonToSpin
{
	/**
	 * Attributs
	 */
	private $_cache;
	private $_id_project;
	private $_url;
	private $_rang;
	private $_json = array();

	private $_crypt;						// Clé pour chiffrer le flux json


	/**
	 * Constructeur
	 *
	 * @param	boolean		$cache		Activation / Désactivation des cache JSON
	 */
	public function __construct($cache=true)
	{
		# Récupération des urls à appeler pour les webservices Json
		$config = config::getConfig('jsonUrls');
		$this->_url = $config['url_spin'];

		# Récupération du grain de sel du projet dans le fichier de configuration "crypt.php"
		$crypt = config::getConfig('crypt');
		$this->_crypt = new crypt( $crypt['grain_sel'] );

		# Récupération de l'adresse IP
		$this->_json['ip'] = $this->_crypt->encrypt( $_SERVER['REMOTE_ADDR'] );

		# Activation par défaut de la gestion des caches
		$this->_cache = $cache;
	}


	/**
	 * Setters
	 */
	public function setIdProjet($id_project) {
		$this->_id_project = $id_project;
		$this->_json['id_project'] = $id_project;
	}

	public function setRang($rang) {
		$this->_rang = $rang;
		$this->_json['rang'] = $this->_crypt->encrypt($rang);
	}


	/**
	 * Getters
	 */
	public function getUrl() {
		return $this->_url;
	}

	public function getJson() {
		return $this->_json;
	}

	public function getRang() {
		return $this->_rang;
	}


	/**
	 * Cette méthode charge toutes les éléments de texte à récupérer pour ne faire qu'un appel REST contenant la requête en JSON
	 *
	 * @param string 	$id			Identifiant de la balise HTML
	 * @param integer 	$id_bdd		id    (BDD)
	 * @param string 	$tbl		Table (BDD)
	 * @param string 	$chp		Champ (BDD)
	 */
	public function addReq($id, $id_bdd, $tbl, $chp)
	{
		$this->_json['textes'][$this->_crypt->encrypt($id)] = array(
											'id_bdd'	=> $this->_crypt->encrypt($id_bdd),
											'tbl' 		=> $this->_crypt->encrypt($tbl),
											'chp' 		=> $this->_crypt->encrypt($chp),
											);
	}


	/**
	 * On effectue l'appel REST en postant en POST la requête JSON
	 * Cette méthode retourne un flux JSON contenant les tirages effectués à partir des SPIN appelés
	 */
	public function reqGetJson()
	{
		// On commence par vérifier si notre flux JSON n'est pas en cache
		$url = str_replace("/","#", $_SERVER['REQUEST_URI']);
		$cacheFile = "S#" . $this->_id_project . "#" . $this->_rang . "#" . date("Ymd") . "#" . $url;

		// Récupération du dossier de cache
		if ($this->_cache===true) {
			$cache = config::getConfig('cacheJson');
		}

		if ($this->_cache===true && file_exists($cache['spin'] . $cacheFile)) {

			$res = file($cache['spin'] . $cacheFile);
			$res = $res[0];

		} else {

			$json = json_encode($this->_json);

			$curl = curl_init();

			curl_setopt($curl, CURLOPT_URL, 			$this->_url);
			curl_setopt($curl, CURLOPT_COOKIESESSION, 	true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 	true);
			curl_setopt($curl, CURLOPT_POST, 			true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, 		array('json' => $json));
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 	false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 	0);

			$res = curl_exec($curl);
			curl_close($curl);

			if ($this->_cache===true) {

				# On stock le résultat en cache
				$fp = fopen($cache['spin'] . $cacheFile, "w+");
				fputs($fp, $res);
				fclose($fp);

				# On efface les caches de footer ayant une date antérieure
				$listSpin = glob($cache['spin'] . "S#*");

				foreach ($listSpin as $spin) {
					$exp = explode('#', $spin);
					if ($exp[1] == $this->_id_project && $exp[3] != date("Ymd")) {
						unlink($spin);
					}
				}
			}
		}

		return $res;
	}
}
