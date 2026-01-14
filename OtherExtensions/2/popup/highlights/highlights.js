(function () {
    initMain();

    function initMain() {
        $("#loading").addClass("d-none");

        let form_selectors = "input:not([type=button]):not([type=submit]):not([type=reset]),textarea,select";
        let block_selectors = "[opt-type='block-v3']";
        let col_selectors = ".col[opt-id]";
        let bump_selectors = ".opt-order-bump";
        let el_selectors = ".opt-element[opt-id]";

        $("#display_ids").click(tabRequestHighlight.bind(null,form_selectors,"id"));
        $("#display_names").click(tabRequestHighlight.bind(null,form_selectors,"name"));
        // $("#display_custom_attr").click(tabRequestHighlight.bind(null,form_selectors,$("#custom_attr")));
        $("#display_block_ids").click(tabRequestHighlight.bind(null,block_selectors,'id'));
        $("#display_column_ids").click(tabRequestHighlight.bind(null,col_selectors,'opt-id'));
        $("#display_element_ids").click(tabRequestHighlight.bind(null,el_selectors,'opt-id'));
        $("#display_bumps").click(tabRequestHighlight.bind(null,bump_selectors,"opt-id"));
        $("#reset").click(tabRequestHighlightReset.bind(null,null));

        chrome.storage.sync.get("shortcuts_enabled", function (data) {
            const val = data.shortcuts_enabled || false;
            $("#shortcuts").prop("checked", val);
        });

        chrome.storage.sync.get(["shortcuts","sms_token"], function (data) {
            if(data.shortcuts) {
                $.each(data.shortcuts, function (i, shortcut) {
                    $("#"+shortcut.name).val(shortcut.shortcut);
                });
            }

            if(data.sms_token) {
                $("#sms_token").val(data.sms_token);
            }
        });

        $(".navigation-btn").click(function () {
            go('navigation');
        });

    }


    function tabRequestHighlight(selector, key) {
        tabRequestHighlightReset(function () {
            const query = {active: true, currentWindow: true};
            chrome.tabs.query(query, function (tabs) {
                const tab = tabs[0];
                chrome.tabs.sendMessage(tab.id, {method: 'display', selector: selector, attr: key});
            });
        });
    }

    function tabRequestHighlightReset(cb=null) {
        const query = {active: true, currentWindow: true};
        chrome.tabs.query(query, function (tabs) {
            console.log("Tags");
            const tab = tabs[0];
            chrome.tabs.sendMessage(tab.id, {method: 'reset'}, cb);
        });
    }

    function go(to) {
        location.href = chrome.runtime.getURL(`popup/${to}/${to}.html`);
    }


})();