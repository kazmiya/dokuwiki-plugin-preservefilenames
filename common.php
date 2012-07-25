<?php
/**
 * DokuWiki Plugin PreserveFilenames / common.php
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Kazutaka Miyasaka <kazmiya@gmail.com>
 */

// must be run within DokuWiki
if (!defined('DOKU_INC')) {
    die();
}

class PreserveFilenames_Common
{
    /**
     * Returns a correct basename
     * (fixes PHP Bug #37738: basename() bug in handling multibyte filenames)
     * 
     * @see http://bugs.php.net/37738
     */
    function _correctBasename($path)
    {
        return rawurldecode(basename(preg_replace_callback(
            '/%(?:[013-7][0-9a-fA-F]|2[0-46-9a-fA-F])/', // ASCII except for '%'
            array(self, '_correctBasename_callback'),
            rawurlencode($path)
        )));
    }

    /**
     * Callback function for _correctBasename() (only does rawurldecode)
     */
    static function _correctBasename_callback($matches)
    {
        return rawurldecode($matches[0]);
    }

    /**
     * Returns a sanitized safe filename
     * 
     * @see http://en.wikipedia.org/wiki/Filename
     */
    function _sanitizeFileName($filename)
    {
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '',  $filename); // control
        $filename = preg_replace('/["*:<>?|\/\\\\]/', '_', $filename); // graphic
        $filename = preg_replace('/[#&]/', '_', $filename); // dw technical issues

        return $filename;
    }

    /**
     * Builds appropriate "Content-Disposition" header strings
     * 
     * @see http://greenbytes.de/tech/tc2231/
     */
    function _buildContentDispositionHeader($download, $filename, $no_pathinfo = false)
    {
        global $conf;

        $ua   = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $type = $download ? 'attachment' : 'inline';
        $ret  = "Content-Disposition: $type;";

        if (!preg_match('/[\x00-\x1F\x7F-\xFF"*:<>?|\/\\\\]/', $filename)) {
            // no problem with filename-safe ascii characters
            $ret .= ' filename="' . $filename . '";';
        } elseif (preg_match('/(?:Gecko\/|Opera\/| Opera )/', $ua)) {
            // use RFC2231 if accessed via RFC2231-compliant browser
            $ret .= " filename*=UTF-8''" . rawurlencode($filename) . ';';
        } elseif (
            strpos($filename, '"') === false
            && strpos($ua, 'Safari/') !== false
            && strpos($ua, 'Chrome/') === false
            && preg_match('/Version\/[4-9]/', $ua)
            && !preg_match('/Mac OS X 10_[1-4]_/', $ua)
        ) {
            // raw UTF-8 quoted-string
            $ret .= ' filename="' . $filename . '"';
        } elseif (
            !$no_pathinfo
            && $conf['useslash']
            && $conf['userewrite']
            && strpos($ua, 'Safari/') !== false
            && strpos($ua, 'Chrome/') === false
        ) {
            // leave filename-parm field empty
            // (browsers can retrieve a filename from pathinfo of its url)
        } else {
            // fallback to the DokuWiki default
            $ret .= ' filename="' . rawurlencode($filename) . '";';
        }

        return $ret;
    }
}
