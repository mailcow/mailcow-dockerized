// redirect to mailcow login form
document.addEventListener('DOMContentLoaded', function () {
    var loginForm = document.forms.namedItem("loginForm");
    if (loginForm) {
        window.location.href = '/';
    }

    angularReady = false;
    function observe() {
        angularReady = toolbarExists();
        if (angularReady && !mcElementsExists()) addMCElements();

        const observer = new MutationObserver(function(mutations) {
            if (!angularReady) {
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) angularReady = toolbarExists();
                });
            } else if (angularReady && !mcElementsExists()) {
                addMCElements();
            }
        });

        const targetNode = document.body;
        const config = { childList: true, subtree: true };
        observer.observe(targetNode, config);
    }
    function toolbarExists() {
        const toolbarElement = document.body.querySelector('md-toolbar');
        if (toolbarElement)
            return true;
        else
            return false;
    }
    function mcElementsExists() {
        if (document.getElementById("mc_logout"))
            return true;
        else 
            return false;
    }
    function addMCElements() {
        const toolbarElement = document.body.querySelector('.md-toolbar-tools.sg-toolbar-group-last.layout-align-end-center.layout-row');
        var htmlCode = '<a class="md-icon-button md-button md-ink-ripple" aria-label="mailcow" href="/user" aria-hidden="false" tabindex="-1">' +
            '<md-icon class="material-icons" role="img" aria-label="build">build</md-icon>' +
            '</a><a class="md-icon-button md-button md-ink-ripple" aria-label="mailcow" href="#" onclick="mc_logout.submit()" aria-hidden="false" tabindex="-1">' +
            '<md-icon class="material-icons" role="img" aria-label="settings_power">settings_power</md-icon>' +
            '</a><form action="/" method="post" id="mc_logout"><input type="hidden" name="logout"></form>';
        toolbarElement.insertAdjacentHTML('beforeend', htmlCode);
    }

    observe();
});

// Custom SOGo JS

// Change the visible font-size in the editor, this does not change the font of a html message by default
CKEDITOR.addCss("body {font-size: 16px !important}");

// Enable scayt by default
//CKEDITOR.config.scayt_autoStartup = true;

