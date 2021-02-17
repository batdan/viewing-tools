<?php
namespace tools;

/**
 * Gestion des librairies CSS et JS / Gestion des ajouts de code CSS et JS
 * Liste des librairies disponibles
 *
 * @author Daniel Gomes
 */
class libIncluderList extends libIncluder
{
    /**
     * Chargement de JQuery 2.1.4
     * http://jquery.com/
     */
    public static function add_jQuery($optimize=true)
    {
        //$js     = array("//code.jquery.com/jquery-1.12.4.min.js");
        $js     = array("//code.jquery.com/jquery-2.2.4.min.js");

        parent::add_JsLib($js, $optimize);
    }


    /**
     * Chargement de JQuery UI 1.11.4 dans une version limité au strict nécessaire et sans thème
     * Modules présents :
     *          - UI Core (Core, Widget, Mouse, Position), Interaction
     *          - Interactions (Draggable, Droppable, Resizable, Selectable, Sortable)
     */
    public static function add_jQueryUI($optimize=true)
    {
        self::add_jQuery();

        $js     = array("//ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js");
        //$css    = array("//ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css");

        parent::add_JsLib($js, $optimize);
        //parent::add_CssLib($css, $optimize);
    }


    /**
     * Chargement de la bibliothèque font-awesome
     * http://fontawesome.io/
     */
    public static function add_fontAwesome($optimize=true)
    {
        $css    = array("//maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css");

        parent::add_CssLib($css, $optimize);
    }


    /**
     * Chargement de la bibliothèque Material Design Icons
     * https://materialdesignicons.com/
     */
    public static function add_materialdesignicons($optimize=true)
    {
        $css    = array("//cdn.materialdesignicons.com/1.7.22/css/materialdesignicons.min.css");

        parent::add_CssLib($css, $optimize);
    }


    /**
     * Chargement de Bootstrap 3
     * http://getbootstrap.com/
     */
    public static function add_bootstrap($optimize=true)
    {
        self::add_jQuery();

        $js     = array("//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js");

        $css    = array("//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css",
                        "//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css");

        parent::add_JsLib($js, $optimize);
        parent::add_CssLib($css, $optimize);
    }


    /**
     * Chargement de Bootstrap-validator
     * http://1000hz.github.io/bootstrap-validator/
     */
    public static function add_bootstrapValidator($optimize=true)
    {
        self::add_jQuery();
        self::add_bootstrap();

        $js     = array("//cdn.jsdelivr.net/jquery.bootstrapvalidator/0.5.3/js/bootstrapValidator.min.js");

        parent::add_JsLib($js, $optimize);
    }


    /**
     * Chargement de Bootstrap Select
     * http://silviomoreto.github.io/bootstrap-select/
     */
    public static function add_bootstrapSelect($optimize=true)
    {
        self::add_jQuery();
        self::add_bootstrap();

        $js     = array("//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.7.3/js/bootstrap-select.min.js",
                        "//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.7.3/js/i18n/defaults-fr_FR.min.js");

        $css    = array("//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.7.3/css/bootstrap-select.min.css");

        parent::add_JsLib($js, $optimize);
        parent::add_CssLib($css, $optimize);
    }


    /**
     * Parser, valider, manipuler et afficher des dates
     * http://momentjs.com
     */
    public static function add_moment($optimize=true)
    {
        self::add_jQuery();
        self::add_bootstrap();

        $js     = array("//cdnjs.cloudflare.com/ajax/libs/moment.js/2.10.6/moment.min.js",
                        "//cdnjs.cloudflare.com/ajax/libs/moment.js/2.10.6/locale/fr.js");

        parent::add_JsLib($js, $optimize);
    }


    /**
     * Chargement de Bootstrap datetimepicker
     * https://eonasdan.github.io/bootstrap-datetimepicker
     */
    public static function add_bootstrapDatetimepicker($optimize=true)
    {
        self::add_jQuery();
        self::add_bootstrap();
        self::add_moment();

        $js     = array("//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.15.35/js/bootstrap-datetimepicker.min.js");
        $css    = array("//cdnjs.cloudflare.com/ajax/libs/bootstrap-datetimepicker/4.15.35/css/bootstrap-datetimepicker.min.css");

        parent::add_JsLib($js, $optimize);
        parent::add_CssLib($css, $optimize);
    }


    /**
     * Chargement de Bootstrap Table
     * http://bootstrap-table.wenzhixin.net.cn/documentation/
     */
    public static function add_bootstrapTable($optimize=true)
    {
        self::add_jQuery();
        self::add_bootstrap();

        $js     = array("//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.8.1/bootstrap-table.min.js",
                        "//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.8.1/locale/bootstrap-table-fr-FR.min.js");

        $css    = array("//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.8.1/bootstrap-table.min.css");

        parent::add_JsLib($js, $optimize);
        parent::add_CssLib($css, $optimize);
    }


    /**
     * Chargement de ckEditor
     * http://ckeditor.com/
     */
    public static function add_ckEditor($optimize=true)
    {
        self::add_jQuery();

        $js     = array("//cdn.ckeditor.com/4.5.3/standard/ckeditor.js");

        parent::add_JsLib($js, $optimize);
    }


    /**
     * Chargement de mousewheel
     * Librairie nécessaire à fancybox
     */
    public static function add_mousewheel($optimize=true)
    {
        self::add_jQuery();

        $js     = array("//cdn.jsdelivr.net/mousewheel/3.1.13/jquery.mousewheel.min.js");

        parent::add_JsLib($js, $optimize);
    }


    /**
     * Chargement de fancybox
     * http://fancyapps.com/fancybox/
     */
    public static function add_fancybox($optimize=true)
    {
        self::add_jQuery();
        self::add_mousewheel();

        $js     = array("//cdn.jsdelivr.net/fancybox/2.1.5/jquery.fancybox.min.js");
        $css    = array("//cdn.jsdelivr.net/fancybox/2.1.5/jquery.fancybox.min.css");

        parent::add_JsLib($js, $optimize);
        parent::add_CssLib($css, $optimize);
    }
}
