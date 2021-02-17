<?php
namespace tools;

/**
 * Gestion et rendu de la pagination d'une page de contenu
 *
 * @author Daniel Gomes
 */
class paginator
{
	/**
	 * Attributs
	 */
	private $_maskLinkPageOne 		= '/exemple/endlink';						// Masque de l'url vers la page 1
	private $_maskLinkOtherPages 	= '/exemple-___num_page___/endlink';		// Masque de l'url des autres pages

	private $_nbResult;															// Nombre de résultats total
	private $_nbResultPage			= 10;										// Nombre de résultats par page
	private $_nbPagesBeforeAfter	= 2;										// Nombre de Numéros de page visibles avant et après la page encours

	private $_nbPages;															// Nombre de pages
	private $_pageEncours;														// Page encours
	private $_pages 				= array();									// Tableau contenant les pages à linker
	private $_pagesHtml				= array();									// Tableau contenant le code HTML des pages à linker


	/**
	 * Constructeur
	 *
	 * @param		integer		 $nbResult		Nombre de résultats
	 * @param		integer		 $pageEncours	N° de la page encours
	 */
	public function __construct($nbResult, $pageEncours)
	{
		$this->_nbResult 	= $nbResult;
		$this->_pageEncours = $pageEncours;
	}


	/**
	 * Setters
	 */
	public function setNbResultPage($int)
	{
		$this->_nbResultPage = $int;
	}

	public function setNbPagesBeforeAfter($int)
	{
		$this->_nbPagesBeforeAfter = $int;
	}

	public function setMaskLinkPageOne($str)
	{
		$this->_maskLinkPageOne = $str;
	}

	public function setMaskLinkOtherPages($str)
	{
		$this->_maskLinkOtherPages = $str;
	}


	/**
	 * Préparation des données avant la mise en page
	 */
	private function prepaRender()
	{
		$this->_nbPage = ceil( $this->_nbResult / $this->_nbResultPage );
		$nbMiniLinks = ($this->_nbPagesBeforeAfter * 2) + 1;

		for ($i=1; $i<=$this->_nbPage; $i++) {

			if ($i==1) {
				$hrefPage = $this->_maskLinkPageOne;
			} else {
				$hrefPage = str_replace('___num_page___', $i, $this->_maskLinkOtherPages);
			}

			if ($i == $this->_pageEncours) {

				$this->_pagesHtml[] = '<div class="paginate-page-encours">' . $i . '</div>';
				$this->_pages[] = $i;

			} elseif ($this->_nbPage <= $nbMiniLinks) {

				$this->_pagesHtml[] = '<div class="paginate-page"><a href="' . $hrefPage . '">' . $i . '</a></div>';
				$this->_pages[] = $i;

			} else {

				// Pages linkées avant
				if ($i < $this->_pageEncours) {

					if ( $i >= ($this->_pageEncours - $this->_nbPagesBeforeAfter)  ||  $i > ($this->_nbPage - $nbMiniLinks) ) {
						$this->_pagesHtml[] = '<div class="paginate-page"><a href="' . $hrefPage . '">' . $i . '</a></div>';
						$this->_pages[] = $i;
					}
				}

				// Pages linkées après
				if ($i > $this->_pageEncours) {

					if ( $i <= ($this->_pageEncours + $this->_nbPagesBeforeAfter)  ||  $i < (1 + $nbMiniLinks) ) {
						$this->_pagesHtml[] = '<div class="paginate-page"><a href="' . $hrefPage . '">' . $i . '</a></div>';
						$this->_pages[] = $i;
					}
				}
			}
		}
	}


	public function render()
	{
		$this->prepaRender();

		$html = '';

		if ( $this->_pages[0] >= 2 ) {
			$html .= '<div class="paginate-page"><a href="' . $this->_maskLinkPageOne . '"><i class="fa fa-angle-double-left" aria-hidden="true"></i></a></div>';
			$html .= '<div class="paginate-separ">...</div>';
		}

		$html .= implode('', $this->_pagesHtml);

		if ( end($this->_pages) < $this->_nbPage ) {

			$hrefPageEnd = str_replace('___num_page___', $this->_nbPage, $this->_maskLinkOtherPages);

			$html .= '<div class="paginate-separ">...</div>';
			$html .= '<div class="paginate-page"><a href="' . $hrefPageEnd . '"><i class="fa fa-angle-double-right" aria-hidden="true"></i></a></div>';
		}

		return $html;
	}

	/**
	 * Retourne le tableau d'un mois
	 * Les jours contenant des données deviendront cliquables
	 *
	 * @param 	string		$bgEncours			// Background page active
	 * @param 	string		$borderEncours		// border page active
	 * @param 	string		$txtEncours			// Couleur textes page active
	 * @param 	string		$bgOther			// Background autres pages
	 * @param 	string		$borderOther		// border autres pages
	 * @param 	string		$txtOther			// Couleur textes autres pages
	 * @param 	string		$bgOtherHover		// Background autres pages au survol
	 * @param 	string		$borderOtherHover	// border autres pages au survol
	 * @param 	string		$txtOtherHover		// Couleur textes autres pages au survol
	 *
	 * @param 	string		$forme				// Donne une forme aux boutons de pargination ( radius-3, radius-5, carre, circle)
	 */
	public static function cssPaginator($bgEncours='#333',  		$borderEncours='#333', 			$txtEncours='#fff',
										$bgOther='#f4f4f4', 		$borderOther='#ccc', 			$txtOther='#ccc',
										$bgOtherHover='#881E82', 	$borderOtherHover='#881E82', 	$txtOtherHover='#fff',
										$forme='radius-3')
	{
		switch ($forme)
		{
			case 'radius-3' : $borderRadius = '3px';	break;
			case 'radius-5' : $borderRadius = '5px';	break;
			case 'carre' 	: $borderRadius = 'none';	break;
			case 'circle'	: $borderRadius = '50%';	break;
		}

		$css = <<<eof
.paginate-page-encours {
	display: inline-block;
	background: $bgEncours;
	color: $txtEncours;
	font-size: 14px;
	font-weight: bold;
	border:1px solid $borderEncours;
	padding: 0 7px;
	border-radius: $borderRadius;
	margin: 0 3px;
	cursor: default;
}

.paginate-page {
	background:$bgOther;
	border: 1px solid $borderOther;
	display: inline-block;
	border-radius: $borderRadius;
	margin: 0 3px;
	transition: all 300ms;
}

.paginate-page:hover {
	background:$bgOtherHover;
	border: 1px solid $borderOtherHover;
}

.paginate-page a {
	display: block;
	padding: 0 7px;
	font-size: 14px;
	font-weight: bold;
	transition: all 300ms;
}

.paginate-page a:link     { color:$txtOther;		 }
.paginate-page a:visited  { color:$txtOther;		 }
.paginate-page a:hover    { color:$txtOtherHover;	 }
.paginate-page a:active   { color:$txtOtherHover;	 }

.paginate-separ {
	display: inline-block;
	margin: 0 5px;
}
eof;

		// Minification du CSS
		if (class_exists('\Minify_CSSmin')) {
			$cssMinify = new \Minify_CSSmin();
			$css  = '/* paginator.css */' . chr(10) . $cssMinify->minify($css);
		}

		\tools\libIncluder::add_CssScript($css);
	}
}
