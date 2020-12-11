jQuery(function () {
    if (jQuery('#wp-admin-bar-e-addons-edit-template-wrapper').length) {
        //console.log('Elementor here!');
        jQuery('#wp-admin-bar-e-addons-edit-template-wrapper').appendTo('#wp-admin-bar-e-addons-inspector');
        jQuery('#wp-admin-bar-elementor_inspector-default > li').appendTo('#wp-admin-bar-e-addons-inspector ul');
        jQuery('#wp-admin-bar-elementor_inspector').remove();

        jQuery('#wp-admin-bar-e-addons-edit-template-wrapper').css('visibility', 'visible');
    } else {
        //console.log('NO Elementor here!');
        jQuery('#wp-admin-bar-e-addons-inspector').remove();
    }
    
});