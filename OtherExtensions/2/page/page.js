chrome.storage.sync.get(['shortcuts','shortcuts_enabled','sms_token'], async function (data) {
    if(!data.shortcuts_enabled) return;


    const shortcut_actions = {
        shortcut_save: function () {
            document.querySelector("[data-callback='save']").click();
        },
        shortcut_open_clone: function () {
            window.open(location.href, "_blank");
        },
        shortcut_back: function () {
            const back = document.querySelector(".ontraport_panes__primary-header__back");
            if (back) back.click();
        },
        shortcut_search: function () {
            const search_btn = document.querySelector(".search_toggle_target");
            const search_input = document.querySelector(".ussr-component-search-options-input-wrapper input");
            if (search_btn) search_btn.click();
            if (search_input) search_input.focus();
        }
    };

    const _scs = data.shortcuts || await fetch(chrome.runtime.getURL("popup/shortcuts/defaults.json"))
        .then(res => res.json());

    const shortcuts = parse_shortcuts(_scs);
    window.addEventListener("keydown", function (e) {
        console.log(e);
        if(location.origin.toLowerCase().indexOf("ontraport.com") !== -1) {
            console.log("Processing key down", e);
            shortcut_event(e);
        }
    });

    let interval = setInterval(initFrameListener,300);

    function initFrameListener () {
        let editor_iframe = document.querySelector(".opt-editor__canvas:not(.visibility-hidden)");
        if(editor_iframe && editor_iframe.clientHeight !== 0) {
            if (location.origin.toLowerCase().indexOf("ontraport.com") !== -1 && editor_iframe) {
                editor_iframe.contentDocument.body.addEventListener("keydown", shortcut_event);
            }
            clearInterval(interval);
        }
    }

    function shortcut_event(ev) {
        const e = {
            key: ev.key ? ev.key.toLowerCase() : ev.key,
            ctrlKey: ev.ctrlKey,
            shiftKey: ev.shiftKey,
            altKey: ev.altKey
        };
        shortcuts_loop:
        for(let i = 0; i < shortcuts.length; i++) {
            const shortcut = shortcuts[i];
            const keys = Object.keys(shortcut.conditions);
            for(let i2 = 0; i2 < keys.length; i2++) {
                const key = keys[i2];
                if(e[key] !== shortcut.conditions[key]) {
                    continue shortcuts_loop;
                }
            }
            ev.preventDefault();
            shortcut.action();
            break;
        }
    }


    function parse_shortcuts(s) {
        const result = [];
        for(let i = 0; i < s.length; i++) {
            const shortcut = s[i];
            const keys = shortcut.shortcut.split("+");
            let sc = {};
            for(let j = 0; j < keys.length-1; j++) {
                sc[getKey(keys[j])] = true;
            }
            sc.key = keys[keys.length-1].toLowerCase();

            if(shortcut_actions[shortcut.name]) {
                result.push({
                    name: shortcut.name,
                    conditions: sc,
                    action: shortcut_actions[shortcut.name]
                });
            }
        }

        return result;
    }

    function getKey(k) {
        return k.toLowerCase()+"Key";
    }


});
let baseURL;
chrome.storage.sync.get(['sms_token'], function (data) {

    if(data.sms_token) {
        baseURL="https://dev.klikfx.com/sms/"+data.sms_token;
        findAnchors();
    }

    const sms_list = [];


    function findAnchors() {
        let links = document.querySelectorAll("a[href^='#sms.']");
        links = Array.from(links);
        const regExp = /#sms\.([a-zA-Z0-9]*)\S?$/i;
        links = links.map(link => {
            const matches = link.getAttribute("href").match(regExp);
            if(matches && matches.length > 1) {
                return {el: link,id: matches[1]};
            }
            return false;
        }).filter(link => !!link);

        initSMS(links);
    }

    let client;


    function initSMS(links) {
        const root = document.createElement("div");
        root.setAttribute("id","_clickfix_sms_root");
        document.body.appendChild(root);
        links.forEach(link => {
            link.el.addEventListener("click", function (e) {
                e.preventDefault();
                const chatWin = new ChatWindow(data, root, function () {
                    sms_list.splice(sms_list.indexOf(chatWin), 1);
                });
                const created = chatWin.startSMS(link.id);
                if(sms_list.length > 2 && created) {
                    sms_list[0].destroy();
                    sms_list.splice(0,1);
                }
                sms_list.push(chatWin);

                if(sms_list.length) {
                    initAbly();
                } else {
                    client.close();
                    client = null;
                }
            })
        })
    }


    function initAbly() {
        if(client) return;
        client = new Ably.Realtime('3f3wyw.Thc8Og:Pm-yBJMAPnnZ00o3');
        let key = "sms-"+data.sms_token;
        let channel = client.channels.get(key);

        channel.subscribe((message) => {
            if(message.name.toLowerCase()==="inbound") {
                sms_list.forEach(smsInstance => {
                    smsInstance.newMessage(JSON.parse(message.data), message.timestamp);
                });
            }
        });
    }

});



