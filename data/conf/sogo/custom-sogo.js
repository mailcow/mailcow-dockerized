// redirect to mailcow login form
document.addEventListener('DOMContentLoaded', function () {
    var loginForm = document.forms.namedItem("loginForm");
    if (loginForm) {
        window.location.href = '/user';
    }
});
// logout function
function mc_logout() {
    // top-level POST, so mailcow can redirect to the identity provider logout
    var form = document.createElement("form");
    form.method = "POST";
    form.action = "/";
    var input = document.createElement("input");
    input.type = "hidden";
    input.name = "logout";
    input.value = "1";
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// Custom SOGo JS

// Change the visible font-size in the editor, this does not change the font of a html message by default
CKEDITOR.addCss("body {font-size: 16px !important}");

// Enable scayt by default
//CKEDITOR.config.scayt_autoStartup = true;

