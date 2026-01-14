const fns = {
};
chrome.runtime.onMessage.addListener((request, sender, response) => {
    if(fns[request.method]) {
        fns[request.method](response, request.data);
        return true;
    }
});