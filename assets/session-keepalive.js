(function () {
  var currentScript = document.currentScript;
  var endpoint = currentScript && currentScript.dataset ? currentScript.dataset.sessionUrl : "";
  var intervalMs = 5 * 60 * 1000;

  if (!endpoint || !window.fetch) {
    return;
  }

  function shouldPing() {
    if (document.hidden) {
      return false;
    }

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
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) {
      ping();
    }
  });
})();
