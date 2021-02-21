<?php
namespace tools;

/**
 * Manipulation des images
 *
 * @author Daniel Gomes
 */
class images
{
    /**
     * Permet de sauvegarder une image
     *
     * @param  string   $imagePath  Chemin de l'image à sauvegarder
     * @param  string   $saveToDir  Répertoire de destination
     * @param  string   $imageName  Nouveau nom de l'image
     */
    public static function saveImage($imagePath, $saveToDir, $imageName)
    {
        $ch = curl_init($imagePath);

        // Ajout d'un slash au path s'il est manquant
        if ($saveToDir[strlen($saveToDir)-1] != '/') {
            $saveToDir .= '/';
        }
        $fp = fopen($saveToDir . $imageName, 'wb');

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        curl_close($ch);

        fclose($fp);
    }


    /**
     * Permet de redimensionner et sauvegarder une image
     *
     * @param  string   $imagePath      Chemin de l'image à redimensionner
     * @param  string   $saveToDir      Répertoire de destination
     * @param  string   $imageName      Nouveau nom de l'image
     * @param  integer  $max_x          Nouvelle largeur
     * @param  integer  $max_y          Nouvelle hauteur
     */
    public static function saveThumbnail($imagePath, $saveToDir, $imageName, $max_x, $max_y)
    {
        preg_match("'^(.*)\.(gif|jpe?g|png)$'i", $imageName, $ext);
        $extension = strtolower($ext[2]);
        if ($extension == 'jpeg') {
            $extension = 'jpg';
        }

        switch ($extension)
        {
            case 'jpg' :
            case 'jpeg': $im   = imagecreatefromjpeg ($imagePath);
                         break;
            case 'gif' : $im   = imagecreatefromgif  ($imagePath);
                         break;
            case 'png' : $im   = imagecreatefrompng  ($imagePath);
                         break;
            default    : $stop = true;
                         break;
        }

        if (!isset($stop)) {
            $x = imagesx($im);
            $y = imagesy($im);

            if (($max_x/$max_y) < ($x/$y)) {
                $save = imagecreatetruecolor($x/($x/$max_x), $y/($x/$max_x));
            } else {
                $save = imagecreatetruecolor($x/($y/$max_y), $y/($y/$max_y));
            }

            imagecopyresized($save, $im, 0, 0, 0, 0, imagesx($save), imagesy($save), $x, $y);

            // Ajout d'un slash au path s'il est manquant
            if ($saveToDir[strlen($saveToDir)-1] != '/') {
                $saveToDir .= '/';
            }
            $newImagePath = $saveToDir . $ext[1] . '.' . $extension;

            switch($extension)
            {
                case 'jpg' : imagejpeg($save, $newImagePath);   break;
                case 'gif' : imagegif($save, $newImagePath);    break;
                case 'png' : imagepng($save, $newImagePath);    break;
            }

            imagedestroy($im);
            imagedestroy($save);
        }
    }
}
