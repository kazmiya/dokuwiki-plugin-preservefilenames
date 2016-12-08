<?php
/**
 * DokuWiki Plugin PreserveFilenames / action_anteater.php
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
require_once(DOKU_PLUGIN . 'preservefilenames/common.php');

class action_plugin_preservefilenames_anteater extends DokuWiki_Action_Plugin
{
    /**
     * Common functions
     */
    protected $common;

    /**
     * Registers event handlers
     */
    function register(Doku_Event_Handler $controller)
    {
        $this->common = new PreserveFilenames_Common();

        $controller->register_hook('MEDIA_UPLOAD_FINISH',          'AFTER',  $this, '_saveMeta');
        $controller->register_hook('MEDIA_DELETE_FILE',            'AFTER',  $this, '_deleteMeta');
        $controller->register_hook('MEDIA_SENDFILE',               'BEFORE', $this, '_sendFile');
        $controller->register_hook('PARSER_HANDLER_DONE',          'BEFORE', $this, '_replaceLinkTitle');
        $controller->register_hook('RENDERER_CONTENT_POSTPROCESS', 'AFTER',  $this, '_replaceLinkURL');
        $controller->register_hook('MEDIAMANAGER_STARTED',         'AFTER',  $this, '_exportToJSINFO');
        $controller->register_hook('MEDIAMANAGER_CONTENT_OUTPUT',  'BEFORE', $this, '_showMediaList');
        $controller->register_hook('AJAX_CALL_UNKNOWN',            'BEFORE', $this, '_showMediaListAjax');
        $controller->register_hook('ACTION_ACT_PREPROCESS',        'BEFORE', $this, '_replaceSnippetDownload');
    }

    /**
     * Saves the name of the uploaded media file to a meta file
     */
    function _saveMeta(&$event)
    {
        global $conf;

        $id = $event->data[2];
        $filename_tidy = noNS($id);

        // retrieve original filename
        if (!empty($_POST['id'])) {
            // via normal uploader
            $filename_pat = $conf['useslash'] ? '/([^:;\/]*)$/' : '/([^:;]*)$/';
            preg_match($filename_pat, $_POST['id'], $matches);
            $filename_orig = $matches[1];
        } elseif (isset($_FILES['Filedata'])) {
            // via multiuploader
            $filename_orig = $_FILES['upload']['name'];
        } else {
            return;
        }

        $filename_safe = $this->common->_sanitizeFileName($filename_orig);

        // no need to save original filename
        if ($filename_tidy === $filename_safe) {
            return;
        }

        // fallback if suspicious characters found
        if ($filename_orig !== $filename_safe) {
            return;
        }

        // save original filename to metadata
        $metafile = metaFN($id, '.filename');
        io_saveFile($metafile, serialize(array(
            'filename' => $filename_safe,
        )));
    }

    /**
     * Deletes a meta file associated with the deleted media file
     */
    function _deleteMeta(&$event)
    {
        $id = $event->data['id'];
        $metafile = metaFN($id, '.filename');

        if (@unlink($metafile)) {
            io_sweepNS($id, 'metadir');
        }
    }

    /**
     * Sends a media file with its original filename
     * 
     * @see sendFile() in lib/exe/fetch.php
     */
    function _sendFile(&$event)
    {
        global $conf;
        global $MEDIA;

        $d = $event->data;
        $event->preventDefault();
        list($file, $mime, $dl, $cache) = array($d['file'], $d['mime'], $d['download'], $d['cache']);

        $fmtime = @filemtime($file);

        // send headers
        header("Content-Type: $mime");

        // smart http caching headers
        if ($cache == -1) {
            // cache
            // cachetime or one hour
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + max($conf['cachetime'], 3600)) . ' GMT');
            header('Cache-Control: public, proxy-revalidate, no-transform, max-age=' . max($conf['cachetime'], 3600));
            header('Pragma: public');
        } elseif ($cache > 0) {
            // recache
            // remaining cachetime + 10 seconds so the newly recached media is used
            header('Expires: ' . gmdate("D, d M Y H:i:s", $fmtime + $conf['cachetime'] + 10) . ' GMT');
            header('Cache-Control: public, proxy-revalidate, no-transform, max-age=' . max($fmtime - time() + $conf['cachetime'] + 10, 0));
            header('Pragma: public');
        } elseif ($cache == 0) {
            // nocache
            header('Cache-Control: must-revalidate, no-transform, post-check=0, pre-check=0');
            header('Pragma: public');
        }

        // send important headers first, script stops here if '304 Not Modified' response
        http_conditionalRequest($fmtime);

        // retrieve original filename and send Content-Disposition header
        $filename = $this->_getOriginalFileName($MEDIA);

        if ($filename === false) {
            $filename = utf8_decodeFN($this->common->_correctBasename($d['file']));
        }

        header($this->common->_buildContentDispositionHeader($dl, $filename));

        // use x-sendfile header to pass the delivery to compatible webservers
        if (http_sendfile($file)) {
            exit;
        }

        // send file contents
        $fp = @fopen($file, 'rb');

        if ($fp) {
            http_rangeRequest($fp, filesize($file), $mime);
        } else {
            header('HTTP/1.0 500 Internal Server Error');
            print "Could not read $file - bad permissions?";
        }
    }

    /**
     * Replaces titles of non-labeled internal media links with their original filenames
     */
    function _replaceLinkTitle(&$event)
    {
        global $ID;
        global $conf;

        require_once(DOKU_INC . 'inc/JpegMeta.php');

        $ns = getNS($ID);

        // get the instructions list from the handler
        $calls =& $event->data->calls;

        // array index numbers for readability
        list($handler_name, $instructions, $source, $title, $linking) = array(0, 1, 0, 1, 6);

        // scan media link and mark it with its original filename
        $last = count($calls) - 1;

        for ($i = 0; $i <= $last; $i++) {
            // NOTE: 'externalmedia' is processed here because there is a
            //       basename() bug in fetching its auto-filled linktext.
            //       For more details please see common->_correctBasename().
            if (!preg_match('/^(?:in|ex)ternalmedia$/', $calls[$i][$handler_name])) {
                continue;
            }

            $inst =& $calls[$i][$instructions];
            $filename = false;
            $linktext = $inst[$title];
            $linkonly = ($inst[$linking] === 'linkonly');
            list($src, $hash) = explode('#', $inst[$source], 2);

            // get original filename
            if ($calls[$i][$handler_name] === 'internalmedia') {
                resolve_mediaid($ns, $src, $exists);
                list($ext, $mime, $dl) = mimetype($src);
                $filename = $this->_getOriginalFileName($src);
            } else {
                list($ext, $mime, $dl) = mimetype($src);
            }

            // prefetch auto-filled linktext
            if (!$linktext) {
                if (
                    substr($mime, 0, 5) === 'image'
                    && ($ext === 'jpg' || $ext === 'jpeg')
                    && ($jpeg = new JpegMeta(mediaFN($src)))
                    && ($caption = $jpeg->getTitle())
                ) {
                    $linktext = $caption;
                } else {
                    $linktext = $this->common->_correctBasename(noNS($src));
                }
            }

            // add a marker (normally you cannot put '}}' in a media link title
            // and cannot put ':' in a filename)
            if ($filename === false) {
                $inst[$title] = $linktext;
            } elseif ($inst[$title] !== $linktext) {
                $inst[$title] = $linktext . '}}preservefilenames:autofilled:' . $filename;
            } else {
                $inst[$title] = $linktext . '}}preservefilenames::' . $filename;
            }
        }
    }

    /**
     * Replaces url and title of a link which has original filename info
     */
    function _replaceLinkURL(&$event)
    {
        if ($event->data[0] !== 'xhtml') {
            return;
        }

        // image link
        $event->data[1] = preg_replace_callback(
            '/
                <a ([^>]*)>
                (?:
                    ([^<>\}]*)\}\}preservefilenames:(autofilled)?:([^<]*)
                    |
                    (<img [^>]*?alt="([^"]*)\}\}preservefilenames:(autofilled)?:([^"]*)"[^>]*>)
                )
                <\/a>
            /x',
            array(self, '_replaceLinkURL_callback_a'),
            $event->data[1]
        );

        // embedded image
        $event->data[1] = preg_replace_callback(
            '/<img [^>]*?alt="([^"]*)\}\}preservefilenames:(autofilled)?:([^"]*)"[^>]*>/',
            array(self, '_replaceLinkURL_callback_img'),
            $event->data[1]
        );
    }

    /**
     * Callback function for _replaceLinkURL (link)
     */
    static function _replaceLinkURL_callback_a($matches)
    {
        list($atag, $attr_str, $linktext, $autofilled, $filename) = array_slice($matches, 0, 5);

        if (!preg_match('/class="media[" ]/', $attr_str)) {
            return $atag;
        }

        if (isset($matches[5])) {
            // image link
            $filename = $matches[8];
            $linktext = self::_replaceLinkURL_callback_img(array_slice($matches, 5, 4));
        } else {
            // text link
            if ($autofilled) {
                $linktext = $filename;
            }
        }

        $filename = htmlspecialchars_decode($filename, ENT_QUOTES);
        $pageid = cleanID($filename);

        $attr_str = preg_replace(
            array(
                '/(href="[^"]*)' . preg_quote(rawurlencode($pageid)) . '((?:\?[^#]*)?(?:#[^"]*)?")/',
                '/(title="[^"]*)' . preg_quote($pageid) . '(")/'
            ),
            array(
                "\${1}" . rawurlencode($filename) . '\2',
                "\${1}" . hsc($filename) . '\2'
            ),
            $attr_str
        );

        return '<a ' . $attr_str . '>' . $linktext . '</a>';
    }

    /**
     * Callback function for _replaceLinkURL (image)
     */
    static function _replaceLinkURL_callback_img($matches)
    {
        list($imgtag, $imgtitle, $autofilled, $filename) = $matches;

        if (!preg_match('/class="media(?:left|right|center)?[" ]/', $imgtag)) {
            return $imgtag;
        }

        if ($autofilled) {
            $imgtitle = $filename;
        }

        $filename = htmlspecialchars_decode($filename, ENT_QUOTES);
        $pageid = cleanID($filename);

        $imgtag = preg_replace(
            array(
                '/(src="[^"]*)' . preg_quote(rawurlencode($pageid)) . '((?:\?[^#]*)?(?:#[^"]*)?")/',
                '/(alt|title)="[^"]*"/'
            ),
            array(
                "\${1}" . rawurlencode($filename) . '\2',
                '\1="' . $imgtitle . '"'
            ),
            $imgtag
        );

        return $imgtag;
    }

    /**
     * Exports configuration settings to $JSINFO
     */
    function _exportToJSINFO(&$event)
    {
        global $JSINFO;

        $JSINFO['plugin_preservefilenames'] = array(
            'in_mediamanager' => true,
        );
    }

    /**
     * Shows a list of media
     */
    function _showMediaList(&$event)
    {
        global $NS;
        global $AUTH;
        global $JUMPTO;

        if ($event->data['do'] !== 'filelist') {
            return;
        }

        $event->preventDefault();

        ptln('<div id="media__content">');
        $this->_listMedia($NS, $AUTH, $JUMPTO);
        ptln('</div>');
    }

    /**
     * Shows a list of media via ajax
     */
    function _showMediaListAjax(&$event)
    {
        global $JUMPTO;

        if ($event->data !== 'medialist_preservefilenames') {
            return;
        }

        $event->preventDefault();

        require_once(DOKU_INC . 'inc/media.php');
        $ns = cleanID($_POST['ns']);
        $auth = auth_quickaclcheck("$ns:*");

        $this->_listMedia($ns, $auth, $JUMPTO);
    }

    /**
     * Outputs a list of files for mediamanager
     * 
     * @see media_filelist() in inc/media.php
     */
    function _listMedia($ns, $auth, $jumpto)
    {
        global $conf;
        global $lang;

        print '<h1 id="media__ns">:' . hsc($ns) . '</h1>' . NL;

        if ($auth < AUTH_READ) {
            print '<div class="nothing">' . $lang['nothingfound'] . '</div>' . NL;
        } else {
            media_uploadform($ns, $auth);

            $dir = utf8_encodeFN(str_replace(':', '/', $ns));
            $data = array();
            search($data, $conf['mediadir'], 'search_media',
                    array('showmsg' => true, 'depth' => 1), $dir);

            if (empty($data)) {
                print '<div class="nothing">' . $lang['nothingfound'] . '</div>' . NL;
            } else {
                foreach ($data as $item) {
                    $filename = $this->_getOriginalFileName($item['id']);

                    if ($filename !== false) {
                        $item['file'] = utf8_encodeFN($filename);
                    }

                    media_printfile($item, $auth, $jumpto);
                }
            }
        }

        media_searchform($ns);
    }

    /**
     * Returns original filename if exists
     */
    function _getOriginalFileName($id)
    {
        $meta = unserialize(io_readFile(metaFN($id, '.filename'), false));
        return empty($meta['filename']) ? false : $this->common->_sanitizeFileName($meta['filename']);
    }

    /**
     * Replaces the default snippet download handler
     * 
     * NOTE: This method is needed to fix basename() bug in determining 
     *       filename of the snippet. For more details please see 
     *       common->_correctBasename().
     */
    function _replaceSnippetDownload(&$event)
    {
        global $ACT;

        // $ACT is not clean, but in most cases this works fine
        if ($ACT === 'export_code') $ACT = 'export_preservefilenames';
    }
}
