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
    function register(Doku_Event_Handler $controller)
    {
        $this->common = new PreserveFilenames_Common();

        $controller->register_hook('MEDIA_UPLOAD_FINISH',          'AFTER',  $this, '_saveMeta');
        $controller->register_hook('MEDIA_DELETE_FILE',            'AFTER',  $this, '_deleteMeta');
        $controller->register_hook('MEDIA_SENDFILE',               'BEFORE', $this, '_sendFile');
        $controller->register_hook('PARSER_HANDLER_DONE',          'BEFORE', $this, '_replaceLinkTitle');
        $controller->register_hook('RENDERER_CONTENT_POSTPROCESS', 'AFTER',  $this, '_replaceLinkURL');
        $controller->register_hook('MEDIAMANAGER_CONTENT_OUTPUT',  'BEFORE', $this, '_handleMediaContent');
        $controller->register_hook('TPL_ACT_RENDER',               'BEFORE', $this, '_handleMediaFullscreen');
        $controller->register_hook('AJAX_CALL_UNKNOWN',            'BEFORE', $this, '_handleAjaxMediaList');
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
        if (isset($_GET['qqfile'])) {
            // via ajax uploader
            $filename_orig = (string) $_GET['qqfile'];
        } elseif (isset($_POST['mediaid'])) {
            if (isset($_FILES['qqfile']['name'])) {
                // via ajax uploader
                $filename_orig = (string) $_FILES['qqfile']['name'];
            } elseif (isset($_FILES['upload']['name'])) {
                // via old-fashioned upload form
                $filename_orig = (string) $_FILES['upload']['name'];
            } else {
                return;
            }

            // check if filename is specified
            $specified_name = (string) $_POST['mediaid'];

            if ($specified_name !== '') {
                $filename_orig = $specified_name;
            }
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

        // save original filename to meta file
        io_saveFile(
            mediaMetaFN($id, '.filename'),
            serialize(array(
                'filename' => $filename_safe,
            ))
        );
    }

    /**
     * Deletes a meta file associated with the deleted media file
     */
    function _deleteMeta(&$event)
    {
        $id = $event->data['id'];
        $metaFilePath = mediaMetaFN($id, '.filename');

        if (@unlink($metaFilePath)) {
            io_sweepNS($id, 'mediametadir');
        } else {
            parent::_deleteMeta($event);
        }
    }

    /**
     * Returns original filename if exists
     */
    function _getOriginalFileName($id)
    {
        $metaFilePath = mediaMetaFN($id, '.filename');
        $meta = unserialize(io_readFile($metaFilePath, false));

        if (empty($meta['filename'])) {
            // check old meta file (for backward compatibility)
            $filename = parent::_getOriginalFileName($id);

            // move old meta file to media_meta directory
            if ($filename !== false) {
                $oldMetaFilePath = metaFN($id, '.filename');
                io_rename($oldMetaFilePath, $metaFilePath);
            }

            return $filename;
        } else {
            return $this->common->_sanitizeFileName($meta['filename']);
        }
    }

    /**
     * Handles media manager content output
     *
     * @see tpl_mediaContent
     */
    function _handleMediaContent(&$event)
    {
        global $NS;
        global $AUTH;
        global $JUMPTO;

        if ($event->data['do'] !== 'filelist') {
            return;
        }

        $event->preventDefault();
        $this->_mod_media_filelist($NS, $AUTH, $JUMPTO);
    }

    /**
     * Handles an action that calls full-screen media manager 
     *
     * @see tpl_content_core()
     */
    function _handleMediaFullscreen(&$event)
    {
        if ($event->data !== 'media') {
            return;
        }

        $event->preventDefault();
        $this->_mod_tpl_media();
    }

    /**
     * Handles a 'medialist' ajax call
     *
     * @see ajax_medialist()
     */
    function _handleAjaxMediaList(&$event)
    {
        global $NS;

        if ($event->data !== 'preservefilenames_medialist') {
            return;
        }

        $event->preventDefault();
        $NS = cleanID($_POST['ns']);

        if ($_POST['do'] === 'media') {
            $this->_mod_tpl_mediaFileList();
        } else {
            tpl_mediaContent('fromAjax');
        }
    }

    // -------------------------------------------------------
    // The following methods whose name starts with '_mod' are
    // slightly modified versions of existing functions.
    // -------------------------------------------------------

    /**
     * Prints full-screen media manager
     *
     * @see tpl_media()
     */
    function _mod_tpl_media()
    {
        global $DEL, $NS, $IMG, $AUTH, $JUMPTO, $REV, $lang, $fullscreen, $conf;
        $fullscreen = true;
        require_once DOKU_INC.'lib/exe/mediamanager.php';

        if ($_REQUEST['image']) $image = cleanID($_REQUEST['image']);
        if (isset($IMG)) $image = $IMG;
        if (isset($JUMPTO)) $image = $JUMPTO;
        if (isset($REV) && !$JUMPTO) $rev = $REV;

        echo '<div id="mediamanager__page">'.NL;
        echo '<h1>'.$lang['btn_media'].'</h1>'.NL;
        html_msgarea();

        echo '<div class="panel namespaces">'.NL;
        echo '<h2>'.$lang['namespaces'].'</h2>'.NL;
        echo '<div class="panelHeader">';
        echo $lang['media_namespaces'];
        echo '</div>'.NL;

        echo '<div class="panelContent" id="media__tree">'.NL;
        media_nstree($NS);
        echo '</div>'.NL;
        echo '</div>'.NL;

        echo '<div class="panel filelist">'.NL;
        $this->_mod_tpl_mediaFileList();
        echo '</div>'.NL;

        echo '<div class="panel file">'.NL;
        echo '<h2 class="a11y">'.$lang['media_file'].'</h2>'.NL;
        tpl_mediaFileDetails($image, $rev);
        echo '</div>'.NL;

        echo '</div>'.NL;
    }

    /**
     * Prints the central column in full-screen media manager
     *
     * @see tpl_mediaFileList()
     */
    function _mod_tpl_mediaFileList()
    {
        global $AUTH;
        global $NS;
        global $JUMPTO;
        global $lang;

        $opened_tab = $_REQUEST['tab_files'];
        if (!$opened_tab || !in_array($opened_tab, array('files', 'upload', 'search'))) $opened_tab = 'files';
        if ($_REQUEST['mediado'] == 'update') $opened_tab = 'upload';

        echo '<h2 class="a11y">' . $lang['mediaselect'] . '</h2>'.NL;

        media_tabs_files($opened_tab);

        echo '<div class="panelHeader">'.NL;
        echo '<h3>';
        $tabTitle = ($NS) ? $NS : '['.$lang['mediaroot'].']';
        printf($lang['media_' . $opened_tab], '<strong>'.hsc($tabTitle).'</strong>');
        echo '</h3>'.NL;
        if ($opened_tab === 'search' || $opened_tab === 'files') {
            media_tab_files_options();
        }
        echo '</div>'.NL;

        echo '<div class="panelContent">'.NL;
        if ($opened_tab == 'files') {
            $this->_mod_media_tab_files($NS,$AUTH,$JUMPTO);
        } elseif ($opened_tab == 'upload') {
            media_tab_upload($NS,$AUTH,$JUMPTO);
        } elseif ($opened_tab == 'search') {
            media_tab_search($NS,$AUTH);
        }
        echo '</div>'.NL;
    }

    /**
     * Prints tab that displays a list of all files
     *
     * @see media_tab_files()
     */
    function _mod_media_tab_files($ns,$auth=null,$jump='') {
        global $lang;
        if(is_null($auth)) $auth = auth_quickaclcheck("$ns:*");

        if($auth < AUTH_READ){
            echo '<div class="nothing">'.$lang['media_perm_read'].'</div>'.NL;
        }else{
            $this->_mod_media_filelist($ns,$auth,$jump,true,_media_get_sort_type());
        }
    }

    /**
     * List all files in a given Media namespace
     *
     * @see media_filelist()
     */
    function _mod_media_filelist($ns,$auth=null,$jump='',$fullscreenview=false,$sort=false){
        global $conf;
        global $lang;
        $ns = cleanID($ns);

        // check auth our self if not given (needed for ajax calls)
        if(is_null($auth)) $auth = auth_quickaclcheck("$ns:*");

        if (!$fullscreenview) echo '<h1 id="media__ns">:'.hsc($ns).'</h1>'.NL;

        if($auth < AUTH_READ){
            // FIXME: print permission warning here instead?
            echo '<div class="nothing">'.$lang['nothingfound'].'</div>'.NL;
        }else{
            if (!$fullscreenview) media_uploadform($ns, $auth);

            $dir = utf8_encodeFN(str_replace(':','/',$ns));
            $data = array();
            search($data,$conf['mediadir'],'search_media',
                    array('showmsg'=>true,'depth'=>1),$dir,1,$sort);

            if(!count($data)){
                echo '<div class="nothing">'.$lang['nothingfound'].'</div>'.NL;
            }else {
                if ($fullscreenview) {
                    echo '<ul class="' . _media_get_list_type() . '">';
                }
                foreach($data as $item){
                    if (!$fullscreenview) {
                        $this->_mod_media_printfile($item,$auth,$jump);
                    } else {
                        $this->_mod_media_printfile_thumbs($item,$auth,$jump);
                    }
                }
                if ($fullscreenview) echo '</ul>'.NL;
            }
        }
        if (!$fullscreenview) media_searchform($ns);
    }

    /**
     * Formats and prints one file in the list
     *
     * @see media_printfile()
     */
    function _mod_media_printfile($item,$auth,$jump,$display_namespace=false){
        global $lang;
        global $conf;

        // Prepare zebra coloring
        // I always wanted to use this variable name :-D
        static $twibble = 1;
        $twibble *= -1;
        $zebra = ($twibble == -1) ? 'odd' : 'even';

        // Automatically jump to recent action
        if($jump == $item['id']) {
            $jump = ' id="scroll__here" ';
        }else{
            $jump = '';
        }

        // Prepare fileicons
        list($ext,$mime,$dl) = mimetype($item['file'],false);
        $class = preg_replace('/[^_\-a-z0-9]+/i','_',$ext);
        $class = 'select mediafile mf_'.$class;

        // Prepare filename
        $file = $this->_getOriginalFileName($item['id']);

        if ($file === false) {
            $file = utf8_decodeFN($item['file']);
        }

        // build fake media id
        $ns = getNS($item['id']);
        $fakeId = $ns === false ? $file : "$ns:$file";
        $fakeId_escaped = hsc($fakeId);

        // Prepare info
        $info = '';
        if($item['isimg']){
            $info .= (int) $item['meta']->getField('File.Width');
            $info .= '&#215;';
            $info .= (int) $item['meta']->getField('File.Height');
            $info .= ' ';
        }
        $info .= '<i>'.dformat($item['mtime']).'</i>';
        $info .= ' ';
        $info .= filesize_h($item['size']);

        // output
        echo '<div class="'.$zebra.'"'.$jump.' title="'.$fakeId_escaped.'">'.NL;
        if (!$display_namespace) {
            echo '<a name="h_:'.$item['id'].'" class="'.$class.'">'.hsc($file).'</a> ';
        } else {
            echo '<a name="h_:'.$item['id'].'" class="'.$class.'">'.$fakeId_escaped.'</a><br/>';
        }
        echo '<span class="info">('.$info.')</span>'.NL;

        // view button
        $link = ml($fakeId,'',true);
        echo ' <a href="'.$link.'" target="_blank"><img src="'.DOKU_BASE.'lib/images/magnifier.png" '.
            'alt="'.$lang['mediaview'].'" title="'.$lang['mediaview'].'" class="btn" /></a>';

        // mediamanager button
        $link = wl('',array('do'=>'media','image'=>$fakeId,'ns'=>$ns));
        echo ' <a href="'.$link.'" target="_blank"><img src="'.DOKU_BASE.'lib/images/mediamanager.png" '.
            'alt="'.$lang['btn_media'].'" title="'.$lang['btn_media'].'" class="btn" /></a>';

        // delete button
        if($item['writable'] && $auth >= AUTH_DELETE){
            $link = DOKU_BASE.'lib/exe/mediamanager.php?delete='.rawurlencode($fakeId).
                '&amp;sectok='.getSecurityToken();
            echo ' <a href="'.$link.'" class="btn_media_delete" title="'.$fakeId_escaped.'">'.
                '<img src="'.DOKU_BASE.'lib/images/trash.png" alt="'.$lang['btn_delete'].'" '.
                'title="'.$lang['btn_delete'].'" class="btn" /></a>';
        }

        echo '<div class="example" id="ex_'.str_replace(':','_',$item['id']).'">';
        echo $lang['mediausage'].' <code>{{:'.str_replace(array('{','}'),array('(',')'),$fakeId_escaped).'}}</code>';
        echo '</div>';
        if($item['isimg']) media_printimgdetail($item);
        echo '<div class="clearer"></div>'.NL;
        echo '</div>'.NL;
    }

    /**
     * Formats and prints one file in the list in the thumbnails view
     *
     * @see media_printfile_thumbs()
     */
    function _mod_media_printfile_thumbs($item,$auth,$jump=false,$display_namespace=false){
        global $lang;
        global $conf;

        // Prepare filename
        $file = $this->_getOriginalFileName($item['id']);

        if ($file === false) {
            $file = utf8_decodeFN($item['file']);
        }

        // build fake media id
        $ns = getNS($item['id']);
        $fakeId = $ns === false ? $file : "$ns:$file";
        $fakeId_escaped = hsc($fakeId);

        // output
        echo '<li><dl title="'.$fakeId_escaped.'">'.NL;

            echo '<dt>';
        if($item['isimg']) {
            media_printimgdetail($item, true);

        } else {
            echo '<a name="d_:'.$item['id'].'" class="image" title="'.$fakeId_escaped.'" href="'.
                media_managerURL(array('image' => $fakeId, 'ns' => $ns,
                'tab_details' => 'view')).'">';
            echo media_printicon($fakeId_escaped);
            echo '</a>';
        }
        echo '</dt>'.NL;
        if (!$display_namespace) {
            $name = hsc($file);
        } else {
            $name = $fakeId_escaped;
        }
        echo '<dd class="name"><a href="'.media_managerURL(array('image' => $fakeId, 'ns' => $ns,
            'tab_details' => 'view')).'" name="h_:'.$item['id'].'">'.$name.'</a></dd>'.NL;

        if($item['isimg']){
            $size = '';
            $size .= (int) $item['meta']->getField('File.Width');
            $size .= '&#215;';
            $size .= (int) $item['meta']->getField('File.Height');
            echo '<dd class="size">'.$size.'</dd>'.NL;
        } else {
            echo '<dd class="size">&#160;</dd>'.NL;
        }
        $date = dformat($item['mtime']);
        echo '<dd class="date">'.$date.'</dd>'.NL;
        $filesize = filesize_h($item['size']);
        echo '<dd class="filesize">'.$filesize.'</dd>'.NL;
        echo '</dl></li>'.NL;
    }
}
