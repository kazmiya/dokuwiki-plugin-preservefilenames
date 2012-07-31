/**
 * PreserveFilenames Plugin for DokuWiki / script.js
 *
 * @author Kazutaka Miyasaka <kazmiya@gmail.com>
 */

(function() {
    // check DokuWiki version
    if (
        typeof DEPRECATED === 'function' ||
        typeof addInitEvent === 'undefined'
    ) {
        // DokuWiki Angua
        jQuery(_Angua);
    } else if (
        typeof JSINFO === 'object' &&
        JSINFO.plugin_preservefilenames
    ) {
        // DokuWiki Anteater or Rincewind
        addInitEvent(_Anteater);
    }

    /**
     * for DokuWiki Angua
     */
    function _Angua() {
        var update_content_orig,
            insert_orig;

        // replace built-in methods with plugin's
        if (typeof dw_mediamanager === 'object') {
            update_content_orig = dw_mediamanager.update_content;
            dw_mediamanager.update_content = mod_update_content;

            insert_orig = dw_mediamanager.insert;
            dw_mediamanager.insert = mod_insert;
        }

        if (
            typeof qq === 'object' &&
            typeof qq.FileUploaderExtended === 'function'
        ) {
            qq.FileUploaderExtended.prototype._addToList = mod_addToList;
        }

        /**
         * Changes ajax call parameter in media manager
         */
        function mod_update_content($content, params, update_list) {
            params = params.replace(
                /\bcall=(media(?:list))\&/,
                'call=preservefilenames_$1&'
            );

            update_content_orig($content, params, update_list);
        }

        /**
         * Changes file part of a media id into its original filename
         */
        function mod_insert(id) {
            var filename,
                matches;

            // retrieve original filename by DOM manipulation
            filename = jQuery('#media__content')
                .find('a[name="h_' + id + '"]:first')
                .text();

            if (
                filename !== '' &&
                (matches = id.match(/^((?::[^:]+)*:)[^:]+$/))
            ) {
                filename = filename.replace(/\{/g, '(').replace(/\}/g, ')');
                id = matches[1] + filename;
            }

            insert_orig(id);
        }

        /**
         * Fills input box with raw file name (without cleanID process)
         */
        function mod_addToList(id, fileName) {
            var item = qq.toElement(this._options.fileTemplate);
            item.qqFileId = id;

            var fileElement = this._find(item, 'file');
            qq.setText(fileElement, fileName);
            this._find(item, 'size').style.display = 'none';

            var nameElement = this._find(item, 'nameInput');
            nameElement.value = fileName;
            nameElement.id = 'mediamanager__upload_item' + id;

            this._listElement.appendChild(item);
        }
    }

    /**
     * for DokuWiki Anteater and Rincewind
     */
    function _Anteater() {
        if (
            !JSINFO ||
            !JSINFO.plugin_preservefilenames ||
            !JSINFO.plugin_preservefilenames.in_mediamanager
        ) {
            return;
        }

        JSINFO.plugin_preservefilenames.getFakeID = function(id) {
            var matched = id.match(/^(.*?:)[^:]+$/);
            if (!matched) return id;
            var namespace = matched[1];

            var content_div = $('media__content');
            if (!content_div) return id;

            var medialinks = getElementsByClass('mediafile', content_div, 'a');

            for (var i = 0, i_len = medialinks.length; i < i_len; i++) {
                if (!medialinks[i].name || medialinks[i].name != 'h_' + id) continue;
                var link = medialinks[i];
                break;
            }
            if (!link || !link.firstChild || !link.firstChild.nodeValue) return id;

            // replace characters which cannot be included in a link title
            var filename_orig = link.firstChild.nodeValue;
            filename_orig = filename_orig.replace(/\{/g, '(');
            filename_orig = filename_orig.replace(/\}/g, ')');

            // return namespace + filename_orig as a "fake id"
            return namespace + filename_orig;
        };

        // override media listing via ajax
        eval('media_manager.list = ' + media_manager.list.toString().replace(
            /&call=medialist/,
            '&call=medialist_preservefilenames'
        ));

        // override snippet insertion
        if (!!media_manager.select) { // DokuWiki 2009-12-25
            eval('media_manager.select = ' + media_manager.select.toString().replace(
                /\+\s*id\s*\+/,
                '+ JSINFO.plugin_preservefilenames.getFakeID(id) +'
            ));
        }

        if (!!media_manager.insert) { // DokuWiki dev@2010/03
            eval('media_manager.insert = ' + media_manager.insert.toString().replace(
                /\+\s*id\s*\+/,
                '+ JSINFO.plugin_preservefilenames.getFakeID(id) +'
            ));
        }
    }
})();
