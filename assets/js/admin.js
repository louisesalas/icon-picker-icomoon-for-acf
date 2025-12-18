/**
 * IPIACF Admin JavaScript
 *
 * Handles the admin UI interactions for icon selection and management.
 *
 * @package IPIACF
 */

(function($) {
    'use strict';

    /**
     * IPIACF Admin Handler
     */
    var IPIACF = {

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
            $(document).on('input', '#ipiacf-search', this.handleSearch.bind(this));
            
            // Clear icons button
            $(document).on('click', '#ipiacf-clear', this.handleClearIcons.bind(this));
            
            // Icon item click on settings page
            $(document).on('click', '.ipiacf-icon-item', this.handleIconClick.bind(this));
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
            $container.find('.ipiacf-toggle').off('click').on('click', function(e) {
                e.preventDefault();
                var $wrap = $(this).closest('.ipiacf-field-wrap');
                $wrap.find('.ipiacf-picker').slideToggle(200);
            });

            // Search in picker
            $container.find('.ipiacf-search-input').off('input').on('input', function() {
                self.filterPickerIcons($(this));
            });

            // Select icon
            $container.find('.ipiacf-picker-item').off('click').on('click', function() {
                self.selectIcon($(this));
            });

            // Remove selected icon
            $container.find('.ipiacf-remove').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.removeIcon($(this));
            });

            // Clear selection
            $container.find('.ipiacf-clear-selection').off('click').on('click', function(e) {
                e.preventDefault();
                self.clearSelection($(this));
            });

            // Close picker
            $container.find('.ipiacf-close').off('click').on('click', function(e) {
                e.preventDefault();
                $(this).closest('.ipiacf-picker').slideUp(200);
            });
        },

        /**
         * Handle search on settings page
         */
        handleSearch: function(e) {
            var query = $(e.target).val().toLowerCase().trim();
            var $grid = $('#ipiacf-icons-grid');
            var $items = $grid.find('.ipiacf-icon-item');
            var $noResults = $('#ipiacf-no-results');
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
            $('.ipiacf-count').text(visibleCount + ' icons');
        },

        /**
         * Handle clear icons button
         */
        handleClearIcons: function(e) {
            e.preventDefault();

            if (!confirm(ipiacfData.strings.confirmClear)) {
                return;
            }

            var $button = $(e.target);
            $button.prop('disabled', true).text(ipiacfData.strings.clearing);

            $.ajax({
                url: ipiacfData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ipiacf_clear_icons',
                    nonce: ipiacfData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || ipiacfData.strings.error);
                        $button.prop('disabled', false).text('Clear All Icons');
                    }
                },
                error: function() {
                    alert(ipiacfData.strings.error);
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
                    var $name = $item.find('.ipiacf-icon-name');
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
            var $wrap = $input.closest('.ipiacf-picker');
            var $items = $wrap.find('.ipiacf-picker-item');
            var $noResults = $wrap.find('.ipiacf-picker-no-results');
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
            var $wrap = $item.closest('.ipiacf-field-wrap');
            var iconName = $item.data('icon');
            var isMultiple = $wrap.data('multiple') === 1;
            var spriteUrl = $wrap.data('sprite-url');

            if (!isMultiple) {
                // Single selection mode
                $wrap.find('.ipiacf-picker-item').removeClass('is-selected');
                $item.addClass('is-selected');

                // Update hidden input
                $wrap.find('.ipiacf-value').val(iconName);

                // Update preview
                var $selectedIcons = $wrap.find('.ipiacf-selected-icons');
                $selectedIcons.empty();
                
                if (iconName) {
                    $selectedIcons.append(this.createSelectedItem(iconName, spriteUrl));
                }

                // Update button text
                $wrap.find('.ipiacf-toggle').text(
                    iconName ? ipiacfData.strings.changeIcon : ipiacfData.strings.selectIcon
                );

            } else {
                // Multiple selection mode
                $item.toggleClass('is-selected');
                
                var $selectedIcons = $wrap.find('.ipiacf-selected-icons');
                var $existingInputs = $wrap.find('.ipiacf-value');
                
                if ($item.hasClass('is-selected')) {
                    // Add new hidden input
                    var inputName = $wrap.find('input[type="hidden"]').first().attr('name').replace('[]', '');
                    $wrap.append(
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', inputName + '[]')
                            .val(iconName)
                            .addClass('ipiacf-value')
                    );
                    
                    // Add preview
                    $selectedIcons.append(this.createSelectedItem(iconName, spriteUrl));
                } else {
                    // Remove hidden input and preview
                    $wrap.find('.ipiacf-value[value="' + iconName + '"]').remove();
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
            var $item = $('<span class="ipiacf-selected-item" data-icon="' + iconName + '">');
            
            if (spriteUrl) {
                $item.append(
                    '<svg class="icomoon-icon" aria-hidden="true">' +
                    '<use href="' + spriteUrl + '#icon-' + iconName + '"></use>' +
                    '</svg>'
                );
            }
            
            $item.append('<span class="ipiacf-selected-name">' + iconName + '</span>');
            $item.append('<button type="button" class="ipiacf-remove" title="Remove">&times;</button>');
            
            // Bind remove event
            var self = this;
            $item.find('.ipiacf-remove').on('click', function(e) {
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
            var $item = $button.closest('.ipiacf-selected-item');
            var $wrap = $button.closest('.ipiacf-field-wrap');
            var iconName = $item.data('icon');
            var isMultiple = $wrap.data('multiple') === 1;

            // Remove from picker selection
            $wrap.find('.ipiacf-picker-item[data-icon="' + iconName + '"]').removeClass('is-selected');

            if (isMultiple) {
                // Remove specific hidden input
                $wrap.find('.ipiacf-value[value="' + iconName + '"]').remove();
            } else {
                // Clear hidden input
                $wrap.find('.ipiacf-value').val('');
                
                // Update button text
                $wrap.find('.ipiacf-toggle').text(ipiacfData.strings.selectIcon);
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
            var $wrap = $button.closest('.ipiacf-field-wrap');
            var isMultiple = $wrap.data('multiple') === 1;

            // Clear picker selections
            $wrap.find('.ipiacf-picker-item').removeClass('is-selected');

            // Clear hidden inputs
            if (isMultiple) {
                $wrap.find('.ipiacf-value').remove();
            } else {
                $wrap.find('.ipiacf-value').val('');
            }

            // Clear preview
            $wrap.find('.ipiacf-selected-icons').empty();

            // Update button text
            $wrap.find('.ipiacf-toggle').text(ipiacfData.strings.selectIcon);

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
        IPIACF.init();
    });

})(jQuery);
