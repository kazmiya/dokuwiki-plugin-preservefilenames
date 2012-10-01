<?php
/**
 * DokuWiki Plugin PreserveFilenames / action_adorabelle.php
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
require_once(DOKU_PLUGIN . 'preservefilenames/action_angua.php');

class action_plugin_preservefilenames_adorabelle extends action_plugin_preservefilenames_angua
{
    // -------------------------------------------------------
    // The following methods whose name starts with '_mod' are
    // slightly modified versions of existing functions.
    // -------------------------------------------------------

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
            echo '<a id="h_:'.$item['id'].'" class="'.$class.'">'.hsc($file).'</a> ';
        } else {
            echo '<a id="h_:'.$item['id'].'" class="'.$class.'">'.$fakeId_escaped.'</a><br/>';
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
            echo '<a id="d_:'.$item['id'].'" class="image" title="'.$fakeId_escaped.'" href="'.
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
            'tab_details' => 'view')).'" id="h_:'.$item['id'].'">'.$name.'</a></dd>'.NL;

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
