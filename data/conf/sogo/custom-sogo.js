// redirect to mailcow login form
document.addEventListener('DOMContentLoaded', function () {
    var loginForm = document.forms.namedItem("loginForm");
    if (loginForm) {
        window.location.href = '/user';
    }
});
// logout function
function mc_logout() {
    // Create and submit a logout form to trigger the logout process
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/';
    
    var logoutInput = document.createElement('input');
    logoutInput.type = 'hidden';
    logoutInput.name = 'logout';
    logoutInput.value = '1';
    
    form.appendChild(logoutInput);
    document.body.appendChild(form);
    form.submit();
}

// Custom SOGo JS

// Change the visible font-size in the editor, this does not change the font of a html message by default
CKEDITOR.addCss("body {font-size: 16px !important}");

// Enable scayt by default
//CKEDITOR.config.scayt_autoStartup = true;

