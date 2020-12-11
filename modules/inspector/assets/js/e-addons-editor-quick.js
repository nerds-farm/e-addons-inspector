function e_get_url_param(parameter, fallback) {
    var urlparameter = fallback;
    var vars = {};
    var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function (m, key, value) {
        vars[key] = value;
    });
    if (window.location.href.indexOf(parameter) > -1) {
        urlparameter = vars[parameter];
    }
    return urlparameter;
}

jQuery(window).on('load', function () {
//jQuery(window).on('preview:loaded', function () {
    //console.log('load');
    if (jQuery('#elementor-preview-iframe').length) {
        var element = e_get_url_param('element');
        //console.log(element);        
        if (element) {
            var iFrame = jQuery("iframe#elementor-preview-iframe");
            var iFrameDOM = iFrame.contents();
            var thisTimeout = setInterval(function () {
                //console.log(thisTimeout);
                if (!jQuery('#elementor-loading:visible').length) {
                    //console.log('click');
                    iFrameDOM.find("div.elementor-element-" + element).trigger('click');
                    clearInterval(thisTimeout);
                }
            }, 1000);
        }
    }
});