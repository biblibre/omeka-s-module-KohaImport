/**
 * Initially based on Omeka S omeka2importer.js and resource-core.js.
 */
(function ($) {

    $(document).ready(function () {

        $('.resource_fieldsets').hide();
        activeFirstTab($('.section-nav'));

        function activeFirstTab(sectionNav) {
            var firstTab = $(sectionNav).find('ul li:first-child');
            firstTab.addClass('active');
            showCorrespondingFieldset(firstTab.children('a'));
        }

        $(document).on('click', '.resource_type', (function () {
            showCorrespondingFieldset($(this));
        }));

        function showCorrespondingFieldset(anchor) {
            $(anchor).parent().addClass('active');
            var id = $(anchor).attr('id');
            $('fieldset[name^="resource_fieldset_"]').each(function () {
                if ($(this).attr('name') === `resource_fieldset_${id}`) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    });

})(jQuery);