function ChatWindow(data, root, onCloseHandler) {
    const scopeData = {
        loading: true,
        canLoadMore: false,
        id: 0,
        firstname: '',
        lastname: ''
    };

    let messages = [];
    let template, $el;


    this.startSMS = function(id) {
        scopeData.id = id;

        if(document.querySelector("[data-clickfix-sms-id='"+id+"']")) return false;

        const chat = chrome.runtime.getURL("page/chat.html");

        fetchGet(chat, data => {
            $el = document.createElement("div");
            $el.setAttribute("data-clickfix-sms-id", id);
            template = Handlebars.template(data);
            $el.innerHTML = template({list: [], ...scopeData});
            root.appendChild($el);

            fetchGet(baseURL+"/info/"+id, (data) => {
                data = JSON.parse(data);
                scopeData.firstname = data.firstname;
                scopeData.lastname = data.lastname;

                loadMessages.call(this,id);
            });
        }, function () {
            console.log("ERROR");
        });



        // initAbly();

        return true;
    };

    this.destroy = function() {
        $el.parentNode.removeChild($el);
    };

    this.newMessage = function(msg, time) {
        if(parseInt(msg.contact_id) !== parseInt(scopeData.id)) return;

        const message = {
            id: msg.message.id,
            text: msg.text,
            type: 'inbound',
            unread: true,
            time
        };
        messages.push(message);

        const rootDiv = document.querySelector("._clickfix_sms_messages");
        let force = false;
        if(rootDiv.scrollHeight === rootDiv.clientHeight + rootDiv.scrollTop) {
            force = true;
        }
        renderMessages.call(this);
        force&&scrollMessages();
    };

    function loadMessages(id, offset) {
        if(!offset) offset=0;

        if(offset === 0) {
            scopeData.loading = true;
        }

        renderMessages.call(this);
        fetchGet(baseURL+"/"+id+"?offset="+offset, d => {
            const list = JSON.parse(d);
            messages = messages.concat(list);
            scopeData.loading = false;
            scopeData.canLoadMore = list.length === 50;
            renderMessages.call(this);
            if(!offset)
                scrollMessages();
        }, function () {
            console.log("ERROR");
        });
    }


    function sendMessage(text) {
        if(!text) return;

        const msg = {
            type: 'outbound',
            text,
            time: Date.now(),
            sending: true
        };

        messages.push(msg);

        const messagesDiv = document.querySelector("._clickfix_sms_messages");
        let force = false;
        if(messagesDiv.scrollHeight === messagesDiv.clientHeight + messagesDiv.scrollTop) {
            force = true;
        }

        renderMessages.call(this);
        force&&scrollMessages();

        fetchPost(baseURL+"/"+scopeData.id+"/send", {contact: scopeData.id, text: text}, function (result) {
            console.log(result);
            result = JSON.parse(result);
            if(result.success) {
                delete msg["sending"];
                renderMessages.call(this);
            }
        }, function (err) {
            console.log("ERROR");
        })
    }

    function renderMessages() {
        $el.innerHTML = template({list: messages, ...scopeData});
        const loadMoreBtn = $el.querySelector("._clickfix_sms_load_more a");
        const closeBtn = $el.querySelector("._clickfix_sms_close_chat");
        if(loadMoreBtn) {
            loadMoreBtn.addEventListener("click", (e) => {
                e.preventDefault();
                loadMessages.call(this,scopeData.id, messages.length);
            });
        }

        const header = $el.querySelector("._clickfix_sms_header");
        const chat = $el.querySelector("._clickfix_sms");
        const textarea = $el.querySelector("._clickfix_sms_form textarea");
        header.addEventListener("click", (e) => {
            chat.classList.toggle("minimized");
        });

        closeBtn.addEventListener("click", e => {
            e.stopPropagation();
            e.preventDefault();
            this.destroy();
            if(onCloseHandler) onCloseHandler();
        });


        textarea.addEventListener("keydown", (e) => {
            if(e.keyCode===13 && !(e.shiftKey || e.ctrlKey)) {
                e.preventDefault();
                sendMessage(e.currentTarget.value.trim());
                e.currentTarget.value = "";
            }
        });
    }

    function scrollMessages() {
        const block = $el.querySelector("._clickfix_sms_messages");
        block.scrollTop =  block.scrollHeight-block.clientHeight;
    }

}


