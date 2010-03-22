/**
 * PreserveFilenames Plugin for DokuWiki / script.js
 * 
 * @author Kazutaka Miyasaka <kazmiya@gmail.com>
 */

addInitEvent(function() {
    if (!JSINFO
            || !JSINFO.plugin_preservefilenames
            || !JSINFO.plugin_preservefilenames.in_mediamanager) return;

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
    eval('media_manager.insert = ' + media_manager.insert.toString().replace(
        /\+\s*id\s*\+/,
        '+ JSINFO.plugin_preservefilenames.getFakeID(id) +'
    ));
});
