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
                    go('navigation');
                } else {
                    initLogin();
                }
            });
    }

    function initLogin() {
        $("#loading").addClass("d-none");
        $(".login-form").submit(e => {
            e.preventDefault();
            login();
        });
    }

    function login() {
        $("#loading").removeClass("d-none");
        $("#wrong-license").addClass("d-none");

        let token = $("#license").val();
        $api.post(`/accounts/validate-license?license=${token}`)
            .then(res => {
                if(res.data.success) {
                    saveToken(token, function () {
                        go('navigation');
                    });
                } else {
                    $("#wrong-license").removeClass("d-none");
                    $("#loading").addClass("d-none");
                }
            })
    }

    function saveToken(token, cb) {
        chrome.storage.sync.set({token: token}, cb);
    }

    function go(to) {
        location.href = chrome.runtime.getURL(`popup/${to}/${to}.html`);
    }

})();