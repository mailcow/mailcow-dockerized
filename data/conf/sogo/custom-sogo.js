// redirect to mailcow login form
document.addEventListener('DOMContentLoaded', function () {
    var loginForm = document.forms.namedItem("loginForm");
    if (loginForm) {
        window.location.href = '/user';
    }
});
// logout function
var mc_logout_submitted = false;
function mc_logout() {
    // Guard against a double click/double invocation submitting two
    // competing navigations, which can cause the browser to abort the
    // first one (net::ERR_ABORTED) before it reaches the identity
    // provider's logout endpoint.
    if (mc_logout_submitted) {
        return;
    }
    mc_logout_submitted = true;
    // Submit a real form (instead of fetch()) so the browser performs a
    // genuine top-level navigation. This is required for the logout to
    // correctly follow a redirect chain that leaves mail.miolab.it (e.g.
    // Identity Provider Single Logout), which a fetch()-based redirect
    // cannot reliably follow due to cross-origin cookie restrictions.
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/';
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'logout';
    input.value = '1';
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}

// Custom SOGo JS

// Change the visible font-size in the editor, this does not change the font of a html message by default
CKEDITOR.addCss("body {font-size: 16px !important}");

// Enable scayt by default
//CKEDITOR.config.scayt_autoStartup = true;

