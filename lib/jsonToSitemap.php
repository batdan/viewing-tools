<?php
namespace tools;

/**
 * Client JSON
 * Récupère les textes d'un projets
 *
 * @author Daniel Gomes
 */
class jsonToSitemap
{
	/**
	 * Attributs
	 */
	private $_cache;
	private $_id_project;
	private $_url;
	private $_json 		= array();
	private $_maxUrls 	= 20000;


	/**
	 * Constructeur
	 *
	 * @param	boolean		$cache		Activation / Désactivation des cache JSON
	 */
	public function __construct($cache=true)
	{
		$config = config::getConfig('jsonUrls');
		$this->_url = $config['url_sitemap'];

		// Activation par défaut de la gestion des caches
		$this->_cache = $cache;
	}


	/**
	 * Setters
	 */
	public function setIdProjet($id_projet) {
		$this->_id_project = $id_projet;
		$this->_json['id_project'] = $id_projet;
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


	/**
	 * On effectue l'appel REST en postant en POST la requête JSON
	 * Cette méthode retourne un flux JSON contenant les tirages effectués à partir des SPIN appelés
	 *
	 * @param 	array 	$sousSitemap
	 */
	public function reqGetXML( $sousSitemap = false )
	{
		// On commence par vérifier si notre flux JSON est en cache et à la date du jour
		$cacheFile = "Sitemap#" . $this->_id_project . "#" . date("Ymd");

		// Récupération du dossier de cache
		if ($this->_cache===true) {
			$cache = config::getConfig('cacheJson');
		}

		if ($this->_cache===true && file_exists($cache['sitemap'] . $cacheFile)) {

			$res = file($cache['sitemap'] . $cacheFile);
			$res = $res[0];

		} else {

			$json = json_encode($this->_json);

			// On crypte le flux json par sécurité
			$crypt = new crypt("Après la pluie vient le beau temps");
			$jsonCrypt = $crypt->encrypt($json);

			$curl = curl_init();

			curl_setopt($curl, CURLOPT_URL, 			$this->_url);
			curl_setopt($curl, CURLOPT_COOKIESESSION, 	true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 	true);
			curl_setopt($curl, CURLOPT_POST, 			true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, 		array('json'=>$jsonCrypt));
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 	false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 	0);

			$res = curl_exec($curl);
			curl_close($curl);

			// On stock le résultat en cache
			if ($this->_cache===true) {
				$fp = fopen($cache['sitemap'] . $cacheFile, "w+");
				fputs($fp, $res);
				fclose($fp);

				// On efface les sitemaps ayant une date antérieure
				$sitemapOld = glob($cache['sitemap'] . "Sitemap#*");
				
				foreach ($sitemapOld as $sitemap) {
					$exp = explode('#', $sitemap);
					if ($exp[1] == $this->_id_project && $exp[2] != date("Ymd")) {
						unlink($sitemap);
					}
				}
			}
		}

		// Création du XML
		if ($sousSitemap === false) {
			$xml = $this->xmlCreate( json_decode($res) );
		} else {
			$xml = $this->xmlCreateSousSitemap( json_decode($res), $sousSitemap );
		}

		return $xml;
	}


	/**
	 * Création d'un sitemap complet ou d'un sitemapIdex
	 *
	 * @param 	object 	$json
	 */
	private function xmlCreate($json)
	{
		// On vérifie s'il y a un sitemap Locale
		$localSitemap = config::getConfig('localSitemap');

		$protocole 	= $json->protocole;
		$domain 	= $json->domain;
		$countUrls 	= count($json->urls);

		$newDom = new \DOMDocument('1.0', 'utf-8');

		if ($localSitemap['activ'] === true || count($json->urls) > $this->_maxUrls ) {

			$sitemapindex = $newDom->createElement('sitemapindex');
			$sitemapindex->setAttribute('xmlns', "http://www.sitemaps.org/schemas/sitemap/0.9");

			// Lien vers le sitemap local (pages spécifiques au front)
			if ($localSitemap['activ'] === true) {

				$sitemap = $newDom->createElement('sitemap');
				$loc = $newDom->createElement('loc', $protocole . '://' . $domain . $localSitemap['path']);

				$sitemap->appendChild($loc);
				$sitemapindex->appendChild($sitemap);
			}

			// Liens vers les sitemaps des pages générées par viewing
			$countSousSitemaps = ceil($countUrls / $this->_maxUrls);

			for ($i=0; $i<$countSousSitemaps; $i++) {
				$sitemap = $newDom->createElement('sitemap');
				$loc = $newDom->createElement('loc', $protocole . '://' . $domain . '/sitemap-' . $i .'.xml');

				$sitemap->appendChild($loc);
				$sitemapindex->appendChild($sitemap);
			}

			$newDom->appendChild($sitemapindex);

		} else {

			$urlset = $newDom->createElement('urlset');
			$urlset->setAttribute('xmlns', "http://www.sitemaps.org/schemas/sitemap/0.9");

			foreach ($json->urls as $pageUrl) {

				$url = $newDom->createElement('url');
				$loc = $newDom->createElement('loc', $pageUrl);

				$url->appendChild($loc);
				$urlset->appendChild($url);
			}

			$newDom->appendChild($urlset);
		}

		return $newDom->saveXML();
	}


	/**
	 * Création des sous-sitemaps
	 *
	 * @param 	object 		$json
	 * @param 	integer 	$sousSitemap
	 */
	private function xmlCreateSousSitemap($json, $sousSitemap)
	{
		$protocole 	= $json->protocole;
		$domain 	= $json->domain;
		$countUrls 	= count($json->urls);

		$newDom = new \DOMDocument('1.0', 'utf-8');

		$urlset = $newDom->createElement('urlset');
		$urlset->setAttribute('xmlns', "http://www.sitemaps.org/schemas/sitemap/0.9");

		$deb = $sousSitemap * $this->_maxUrls;
		$fin = $deb + $this->_maxUrls;

		for ($i=$deb; $i<$fin; $i++) {

			if (! isset($json->urls[$i])) {
				break;
			}

			$url = $newDom->createElement('url');
			$loc = $newDom->createElement('loc', $json->urls[$i]);

			$url->appendChild($loc);
			$urlset->appendChild($url);
		}

		$newDom->appendChild($urlset);

		return $newDom->saveXML();
	}
}
