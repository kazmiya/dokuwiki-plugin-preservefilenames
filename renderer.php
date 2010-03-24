<?php
/**
 * DokuWiki Plugin PreserveFilenames / renderer.php
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Kazutaka Miyasaka <kazmiya@gmail.com>
 */

// must be run within DokuWiki
if (!defined('DOKU_INC')) die();

require_once(DOKU_INC.'inc/parser/code.php');

class renderer_plugin_preservefilenames extends Doku_Renderer_code {
    /**
     * Outputs code snippet with a correct filename
     * 
     * @see Doku_Renderer_code::code
     */
    function code($text, $language = NULL, $filename = '') {
        // do nothing if codeblock number not matched
        if ($_REQUEST['codeblock'] != $this->_codeblock++) return;

        $action =& plugin_load('action', 'preservefilenames');

        if (!$language) $language = 'txt';
        if (!$filename) $filename = 'snippet.'.$language;

        $filename = $this->getConf('fix_phpbug37738')
            ? $action->_correctBasename($filename)
            : basename($filename);
        $filename = $action->_sanitizeFilename($filename);
        if (!$filename) $filename = 'snippet.'.$language; // check again

        $disposition_header = $this->getConf('use_rfc2231')
            ? $action->_buildContentDispositionHeader('download', $filename)
            : 'Content-Disposition: attachment; filename="'.rawurlencode($filename).'"';

        header("Content-Type: text/plain; charset=utf-8");
        header($disposition_header);
        header("X-Robots-Tag: noindex");
        print trim($text, "\r\n");
        exit;
    }
}
