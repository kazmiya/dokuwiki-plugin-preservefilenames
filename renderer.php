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

        $language = $action->_sanitizeFilename($language);
        if (!$language) $language = 'txt';

        $filename = $action->_correctBasename($filename);
        $filename = $action->_sanitizeFilename($filename);
        if (!$filename) $filename = 'snippet.'.$language;

        $c_d_header = $action->_buildContentDispositionHeader('download', $filename);

        // add pathinfo if no filename-parm in C-D header (workaround for Safari)
        if (!isset($_REQUEST['preservefilenames_redirected'])
                && strpos($c_d_header, 'filename') === false) {
            global $ID;
            global $conf;

            $conf['useslash'] = $conf['userewrite'] = 0;
            $params = array(
                'do' => 'export_preservefilenames',
                'codeblock' => $_REQUEST['codeblock'],
                'preservefilenames_redirected' => '1',
            );
            $redirect_url = preg_replace(
                '/^([^\?]*)(\?.*)?$/',
                '\1/'.rawurlencode($filename).'\2',
                wl($ID, $params, 'absolute', '&')
            );
            send_redirect($redirect_url);
            exit; // just in case
        }

        header("Content-Type: text/plain; charset=utf-8");
        header($c_d_header);
        header("X-Robots-Tag: noindex");
        print trim($text, "\r\n");
        exit;
    }
}
