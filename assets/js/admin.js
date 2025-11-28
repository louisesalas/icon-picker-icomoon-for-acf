/**
 * ACF IcoMoon Admin JavaScript
 *
 * Handles the admin UI interactions for icon selection and management.
 *
 * @package ACF_IcoMoon_Integration
 */

(function($) {
    'use strict';

    /**
     * IcoMoon Admin Handler
     */
    var ACFIcoMoon = {

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initACFField();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Settings page search
            $(document).on('input', '#acf-icomoon-search', this.handleSearch.bind(this));
            
            // Clear icons button
            $(document).on('click', '#acf-icomoon-clear', this.handleClearIcons.bind(this));
            
            // Icon item click on settings page
            $(document).on('click', '.acf-icomoon-icon-item', this.handleIconClick.bind(this));
        },

        /**
         * Initialize ACF field functionality
         */
        initACFField: function() {
            // When ACF is ready
            if (typeof acf !== 'undefined') {
                acf.addAction('ready', this.setupACFFields.bind(this));
                acf.addAction('append', this.setupACFFields.bind(this));
            }
        },

        /**
         * Setup ACF field interactions
         */
        setupACFFields: function($el) {
            var self = this;
            var $container = $el || $(document);

            // Toggle picker
            $container.find('.acf-icomoon-toggle').off('click').on('click', function(e) {
                e.preventDefault();
                var $wrap = $(this).closest('.acf-icomoon-field-wrap');
                $wrap.find('.acf-icomoon-picker').slideToggle(200);
            });

            // Search in picker
            $container.find('.acf-icomoon-search-input').off('input').on('input', function() {
                self.filterPickerIcons($(this));
            });

            // Select icon
            $container.find('.acf-icomoon-picker-item').off('click').on('click', function() {
                self.selectIcon($(this));
            });

            // Remove selected icon
            $container.find('.acf-icomoon-remove').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.removeIcon($(this));
            });

            // Clear selection
            $container.find('.acf-icomoon-clear-selection').off('click').on('click', function(e) {
                e.preventDefault();
                self.clearSelection($(this));
            });

            // Close picker
            $container.find('.acf-icomoon-close').off('click').on('click', function(e) {
                e.preventDefault();
                $(this).closest('.acf-icomoon-picker').slideUp(200);
            });
        },

        /**
         * Handle search on settings page
         */
        handleSearch: function(e) {
            var query = $(e.target).val().toLowerCase().trim();
            var $grid = $('#acf-icomoon-icons-grid');
            var $items = $grid.find('.acf-icomoon-icon-item');
            var $noResults = $('#acf-icomoon-no-results');
            var visibleCount = 0;

            $items.each(function() {
                var name = $(this).data('name').toLowerCase();
                var isMatch = name.indexOf(query) !== -1;
                
                $(this).toggle(isMatch);
                
                if (isMatch) {
                    visibleCount++;
                }
            });

            $noResults.toggle(visibleCount === 0);
            
            // Update count
            $('.acf-icomoon-count').text(visibleCount + ' icons');
        },

        /**
         * Handle clear icons button
         */
        handleClearIcons: function(e) {
            e.preventDefault();

            if (!confirm(acfIcoMoon.strings.confirmClear)) {
                return;
            }

            var $button = $(e.target);
            $button.prop('disabled', true).text(acfIcoMoon.strings.clearing);

            $.ajax({
                url: acfIcoMoon.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'acf_icomoon_clear_icons',
                    nonce: acfIcoMoon.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || acfIcoMoon.strings.error);
                        $button.prop('disabled', false).text('Clear All Icons');
                    }
                },
                error: function() {
                    alert(acfIcoMoon.strings.error);
                    $button.prop('disabled', false).text('Clear All Icons');
                }
            });
        },

        /**
         * Handle icon click on settings page (copy to clipboard)
         */
        handleIconClick: function(e) {
            var $item = $(e.currentTarget);
            var name = $item.data('name');
            
            // Try to copy to clipboard
            if (navigator.clipboard) {
                navigator.clipboard.writeText(name).then(function() {
                    // Show feedback
                    var $name = $item.find('.acf-icomoon-icon-name');
                    var originalText = $name.text();
                    $name.text('Copied!');
                    
                    setTimeout(function() {
                        $name.text(originalText);
                    }, 1000);
                });
            }
        },

        /**
         * Filter icons in picker
         */
        filterPickerIcons: function($input) {
            var query = $input.val().toLowerCase().trim();
            var $wrap = $input.closest('.acf-icomoon-picker');
            var $items = $wrap.find('.acf-icomoon-picker-item');
            var $noResults = $wrap.find('.acf-icomoon-picker-no-results');
            var visibleCount = 0;

            $items.each(function() {
                var name = $(this).data('icon').toLowerCase();
                var isMatch = name.indexOf(query) !== -1;
                
                $(this).toggle(isMatch);
                
                if (isMatch) {
                    visibleCount++;
                }
            });

            $noResults.toggle(visibleCount === 0);
        },

        /**
         * Select an icon
         */
        selectIcon: function($item) {
            var $wrap = $item.closest('.acf-icomoon-field-wrap');
            var iconName = $item.data('icon');
            var isMultiple = $wrap.data('multiple') === 1;
            var spriteUrl = $wrap.data('sprite-url');

            if (!isMultiple) {
                // Single selection mode
                $wrap.find('.acf-icomoon-picker-item').removeClass('is-selected');
                $item.addClass('is-selected');

                // Update hidden input
                $wrap.find('.acf-icomoon-value').val(iconName);

                // Update preview
                var $selectedIcons = $wrap.find('.acf-icomoon-selected-icons');
                $selectedIcons.empty();
                
                if (iconName) {
                    $selectedIcons.append(this.createSelectedItem(iconName, spriteUrl));
                }

                // Update button text
                $wrap.find('.acf-icomoon-toggle').text(
                    iconName ? acfIcoMoon.strings.changeIcon : acfIcoMoon.strings.selectIcon
                );

            } else {
                // Multiple selection mode
                $item.toggleClass('is-selected');
                
                var $selectedIcons = $wrap.find('.acf-icomoon-selected-icons');
                var $existingInputs = $wrap.find('.acf-icomoon-value');
                
                if ($item.hasClass('is-selected')) {
                    // Add new hidden input
                    var inputName = $wrap.find('input[type="hidden"]').first().attr('name').replace('[]', '');
                    $wrap.append(
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', inputName + '[]')
                            .val(iconName)
                            .addClass('acf-icomoon-value')
                    );
                    
                    // Add preview
                    $selectedIcons.append(this.createSelectedItem(iconName, spriteUrl));
                } else {
                    // Remove hidden input and preview
                    $wrap.find('.acf-icomoon-value[value="' + iconName + '"]').remove();
                    $selectedIcons.find('[data-icon="' + iconName + '"]').remove();
                }
            }

            // Trigger ACF change
            this.triggerACFChange($wrap);
        },

        /**
         * Create selected item HTML
         */
        createSelectedItem: function(iconName, spriteUrl) {
            var $item = $('<span class="acf-icomoon-selected-item" data-icon="' + iconName + '">');
            
            if (spriteUrl) {
                $item.append(
                    '<svg class="icomoon-icon" aria-hidden="true">' +
                    '<use href="' + spriteUrl + '#icon-' + iconName + '"></use>' +
                    '</svg>'
                );
            }
            
            $item.append('<span class="acf-icomoon-selected-name">' + iconName + '</span>');
            $item.append('<button type="button" class="acf-icomoon-remove" title="Remove">&times;</button>');
            
            // Bind remove event
            var self = this;
            $item.find('.acf-icomoon-remove').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.removeIcon($(this));
            });
            
            return $item;
        },

        /**
         * Remove an icon
         */
        removeIcon: function($button) {
            var $item = $button.closest('.acf-icomoon-selected-item');
            var $wrap = $button.closest('.acf-icomoon-field-wrap');
            var iconName = $item.data('icon');
            var isMultiple = $wrap.data('multiple') === 1;

            // Remove from picker selection
            $wrap.find('.acf-icomoon-picker-item[data-icon="' + iconName + '"]').removeClass('is-selected');

            if (isMultiple) {
                // Remove specific hidden input
                $wrap.find('.acf-icomoon-value[value="' + iconName + '"]').remove();
            } else {
                // Clear hidden input
                $wrap.find('.acf-icomoon-value').val('');
                
                // Update button text
                $wrap.find('.acf-icomoon-toggle').text(acfIcoMoon.strings.selectIcon);
            }

            // Remove preview item
            $item.remove();

            // Trigger ACF change
            this.triggerACFChange($wrap);
        },

        /**
         * Clear all selections
         */
        clearSelection: function($button) {
            var $wrap = $button.closest('.acf-icomoon-field-wrap');
            var isMultiple = $wrap.data('multiple') === 1;

            // Clear picker selections
            $wrap.find('.acf-icomoon-picker-item').removeClass('is-selected');

            // Clear hidden inputs
            if (isMultiple) {
                $wrap.find('.acf-icomoon-value').remove();
            } else {
                $wrap.find('.acf-icomoon-value').val('');
            }

            // Clear preview
            $wrap.find('.acf-icomoon-selected-icons').empty();

            // Update button text
            $wrap.find('.acf-icomoon-toggle').text(acfIcoMoon.strings.selectIcon);

            // Trigger ACF change
            this.triggerACFChange($wrap);
        },

        /**
         * Trigger ACF change event
         */
        triggerACFChange: function($wrap) {
            var $input = $wrap.find('input[type="hidden"]').first();
            $input.trigger('change');
            
            // Trigger ACF validation
            if (typeof acf !== 'undefined') {
                acf.doAction('change', $input);
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        ACFIcoMoon.init();
    });

})(jQuery);

