(async function () {
    const default_shortcuts = await fetch(chrome.runtime.getURL("popup/shortcuts/defaults.json"))
        .then(res => res.json());

    initShortcuts();
    function initShortcuts() {
        $("#loading").addClass("d-none");

        chrome.storage.sync.get(["shortcuts_enabled"]).then(function (data) {
            var val = data.shortcuts_enabled || false;
            $("#shortcuts").prop("checked", val);
        });

        chrome.storage.sync.get(["shortcuts","sms_token"]).then(function (data) {
            const shortcuts = data.shortcuts || default_shortcuts;
            if(shortcuts) {
                $.each(shortcuts, function (i, shortcut) {
                    $("#"+shortcut.name).val(shortcut.shortcut);
                });
            }

            if(data.sms_token) {
                $("#sms_token").val(data.sms_token);
            }
        });

        $("#shortcuts").change(function () {
            chrome.storage.sync.set({shortcuts_enabled: $(this).prop("checked")});
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

        $(".navigation-btn").click(function () {
            go('navigation');
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

    function go(to) {
        console.log(chrome.runtime.getURL(`popup/${to}/${to}.html`));
        location.href = chrome.runtime.getURL(`popup/${to}/${to}.html`);
    }

})();