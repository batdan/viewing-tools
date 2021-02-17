<?php
namespace tools;

/**
 * collection permet une gestion simple et lisible des manipulations de tableaux.
 *
 * @implements IteratorAggregate
 * @implements ArrayAccess
 */
class collection implements IteratorAggregate, ArrayAccess {

	private $items;

	public function __construct(array $items){
		$this->items = $items;
	}

	/**
	 * get récupère une valeur lié à une clé de la collection.
	 *
	 * Tableau de démo :
	 * $gens = new collection([
	 * 		['nom' => 'DUPOND', 'prenom' => 'Jean', 'age' => 30]
	 * 		['nom' => 'DURAND', 'prenom' => 'Paul', 'age' => 25]
	 * 		['nom' => 'DUPONT', 'prenom' => 'Pierre', 'age' => 50]
	 * 	]);
	 *
	 * ex : var_dump( $gens->get('1.age') );
	 * affichera : 25
	 *
	 * ex : var_dump( $gens->get('1')->get('age') );
	 * affichera : 25
	 *
	 * @access public
	 * @param mixed $key
	 * @return mixed
	 */
	public function get($key){
		$index = explode ('.', $key);
		return $this->getValue($index, $this->items);
	}

	/**
	 * getValue, fonction récursive interne à l'appel get.
	 *
	 * @access private
	 * @param array $indexes
	 * @param mixed $value
	 * @return void
	 */
	private function getValue(array $indexes, $value){
		$key = array_shift($indexes);
		if (empty($indexes)){
			if (!array_key_exists($key, $value)){
				return null;
			}
			if (is_array($value[$key])){
				return new collection($value[$key]);
			}
			else{
				return $value[$key];
			}
		}
		else {
			return $this->getValue($indexes, $value[$key]);
		}
	}

	/**
	 * set insert oumet à jour un couple clé=>valeur dans la collection.
	 *
	 * @access public
	 * @param mixed $key
	 * @param mixed $value
	 * @return null
	 */
	public function set($key, $value){
		$this->items[$key] = $value;
	}

	/**
	 * has function vérifie la présence d'une clé dans la collection.
	 *
	 * @access public
	 * @param mixed $key
	 * @return boolean
	 */
	public function has($key){
		return array_key_exists($key, $this->items);
	}

	/**
	 * lists liste le couple d'éléments demandés en arguments dans une tableau multidimensionnel.
	 *
	 * ex : var_dump( $gens->lists('prenom','age') );
	 * affichera : array['Jean' => 30, 'Paul'=>25, 'Pierre'=>50]
	 *
	 * @access public
	 * @param mixed $key
	 * @param mixed $value
	 * @return object collection
	 */
	public function lists($key, $value){
		$res = array();
		foreach($this->items as $item){
			$res[$item[$key]] = $item[$value];
		}
		return new collection($res);
	}

	/**
	 * extract retourne toutes les valeurs de la clé demandée sous forme de collection.
	 *
	 * ex : var_dump( $gens->extract('age') );
	 * affichera : array[30, 25, 50]
	 *
	 * @access public
	 * @param mixed $key
	 * @return object collection
	 */
	public function extract($key){
		$res = array();
		foreach($this->items as $item){
			$res[] = $item[$key];
		}
		return new collection($res);
	}

	/**
	 * join lie les éléments de l'objet en cours avec le séparateur sélectionné.
	 *
	 * ex : var_dump( $gens->extract('age')->join(', ') );
	 * affichera : 30, 25, 50
	 *
	 * @access public
	 * @param string $glue
	 * @return string
	 */
	public function join($glue){
		return implode($glue, $this->items);
	}

	/**
	 * max retourne la valeur max de la collection ou de la clé demandée.
	 *
	 * @access public
	 * @param bool $key (default: false)
	 * @return void
	 */
	public function max($key = false){
		if ($key){
			return $this->extract($key)->max();
		}
		else {
			return max($this->items);
		}
	}

	/**
	 * min retourne la valeur min de la collection ou de la clé demandée.
	 *
	 * @access public
	 * @param bool $key (default: false)
	 * @return void
	 */
	public function min($key = false){
		if ($key){
			return $this->extract($key)->min();
		}
		else {
			return min($this->items);
		}
	}


	/** EN COURS
	 * reorder tri la collection par la clé et le sens demandé.
	 *
	 * @access public
	 * @param mixed $key
	 * @param string $order (default: "asc")
	 * @return object collection
	 */
	public function reorder($key, $order = "asc"){
		$tmp = array();

		foreach($this->items as $item){
			$tmp[$item[$key]] = $item;
		}

		($order == "asc") ? ksort($tmp) : krsort($tmp);

		$this->items = array();

		foreach($hash as $record){
			$this->items[] = $record;
		}

		return $records;
	}

// 	--------------------------------------------------------------------------

	/**
	 * Fonction obligatoire pour l'ArrayAccess
	 * http://php.net/manual/fr/arrayobject.offsetexists.php
	 */
	public function offsetExists($key){
		return $this->has($key);
	}

	/**
	 * Fonction obligatoire pour l'ArrayAccess
	 * http://php.net/manual/fr/arrayobject.offsetget.php
	 */
	public function offsetGet($key){
		return $this->get($key);
	}

	/**
	 * Fonction obligatoire pour l'ArrayAccess
	 * http://php.net/manual/fr/arrayobject.offsetset.php
	 */
	public function offsetSet($key, $value){
		return $this->set($key, $value);
	}

	/**
	 * Fonction obligatoire pour l'ArrayAccess
	 * http://php.net/manual/fr/arrayobject.offsetunset.php
	 */
	public function offsetUnset($key){
		if ($this->has($key)){
			unset($this->items[$key]);
		}
	}

	/**
	 * Fonction obligatoire pour l'ArrayIterator
	 * http://php.net/manual/fr/class.arrayiterator.php
	 */
	public function getIterator(){
		return new ArrayIterator($this->items);
	}
}
?>
