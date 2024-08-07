// redirect to mailcow login form
document.addEventListener('DOMContentLoaded', function () {
    var loginForm = document.forms.namedItem("loginForm");
    if (loginForm) {
        window.location.href = '/';
    }

    angularReady = false;
    // Wait for the Angular components to be initialized
    function waitForAngularComponents(callback) {
        const targetNode = document.body;

        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                const toolbarElement = document.body.querySelector('md-toolbar');
                if (toolbarElement) {
                observer.disconnect();
                callback();
                }
            }
            });
        });

        const config = { childList: true, subtree: true };
        observer.observe(targetNode, config);
    }

    // Usage
    waitForAngularComponents(function() {
        if (!angularReady){
            angularReady = true;

            const toolbarElement = document.body.querySelector('.md-toolbar-tools.sg-toolbar-group-last.layout-align-end-center.layout-row');

            var htmlCode = '<a class="md-icon-button md-button md-ink-ripple" aria-label="mailcow" href="/user" aria-hidden="false" tabindex="-1">' +
                '<md-icon class="material-icons" role="img" aria-label="build">build</md-icon>' +
                '</a><a class="md-icon-button md-button md-ink-ripple" aria-label="mailcow" href="#" onclick="logout.submit()" aria-hidden="false" tabindex="-1">' +
                '<md-icon class="material-icons" role="img" aria-label="settings_power">settings_power</md-icon>' +
                '</a><form action="/" method="post" id="logout"><input type="hidden" name="logout"></form>';

            toolbarElement.insertAdjacentHTML('beforeend', htmlCode);
        }
    });
});

// Custom SOGo JS

// Change the visible font-size in the editor, this does not change the font of a html message by default
CKEDITOR.addCss("body {font-size: 16px !important}");

// Enable scayt by default
//CKEDITOR.config.scayt_autoStartup = true;

