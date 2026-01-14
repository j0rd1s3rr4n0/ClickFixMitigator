(function () {

    var $api = axios.create({
        baseURL: "https://klikfx.com/api"
    });

    chrome.storage.sync.get("token", function (data) {
        initPopup(data.token);
    });

    function initPopup(token) {
        if(!token) {
            initLogin();
            return;
        }
        $api.get(`/accounts/validate-license?license=${token}`)
            .then(res => {
                if(res.data.success) {
                    initMain();
                } else {
                    initLogin();
                }
            });
    }

    function initLogin() {
        $("#loading").addClass("d-none");
        show_page("login");

        $(".login-form").submit(e => {
            e.preventDefault();
            login();
        })
    }

    function initMain() {
        $("#loading").addClass("d-none");
        show_page("navigation");

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
            var val = data.shortcuts_enabled || false;
            $("#shortcuts").prop("checked", val);
        });

        chrome.storage.sync.get(["shortcuts","sms_token"], function (data) {
            if(data.shortcuts) {
                $.each(data.shortcuts, function (i, shortcut) {
                    $("#"+shortcut.name).val(shortcut.shortcut);
                })
            }

            if(data.sms_token) {
                $("#sms_token").val(data.sms_token);
            }
        });

        $("#shortcuts").change(function () {
            chrome.storage.sync.set({shortcuts_enabled: $(this).prop("checked")});
        });

        $(".highlight-btn").click(function () {
            show_page('highlights');
        });

        $(".edit-shortcuts").click(function () {
            show_page('shortcuts');
        });

        $(".sms-chat-settings").click(function () {
            show_page('sms_chat_settings');
        });

        $(".navigation-btn").click(function () {
            show_page('navigation');
        });

        var combinationKeys = ['Control','Shift','Alt'];

        $(".shortcut-input").keydown(function(e) {
            e.preventDefault();
            if(combinationKeys.indexOf(e.key) !== -1) return;
            let keys = [];
            if(e.ctrlKey) {
                keys.push("CTRL");
            }
            if(e.altKey) {
                keys.push("ALT");
            }
            if(e.shiftKey) {
                keys.push("SHIFT");
            }
            keys.push(e.key.toUpperCase());

            $(this).val(keys.join("+"));
            save_shortcuts();
        });

        $(".clear-shortcut").click(function (e) {
            e.preventDefault();
            let target = $(this).data("target");
            $("#"+target).val("");
            save_shortcuts();
        });

        $(".logout").click(function (e) {
            e.preventDefault();
            chrome.storage.sync.remove("token", function () {
                show_page("login");
            });
        });

        $("#save_sms_settings").click(function () {
            var sms_token = $("#sms_token").val();
            chrome.storage.sync.set({sms_token: sms_token});
        });

        $(".sc-builder").click(() => {
            tabRequestBuilder();
        });
    }

    function save_shortcuts() {
        let shortcuts = [];
        $(".shortcut-input").each(function (i, shortcut) {
            shortcuts.push({
                name: shortcut.id,
                shortcut: shortcut.value
            });
        });
        chrome.storage.sync.set({shortcuts: shortcuts});
    }

    function tabRequestBuilder() {
        tabRequestHighlightReset(function () {
            var query = { active: true, currentWindow: true };
            // key = typeof key === "string" ? key : key.val();
            chrome.tabs.query(query, function (tabs) {
                var tab = tabs[0];
                chrome.tabs.sendMessage(tab.id, {method: 'shortcode-builder'});
            });
        });
    }

    function tabRequestHighlight(selector, key) {
        tabRequestHighlightReset(function () {
            var query = { active: true, currentWindow: true };
            // key = typeof key === "string" ? key : key.val();
            chrome.tabs.query(query, function (tabs) {
                var tab = tabs[0];
                chrome.tabs.sendMessage(tab.id, {method: 'display', selector: selector, attr: key});
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

    function login() {
        $("#loading").removeClass("d-none");
        $("#wrong-license").addClass("d-none");

        let token = $("#license").val();
        $api.post(`/accounts/validate-license?license=${token}`)
            .then(res => {
                if(res.data.success) {
                    save_shortcuts();
                    saveToken(token, function () {
                        initMain();
                    });
                } else {
                    $("#wrong-license").removeClass("d-none");
                    $("#loading").addClass("d-none");
                }
            })
    }

    function show_page(page) {
        $(".e-page").addClass("d-none");
        $("."+page).removeClass("d-none");
    }

    function saveToken(token, cb) {
        chrome.storage.sync.set({token: token}, cb);
    }

})();