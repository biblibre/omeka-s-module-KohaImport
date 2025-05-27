/**
 * Initially based on Omeka S omeka2importer.js and resource-core.js.
 */
(function ($) {

    $(document).ready(function () {

        var mediaIngesterInput = $('input[name="medias_ingester"]');
        var mediaPathInput = $('input[name="medias_path"]');

        mediaIngesterInput.on('click', function () {
            if ($(this).val() === 'bucket') {
                mediaPathInput.prop('disabled', true);
            } else if ($(this).val() === 'local') {
                mediaPathInput.prop('disabled', false);
            }
        });

    });

})(jQuery);