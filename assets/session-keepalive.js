(function () {
  var currentScript = document.currentScript;
  var endpoint = currentScript && currentScript.dataset ? currentScript.dataset.sessionUrl : "";
  var intervalMs = 4 * 60 * 1000;

  if (!endpoint || !window.fetch) {
    return;
  }

  function shouldPing() {
    return navigator.onLine !== false;
  }

  function ping() {
    if (!shouldPing()) {
      return;
    }

    fetch(endpoint, {
      method: "POST",
      credentials: "same-origin",
      cache: "no-store",
      keepalive: true,
      headers: {
        "X-UIAT-Keepalive": "1"
      }
    }).catch(function () {});
  }

  window.setInterval(ping, intervalMs);
  window.addEventListener("pageshow", ping);
  window.addEventListener("focus", ping);
  window.addEventListener("online", ping);
  document.addEventListener("visibilitychange", function () {
    ping();
  });
})();
