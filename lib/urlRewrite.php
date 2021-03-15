<?php
namespace tools;

/**
 * Gestion de la réécriture d'url
 * Récupération des arguments et renvoi ver la page index.php
 *
 * Méthode avec la ville en sous domaine
 *
 * @author Daniel Gomes
 */
class urlRewrite
{
	/**
	 * Attributs
	 *
	 * @author Daniel Gomes
	 */
	private $_dbh;
	private $_http_host;
	private $_request_uri;
	private $_domain;
	private $_subDomain;
	private $_rang;


	/**
	 * Constructeur, On récupère le domaine et le reste de l'url
	 *
	 * @param		string		$http_host
	 * @param		string		$request_uri
	 */
	public function __construct($http_host='', $request_uri='')
	{
		// Instance PDO
		$this->_dbh = dbSingleton::getInstance();

		if ($http_host == '') {
			$this->_http_host	= $_SERVER['HTTP_HOST'];
		} else {
			$this->_http_host	= $http_host;
		}

		if ($request_uri == '') {
			$this->_request_uri	= $_SERVER['REQUEST_URI'];
		} else {
			$this->_request_uri	= $request_uri;
		}

		$explode_domain = explode(".",$this->_http_host);
		if (count($explode_domain) == 3) {
			$this->_subDomain = $explode_domain[0];
			$this->_domain = $explode_domain[1] . '.' . $explode_domain[2];
		} else {
			$this->_domain = $explode_domain[0] . '.' . $explode_domain[1];
		}

		if ($this->_subDomain == '') {
			$this->redirHTTP(301);
		}
	}


	/**
	 * Getters
	 */
	public function getHttpHost() {
		return $this->_http_host;
	}

	public function getRequestUri() {
		return $this->_request_uri;
	}

	public function getSubDomain() {
		return $this->_subDomain;
	}


	/**
	 * Redirections HTTP
	 *
	 * @param 	integer 	$number		Code de la redirection
	 */
	public function redirHTTP($number)
	{
		switch($number) {

			case 301 :
				header("Status: 301 Moved Permanently", false, 301);
				header("Location: http://www." . $this->_domain);
				break;

			case 404 :
				header("Status: 404 Not Found", false, 404);
				include("erreur.php");
				break;
		}

		exit();
	}


	/**
	 * Calcul du rang
	 *
	 * @return 	integer
	 */
	public function getRang()
	{
		if ($this->_subDomain == 'www') {
			$this->_rang = 0;
		} else {
			$this->getRang_aux();
		}

		# Sous domaine inconnu, on retourne une 404
		if (is_null($this->_rang)) {
			$this->redirHTTP(404);
		} else {
			return $this->_rang;
		}
	}


	/**
	 * Fonction auxiliaire de la fonction "getRang"
	 *
	 * @return 	integer
	 */
	private function getRang_aux()
	{
		# On vérifie s'il y a plusieurs occurence pour ce sous-domaine (s'il est suffixé d'un code postal, c'est le cas)
		$nbcar = strlen($this->_subDomain);

		if ($nbcar > 5  &&  is_numeric( substr($this->_subDomain, $nbcar-5, 5) )) {
			$dns		= substr($this->_subDomain, 0, ($nbcar-6));
			$cde_postal	= substr($this->_subDomain, $nbcar-5, 5);

			$req = "SELECT rang FROM geo_communes WHERE dns = :dns AND cde_postal = :cde_postal";
			$sql = $this->_dbh->prepare($req);
			$sql->execute( array( 'dns'=>$dns, 'cde_postal'=>$cde_postal ));
			if ($res = $sql->fetch()) {
				$this->_rang = $res->rang;
			}

		} else {

			$req = "SELECT rang FROM geo_communes WHERE dns = :dns ORDER BY rang LIMIT 0,1";
			$sql = $this->_dbh->prepare($req);
			$sql->execute(array( 'dns'=>$this->_subDomain ));
			if ($res = $sql->fetch()) {
				$this->_rang = $res->rang;
			}
		}
	}


	/**
	 * Récupération du département
	 */
	public function getDep($bdd_ville_projet)
	{
		# Récupération du rang
		if ($this->_subDomain == 'www') {
			$this->_rang = 0;
		} else {
			$this->getRang_aux($bdd_ville_projet);
		}

		if (!empty($this->_rang)) {

			$req = "SELECT cp FROM $bdd_ville_projet WHERE rang=:rang";
			$sql = $this->_dbh->prepare($req);
			$sql->execute(array('rang'=>$this->_rang));
			$res = $sql->fetch();

			return substr($res->cp, 0, 2);
		}
	}


	/**
	 * Récupération de la section
	 * @return string
	 */
	public function getSection()
	{
		$explode_section = explode("/",$this->_request_uri);
		if (count($explode_section) > 1) {
			return $explode_section[1];
		}
	}


	/**
	 * Récupération des arguments liés à la section
	 * @return array
	 */
	public function getArgs()
	{
		$explode_section = explode("/",$this->_request_uri);
		if (count($explode_section) > 2) {
			$result = array();
			for ($i=2; $i<count($explode_section); $i++) {
				if (!empty($explode_section[$i])) {
					$result[] = $explode_section[$i];
				}
			}
			return $result;
		}
	}
}
