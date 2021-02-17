<?php
namespace tools;

/**
 * Client JSON
 * Récupère les liens internes d'un projets
 *
 * @author Daniel Gomes
 */
class jsonToFooter
{
	/**
	 * Attributs
	 */
	private $_cache;						// Activation ou non de la mise en cache des contenus
	private $_url;							// url du webservice JSON
	private $_rang;							// Rang de la page
	private $_id_project;					// id du projet
	private $_table;						// bdd table de la page
	private $_id;							// bdd id de la page
	private $_urlType 	= 'crypt';			// sous-domaine | clair | crypt
	private $_json 		= array();			// Requête JSON

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
		$this->_url = $config['url_footer'];

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

	public function setTable($table) {
		$this->_table = $table;
		$this->_json['table'] = $this->_crypt->encrypt($table);
	}

	public function setId($id) {
		$this->_id = $id;
		$this->_json['id'] = $this->_crypt->encrypt($id);
	}

	public function setUrlType($type) {
		$this->_urlType = $type;
		$this->_json['urlType'] = $this->_crypt->encrypt($type);
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
	 * On effectue l'appel REST en postant en POST la requête JSON
	 *
	 * Cette méthode retourne un flux JSON contenant les tirages effectués à partir des SPIN appelés
	 */
	public function reqGetJson()
	{
		# On commence par vérifier si notre flux JSON est en cache et à la date du jour
		$url = str_replace("/","#", $_SERVER['REQUEST_URI']);
		$cacheFile = "F#" . $this->_id_project . "#" . $this->_rang . "#" . date("Ymd") . "#" . $url;

		# Récupération du dossier de cache
		if ($this->_cache===true) {
			$cache = config::getConfig('cacheJson');
		}

		if ($this->_cache===true && file_exists($cache['footer'] . $cacheFile)) {

			$res = file($cache['footer'] . $cacheFile);
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
				$fp = fopen($cache['footer'] . $cacheFile, "w+");
				fputs($fp, $res);
				fclose($fp);

				# On efface les caches de footer ayant une date antérieure
				$listFooter = glob($cache['footer'] . "F#*");

				foreach ($listFooter as $footer) {
					$exp = explode('#', $footer);
					if ($exp[1] == $this->_id_project && $exp[3] != date("Ymd")) {
						unlink($footer);
					}
				}
			}
		}

		return $res;
	}
}