chrome.runtime.onMessage.addListener(function (request, sender, response) {
    switch(request.method.toLowerCase()) {
        case 'display':
            display(request.selector, request.attr);
            break;
        case 'reset':
            reset();
            break;
    }
    response();
    return true;
});

function display(selector, attr) {
    let $els;
    $els = document.querySelectorAll(selector);
    for(let i = 0; i < $els.length; i++) {
        const $el = $els[i];
        showKey($el, findAttribute($el, attr), i);
    }
}

function reset() {
    const $els = document.querySelectorAll("[data-klikfx-uniq-id]");
    for(let i = 0; i < $els.length; i++) {
        const $el = $els[i];
        hideKey($el);
    }
}

function showKey($el, txt) {
    const scrollTop = document.querySelector("html").scrollTop || window.scrollTop || document.body.scrollTop;
    const scrollLeft = document.querySelector("html").scrollLeft || window.scrollLeft || document.body.scrollLeft;
    const rect = $el.getBoundingClientRect();
    const _id = $el.getAttribute("data-klikfx-uniq-id") || makeId(10);
    let overlay = document.querySelector("[data-klikfx-overlay='" + _id + "']");
    if(!overlay) {
        overlay = document.createElement("div");
        overlay.setAttribute("data-klikfx-overlay", _id);

        overlay.style.top = (scrollTop + rect.top) + "px";
        overlay.style.left = (scrollLeft + rect.left) + "px";
        if($el.getAttribute("type") && ($el.getAttribute("type").toLowerCase()==="checkbox" || $el.getAttribute("type").toLowerCase()==="radio" || $el.getAttribute("type").toLowerCase()==="range")) {
            overlay.style.minWidth = rect.width+"px";
            overlay.style.minHeight = rect.height+"px";
        } else {
            overlay.style.height = rect.height + "px";
            overlay.style.width = rect.width + "px";
        }

        if(!rect.height || !rect.width) {
            overlay.style.display = "none";
        }

        document.body.appendChild(overlay);
        const br = document.createElement("br");
        br.style.display = "none";
        document.body.appendChild(br);
        $el.setAttribute("data-klikfx-uniq-id", _id);

        overlay.setAttribute("data-clipboard-text", txt);
        if(txt) new ClipboardJS(overlay);
    }
    overlay.textContent = txt || "[Empty]";
}

function hideKey($el) {
    const id = $el.getAttribute("data-klikfx-uniq-id");
    if(id) {
        overlay = document.querySelector("[data-klikfx-overlay='"+id+"']");
        overlay.nextSibling.remove();
        overlay.remove();
        $el.removeAttribute("data-klikfx-uniq-id");
    }
}

function findAttribute($el, key) {
    if($el.classList.contains("select-dropdown") && $el.tagName.toLowerCase() === "input") {
        const select = $el.parentNode.querySelector("select");
        return select && select.getAttribute(key);
    }

    let quantityRegExp = /^quantity\[["'](.+?)["']]$/i;

    if(key.toLowerCase() === "id" && quantityRegExp.test($el.getAttribute("name"))) {
        let match = $el.getAttribute("name").match(quantityRegExp);
        return match[1];
    } else if(key.toLowerCase() === "name" && quantityRegExp.test($el.getAttribute("name"))) {
        let match = $el.getAttribute("name").match(quantityRegExp);
        return match[1];
    }


    return $el.getAttribute(key);
}

function makeId(length) {
    let text = "";
    const possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

    for (let i = 0; i < length; i++)
        text += possible.charAt(Math.floor(Math.random() * possible.length));

    return text+(new Date()).getTime();
}


function fetchGet(url, success, fail) {
    return _fetch("GET", url, undefined, success, fail);
}

function fetchPost(url, data, success, fail) {
    return _fetch("POST", url, data, success, fail);
}

function _fetch(method, url, data, success, fail) {
    const xhr = new XMLHttpRequest();
    xhr.addEventListener("readystatechange", function () {
        if(xhr.readyState === XMLHttpRequest.DONE) {
            if(xhr.status === 200) success(xhr.responseText);
            else fail(xhr.responseText);
        }
    });
    xhr.open(method, url, true);
    xhr.setRequestHeader("Content-Type", "application/json");
    xhr.setRequestHeader("Accept", "application/json");
    xhr.send(JSON.stringify(data));
}

//@ sourceURL=page.js