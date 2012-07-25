<?php
/**
 * DokuWiki Plugin PreserveFilenames / action_angua.php
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

require_once(DOKU_PLUGIN . 'preservefilenames/common.php');
require_once(DOKU_PLUGIN . 'preservefilenames/action_anteater.php');

class action_plugin_preservefilenames_angua extends action_plugin_preservefilenames_anteater
{
    /**
     * Registers event handlers
     */
    function register(&$controller)
    {
        $this->common = new PreserveFilenames_Common();
    }
}
