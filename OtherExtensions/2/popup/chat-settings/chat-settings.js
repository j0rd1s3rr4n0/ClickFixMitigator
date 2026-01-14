(function () {
    initChatSettings();

    function initChatSettings() {
        $("#loading").addClass("d-none");
        chrome.storage.sync.get(["sms_token"], function (data) {
            if(data.sms_token) {
                $("#sms_token").val(data.sms_token);
            }
        });
        $("#save_sms_settings").click(function () {
            var sms_token = $("#sms_token").val();
            chrome.storage.sync.set({sms_token: sms_token});
        });

        $(".navigation-btn").click(function () {
            go('navigation');
        });
    }

    function go(to) {
        console.log(chrome.runtime.getURL(`popup/${to}/${to}.html`));
        location.href = chrome.runtime.getURL(`popup/${to}/${to}.html`);
    }

})();