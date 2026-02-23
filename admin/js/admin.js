/**
 * Hesabe WooCommerce Admin JavaScript
 * 
 * Handles dynamic behavior on the admin settings page
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize on page load
        initEnvironmentToggle();
        initDefaultCredentialsToggle();
        initDirectPaymentToggle();
        
        // Re-initialize when form fields change
        $(document).on('change', '#woocommerce_hesabe_environment_mode', initEnvironmentToggle);
        $(document).on('change', '#woocommerce_hesabe_use_default_staging', initDefaultCredentialsToggle);
        $(document).on('change', '#woocommerce_hesabe_pay_on_hesabe', initDirectPaymentToggle);
    });

    /**
     * Initialize environment mode toggle
     * Shows/hides staging and live credential sections
     */
    function initEnvironmentToggle() {
        var $body = $('body');
        var $envSelect = $('#woocommerce_hesabe_environment_mode');
        
        if ($envSelect.length === 0) {
            // Try alternative selector (WooCommerce may use different structure)
            $envSelect = $('select[name="woocommerce_hesabe_environment_mode"]');
        }
        
        if ($envSelect.length === 0) {
            // Try table-based selector
            $envSelect = $('table.form-table select[name*="environment_mode"]');
        }
        
        if ($envSelect.length > 0) {
            var envMode = $envSelect.val();
            
            // Remove existing classes
            $body.removeClass('hesabe-staging-mode hesabe-live-mode');
            
            // Add appropriate class
            if (envMode === 'staging') {
                $body.addClass('hesabe-staging-mode');
            } else if (envMode === 'live') {
                $body.addClass('hesabe-live-mode');
                
                // Show confirmation when switching to live mode
                if (!$body.data('hesabe-live-warned')) {
                    if (confirm('You are switching to LIVE mode. Make sure you have entered your production credentials correctly. Continue?')) {
                        $body.data('hesabe-live-warned', true);
                    } else {
                        $envSelect.val('staging');
                        $body.addClass('hesabe-staging-mode');
                        $body.removeClass('hesabe-live-mode');
                        return;
                    }
                }
            }
        } else {
            // Fallback: check all select elements for environment mode
            $('select').each(function() {
                var $select = $(this);
                var name = $select.attr('name') || '';
                var id = $select.attr('id') || '';
                
                if (name.indexOf('environment_mode') !== -1 || id.indexOf('environment_mode') !== -1) {
                    var envMode = $select.val();
                    $body.removeClass('hesabe-staging-mode hesabe-live-mode');
                    
                    if (envMode === 'staging') {
                        $body.addClass('hesabe-staging-mode');
                    } else if (envMode === 'live') {
                        $body.addClass('hesabe-live-mode');
                    }
                }
            });
        }
    }

    /**
     * Initialize default credentials toggle
     * Shows/hides custom staging credential fields
     */
    function initDefaultCredentialsToggle() {
        var $body = $('body');
        var $defaultCheckbox = $('#woocommerce_hesabe_use_default_staging');
        
        if ($defaultCheckbox.length === 0) {
            // Try alternative selectors
            $defaultCheckbox = $('input[name="woocommerce_hesabe_use_default_staging"]');
        }
        
        if ($defaultCheckbox.length === 0) {
            // Try table-based selector
            $defaultCheckbox = $('table.form-table input[name*="use_default_staging"]');
        }
        
        if ($defaultCheckbox.length > 0) {
            var useDefault = $defaultCheckbox.is(':checked');
            
            // Remove existing class
            $body.removeClass('hesabe-custom-staging-enabled');
            
            // Show custom fields if not using defaults
            if (!useDefault) {
                $body.addClass('hesabe-custom-staging-enabled');
            }
        } else {
            // Fallback: check all checkboxes
            $('input[type="checkbox"]').each(function() {
                var $checkbox = $(this);
                var name = $checkbox.attr('name') || '';
                var id = $checkbox.attr('id') || '';
                
                if (name.indexOf('use_default_staging') !== -1 || id.indexOf('use_default_staging') !== -1) {
                    var useDefault = $checkbox.is(':checked');
                    $body.removeClass('hesabe-custom-staging-enabled');
                    
                    if (!useDefault) {
                        $body.addClass('hesabe-custom-staging-enabled');
                    }
                }
            });
        }
    }

    /**
     * Initialize direct payment toggle
     * Shows/hides individual payment method options
     */
    function initDirectPaymentToggle() {
        var $body = $('body');
        var $payOnHesabeCheckbox = $('#woocommerce_hesabe_pay_on_hesabe');
        
        if ($payOnHesabeCheckbox.length === 0) {
            $payOnHesabeCheckbox = $('input[name="woocommerce_hesabe_pay_on_hesabe"]');
        }
        
        // Payment method rows
        var $methodRows = $('tr:has(#woocommerce_hesabe_direct_knet), tr:has(#woocommerce_hesabe_direct_visa_mastercard), tr:has(#woocommerce_hesabe_direct_amex), tr:has(#woocommerce_hesabe_direct_applepay), tr:has(#woocommerce_hesabe_direct_applepay_knet)');
        var $methodInputs = $('#woocommerce_hesabe_direct_knet, #woocommerce_hesabe_direct_visa_mastercard, #woocommerce_hesabe_direct_amex, #woocommerce_hesabe_direct_applepay, #woocommerce_hesabe_direct_applepay_knet');
        
        if ($payOnHesabeCheckbox.length > 0) {
            var payOnHesabeEnabled = $payOnHesabeCheckbox.is(':checked');
            var directEnabled = !payOnHesabeEnabled;
            
            // Remove existing class
            $body.removeClass('hesabe-direct-enabled');
            
            // Show payment method options if direct is enabled
            if (directEnabled) {
                $body.addClass('hesabe-direct-enabled');
            }

            if (payOnHesabeEnabled) {
                // Disable and uncheck all method toggles
                $methodInputs.prop('checked', false).prop('disabled', true);
                $methodRows.css('opacity', 0.6);
            } else {
                // Enable method toggles
                $methodInputs.prop('disabled', false);
                $methodRows.css('opacity', 1);
            }
        } else {
            // Fallback: check all checkboxes
            $('input[type="checkbox"]').each(function() {
                var $checkbox = $(this);
                var name = $checkbox.attr('name') || '';
                var id = $checkbox.attr('id') || '';
                
                // No-op fallback (legacy direct removed)
            });
        }
    }

    /**
     * Enhanced selector helper for WooCommerce settings
     * WooCommerce uses dynamic IDs, so we need flexible selectors
     */
    function findField(partialName) {
        // Try multiple selector strategies
        var selectors = [
            '#woocommerce_hesabe_' + partialName,
            'input[name="woocommerce_hesabe_' + partialName + '"]',
            'select[name="woocommerce_hesabe_' + partialName + '"]',
            'table.form-table input[name*="' + partialName + '"]',
            'table.form-table select[name*="' + partialName + '"]'
        ];
        
        for (var i = 0; i < selectors.length; i++) {
            var $field = $(selectors[i]);
            if ($field.length > 0) {
                return $field;
            }
        }
        
        return $();
    }

})(jQuery);

