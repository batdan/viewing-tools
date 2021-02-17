<?php
namespace tools;

/**
 * Manipulation des couleurs
 *
 * @author Daniel Gomes (adaptation de scripts issus du Web)
 */
class color
{
    /**
     * Conversion d'une couleur au format hexadémal vers RVB
     *
     * @param       string      $color      Couleur au format hexadecimal
     * @return      array
     */
    public static function hex2rgb($color)
    {
    	if (strlen($color) > 1 && $color[0] == '#') {
            $color = substr($color, 1);
        }

    	if (strlen($color) == 6) {
    		list($r, $g, $b) = array( $color[0].$color[1], $color[2].$color[3], $color[4].$color[5] );
        } elseif (strlen($color) == 3) {
    		list($r, $g, $b) = array( $color[0].$color[0], $color[1].$color[1], $color[2].$color[2] );
    	} else {
    		return false;
        }

    	return array(
    		'R' => hexdec($r),
    		'V' => hexdec($g),
    		'B' => hexdec($b)
    	);
    }


    /**
     * Conversion d'une couleur au format RVB vers hexadémal
     *
     * @param       integer     $r      Valeur décimal du rouge
     * @param       integer     $v      Valeur décimal du vert
     * @param       integer     $b      Valeur décimal du bleu
     * @return      string
     */
    public static function rgb2hex($r, $v, $b)
    {
    	$hex  = "#";
    	$hex .= str_pad( dechex($r), 2, "0", STR_PAD_LEFT );
    	$hex .= str_pad( dechex($v), 2, "0", STR_PAD_LEFT );
    	$hex .= str_pad( dechex($b), 2, "0", STR_PAD_LEFT );

        return $hex;
    }
}
