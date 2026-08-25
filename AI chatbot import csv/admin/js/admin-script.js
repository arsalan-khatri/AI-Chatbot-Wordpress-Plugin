/**
 * WP AI Chatbot - Admin Dashboard Scripts
 */

jQuery(document).ready(function($) {
    'use strict';

    if ($('.wp-color-picker-field').length > 0) {
        $('.wp-color-picker-field').wpColorPicker();
    }

    const $providerSelect = $('select[name="wp_ai_chatbot_settings[api_provider]"]');
    const $openaiFields = $('input[name="wp_ai_chatbot_settings[openai_api_key]"], input[name="wp_ai_chatbot_settings[openai_model]"]').closest('tr');
    const $geminiFields = $('input[name="wp_ai_chatbot_settings[gemini_api_key]"], input[name="wp_ai_chatbot_settings[gemini_model]"]').closest('tr');

    function toggleApiProviderFields() {
        const selectedProvider = $providerSelect.val();
        if (selectedProvider === 'openai') {
            $openaiFields.slideDown(250); 
            $geminiFields.slideUp(250);
        } else if (selectedProvider === 'gemini') {
            $geminiFields.slideDown(250); 
            $openaiFields.slideUp(250);
        }
    }

    if ($providerSelect.length > 0) {
        toggleApiProviderFields();
        $providerSelect.on('change', function() { toggleApiProviderFields(); });
    }

    $('form').on('submit', function() {
        const $submitBtn = $(this).find('input[type="submit"]');
        if ($submitBtn.length > 0) { $submitBtn.val('Saving...').css('opacity', '0.7'); }
    });

    // ==========================================
    // MULTI-FORM BUILDER LOGIC
    // ==========================================
    
    // Add New Form Block
    let formIndex = $('#wp-ai-forms-container .wp-ai-form-block').length;
    
    $(document).on('click', '#add-ai-multi-form', function(e) {
        e.preventDefault();
        let fId = formIndex++;
        
        let formHTML = `
            <div class="postbox wp-ai-form-block" style="margin-bottom:20px; border:1px solid #ccd0d4;">
                <div class="postbox-header" style="display:flex; justify-content:space-between; padding:10px 15px; background:#f9f9f9; border-bottom:1px solid #eee;">
                    <input type="text" name="wp_ai_chatbot_multi_forms[${fId}][title]" placeholder="Form Title (e.g. Booking Form)" style="font-size:16px; font-weight:bold; width:60%;" required />
                    <button type="button" class="button remove-ai-form" style="color:red; border-color:red;">Delete Form</button>
                </div>
                <div class="inside" style="padding:15px;">
                    <p><strong>AI Trigger Intent (Instruction for AI):</strong><br>
                    <input type="text" name="wp_ai_chatbot_multi_forms[${fId}][intent]" placeholder="e.g. If user asks for an appointment" style="width:100%;" required /></p>
                    
                    <table class="wp-list-table widefat fixed striped wp-ai-fields-table" style="margin-bottom: 10px;">
                        <thead><tr><th style="width:25%;">Field Label</th><th style="width:20%;">Type</th><th style="width:35%;">Options</th><th style="width:10%;">Req</th><th style="width:10%;">Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                    <button type="button" class="button add-ai-field" data-form-id="${fId}">+ Add Field to this Form</button>
                </div>
            </div>
        `;
        $('#wp-ai-forms-container').append(formHTML);
    });

    // Remove Form Block
    $(document).on('click', '.remove-ai-form', function(e) {
        e.preventDefault();
        if(confirm('Are you sure you want to delete this entire form?')) {
            $(this).closest('.wp-ai-form-block').remove();
        }
    });

    // Add Field to Specific Form
    $(document).on('click', '.add-ai-field', function(e) {
        e.preventDefault();
        let fId = $(this).data('form-id');
        let $tbody = $(this).siblings('.wp-ai-fields-table').find('tbody');
        let fieldIdx = Math.floor(Math.random() * 10000); 

        let fieldHTML = `
            <tr>
                <td><input type="text" name="wp_ai_chatbot_multi_forms[${fId}][fields][${fieldIdx}][label]" placeholder="e.g. Your Name" style="width:100%;" required></td>
                <td>
                    <select class="ai-field-type-select" name="wp_ai_chatbot_multi_forms[${fId}][fields][${fieldIdx}][type]" style="width:100%;">
                        <option value="text">Text</option>
                        <option value="email">Email</option>
                        <option value="tel">Phone Number</option>
                        <option value="textarea">Textarea</option>
                        <option value="select">Dropdown</option>
                        <option value="multiselect">Multi-Select</option>
                    </select>
                </td>
                <td><input type="text" class="ai-field-options" name="wp_ai_chatbot_multi_forms[${fId}][fields][${fieldIdx}][options]" placeholder="opt1, opt2" style="width:100%; display:none;"></td>
                <td><input type="checkbox" name="wp_ai_chatbot_multi_forms[${fId}][fields][${fieldIdx}][required]" value="yes" checked></td>
                <td><button type="button" class="button remove-ai-field">X</button></td>
            </tr>
        `;
        $tbody.append(fieldHTML);
    });

    // Remove specific field
    $(document).on('click', '.remove-ai-field', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    // Show/Hide Options for Select / Multi-Select type
    $(document).on('change', '.ai-field-type-select', function() {
        const val = $(this).val();
        if (val === 'select' || val === 'multiselect') {
            $(this).closest('tr').find('.ai-field-options').show().attr('placeholder', 'opt1, opt2, opt3').attr('required', true);
        } else {
            $(this).closest('tr').find('.ai-field-options').hide().removeAttr('required').val('');
        }
    });
});