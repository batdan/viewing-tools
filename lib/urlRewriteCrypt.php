<?php
namespace tools;

/**
 * Gestion de la réécriture d'url
 * Récupération des arguments et renvoi ver la page index.php
 *
 * Méthode avec cryptage alpha
 *
 * @author Daniel Gomes
 */
class urlRewriteCrypt
{
	/**
	 * Attributs
	 */
	private $_http_host;
	private $_request_uri;
	private $_rang;
	private $_table;
	private $_id;
	private $_domain;
	private $_subDomain;
	private $_page;
	private $_alpha;


	/**
	 * Constructeur, On récupère le domaine et le reste de l'url
	 *
	 * @param		string		$http_host
	 * @param		string		$request_uri
	 */
	public function __construct($http_host='', $request_uri='')
	{
		if ($http_host == '') 	{ $this->_http_host	= $_SERVER['HTTP_HOST']; 	}
		else 					{ $this->_http_host	= $http_host;				}

		if ($request_uri == '')	{ $this->_request_uri = $_SERVER['REQUEST_URI'];}
		else 					{ $this->_request_uri = $request_uri;			}

		// Suite erreur gestion des noms de ville depuis spin
		if (strstr($_SERVER['REQUEST_URI'], 'nbsp') !== false) {
			$this->redirHTTP(404);
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

		if ($this->_request_uri == '/' || $this->_request_uri == '/index.php') {

			$this->_page = 'home';

		} else {

			// Interpretation de l'url
			$explode_uri 	= explode("/",$this->_request_uri);
			$this->_page	= $explode_uri[count($explode_uri) - 2];
			$this->_alpha	= $explode_uri[count($explode_uri) - 1];

			// Décryptage du code alpha
			$decryptAlpha	= convAlphaNum::decodeAlpha($this->_alpha);

			if ($decryptAlpha['check']) {

				$this->_rang	= $decryptAlpha['rang'];
				$this->_table	= $decryptAlpha['table'];
				$this->_id		= $decryptAlpha['id'];

			} else {

				$this->redirHTTP(404);
			}
		}

		return $this;
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

	public function getDomain() {
		return $this->_domain;
	}

	public function getSubDomain() {
		return $this->_subDomain;
	}

	public function getPage() {
		return $this->_page;
	}

	public function getAlpha() {
		return $this->_alpha;
	}

	public function getTable() {
		return $this->_table;
	}

	public function getId() {
		return $this->_id;
	}

	public function getRang() {
		return $this->_rang;
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
				include("erreur404.php");
				break;
		}

		exit();
	}
}
