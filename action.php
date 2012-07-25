<?php
/**
 * DokuWiki Plugin PreserveFilenames / action.php
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Kazutaka Miyasaka <kazmiya@gmail.com>
 */

// must be run within DokuWiki
if (!defined('DOKU_INC')) {
    die();
}

if (!defined('DOKU_PLUGIN')) {
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
}

require_once(DOKU_PLUGIN . 'action.php');

class action_plugin_preservefilenames extends DokuWiki_Action_Plugin
{
    /**
     * Returns some info
     */
    function getInfo()
    {
        return confToHash(DOKU_PLUGIN . 'preservefilenames/plugin.info.txt');
    }

    /**
     * Registers event handlers
     */
    function register(&$controller)
    {
        if (function_exists('tpl_media')) {
            // DokuWiki Angua
            require_once(DOKU_PLUGIN . 'preservefilenames/action_angua.php');
            $handler = new action_plugin_preservefilenames_angua();
            $handler->register($controller);
        } elseif (function_exists('utf8_decodeFN')) {
            // DokuWiki Anteater or Rincewind
            require_once(DOKU_PLUGIN . 'preservefilenames/action_anteater.php');
            $handler = new action_plugin_preservefilenames_anteater();
            $handler->register($controller);
        }
    }
}
