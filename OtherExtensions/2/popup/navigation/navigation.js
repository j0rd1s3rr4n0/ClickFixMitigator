(function () {

    var $api = axios.create({
        baseURL: "https://klikfx.com/api"
    });

    initNavigation();

    function initNavigation() {
        $("#loading").addClass("d-none");

        $("#shortcuts").change(function () {
            chrome.storage.sync.set({shortcuts_enabled: $(this).prop("checked")});
        });

        $(".highlight-btn").click(function () {
            go('highlights');
        });

        $(".edit-shortcuts").click(function () {
            go('shortcuts');
        });

        $(".sms-chat-settings").click(function () {
            go('chat-settings');
        });

        $(".navigation-btn").click(function () {
            go('navigation');
        });

        $(".logout").click(function (e) {
            e.preventDefault();
            chrome.storage.sync.remove("token", function () {
                go("login");
            });
        });

        $(".sc-builder").click(() => {
            tabRequestBuilder();
        });


        chrome.storage.sync.get(["shortcuts_enabled"]).then(function (data) {
            var val = data.shortcuts_enabled || false;
            $("#shortcuts").prop("checked", val);
        });
    }

    function tabRequestBuilder() {
        tabRequestHighlightReset(function () {
            var query = { active: true, currentWindow: true };
            chrome.tabs.query(query, function (tabs) {
                var tab = tabs[0];
                chrome.tabs.sendMessage(tab.id, {method: 'shortcode-builder'});
            });
        });
    }

    function tabRequestHighlightReset(cb=null) {
        var query = { active: true, currentWindow: true };
        chrome.tabs.query(query, function (tabs) {
            var tab = tabs[0];
            chrome.tabs.sendMessage(tab.id, {method: 'reset'}, cb);
        });
    }

    function go(to) {
        location.href = chrome.runtime.getURL(`popup/${to}/${to}.html`);
    }
})();