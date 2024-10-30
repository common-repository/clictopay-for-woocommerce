jQuery(document).ready(function($) {
    if (typeof failed_payment_url !== 'undefined') {
        window.location.href = failed_payment_url;
    } else if (typeof return_url !== 'undefined') {
        window.location.href = return_url;
    }
});
