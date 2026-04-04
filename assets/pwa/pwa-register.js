(function () {
  var scope = "/";
  var currentScript = document.currentScript;
  if (currentScript && currentScript.dataset && currentScript.dataset.pwaScope) {
    scope = currentScript.dataset.pwaScope;
  }

  function normalizeScope(value) {
    if (!value) {
      return "/";
    }

    return value.endsWith("/") ? value : value + "/";
  }

  scope = normalizeScope(scope);
  var registrationPromise = null;
  var hasReloadedAfterUpdate = false;

  function emptyFn() {}

  function listenForWaitingServiceWorker(registration, callback) {
    if (!registration) {
      return;
    }

    if (registration.waiting) {
      callback(registration.waiting);
      return;
    }

    function trackInstalling(worker) {
      if (!worker) {
        return;
      }

      worker.addEventListener("statechange", function () {
        if (worker.state === "installed" && navigator.serviceWorker.controller) {
          callback(worker);
        }
      });
    }

    if (registration.installing) {
      trackInstalling(registration.installing);
    }

    registration.addEventListener("updatefound", function () {
      trackInstalling(registration.installing);
    });
  }

  if ("serviceWorker" in navigator && window.isSecureContext) {
    window.addEventListener("load", function () {
      registrationPromise = navigator.serviceWorker.register(scope + "sw.js", { scope: scope });
      registrationPromise.then(function (registration) {
        listenForWaitingServiceWorker(registration, askForRefresh);
      }).catch(emptyFn);
      registrationPromise.catch(function (error) {
        console.warn("No se pudo registrar el service worker", error);
      });
    });
  }

  if (window.self !== window.top) {
    return;
  }

  var banner = document.getElementById("uiat-pwa-banner");
  var installButton = document.getElementById("uiat-pwa-install");
  var dismissButton = document.getElementById("uiat-pwa-dismiss");
  var textNode = document.getElementById("uiat-pwa-text");
  var statusNode = document.getElementById("uiat-pwa-status");
  var statusTextNode = document.getElementById("uiat-pwa-status-text");
  var updateNode = document.getElementById("uiat-pwa-update");
  var refreshButton = document.getElementById("uiat-pwa-refresh");
  var updateDismissButton = document.getElementById("uiat-pwa-update-dismiss");
  if (!banner || !installButton || !dismissButton || !textNode || !statusNode || !statusTextNode || !updateNode || !refreshButton || !updateDismissButton) {
    return;
  }

  var storageKey = "uiat-pwa-banner-dismissed";
  var updateDismissKey = "uiat-pwa-update-dismissed";
  var deferredPrompt = null;
  var waitingWorker = null;
  var standalone = window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;
  var isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
  var isSafari = /^((?!chrome|android).)*safari/i.test(window.navigator.userAgent);

  function getFlag(key) {
    try {
      return window.localStorage.getItem(key) === "1";
    } catch (error) {
      return false;
    }
  }

  function setFlag(key, value) {
    try {
      if (value) {
        window.localStorage.setItem(key, "1");
      } else {
        window.localStorage.removeItem(key);
      }
    } catch (error) {
      return;
    }
  }

  function isDismissed() {
    return getFlag(storageKey);
  }

  function setDismissed() {
    setFlag(storageKey, true);
  }

  function showBanner(message, installable) {
    if (standalone || isDismissed()) {
      return;
    }

    textNode.textContent = message;
    installButton.hidden = !installable;
    banner.hidden = false;
    banner.classList.add("is-visible");
  }

  function hideBanner(rememberChoice) {
    if (rememberChoice) {
      setDismissed();
    }

    banner.classList.remove("is-visible");
    banner.hidden = true;
  }

  function showStatus(state, message, autoHide) {
    statusNode.dataset.state = state;
    statusTextNode.textContent = message;
    statusNode.hidden = false;
    statusNode.classList.add("is-visible");

    if (autoHide) {
      window.clearTimeout(showStatus.timeoutId);
      showStatus.timeoutId = window.setTimeout(function () {
        statusNode.classList.remove("is-visible");
        statusNode.hidden = true;
      }, 2600);
    }
  }

  function updateConnectivityState(initial) {
    if (navigator.onLine) {
      if (initial) {
        statusNode.classList.remove("is-visible");
        statusNode.hidden = true;
        return;
      }

      showStatus("online", "Conexión restablecida", true);
    } else {
      showStatus("offline", "Sin conexión. Algunas funciones quedarán limitadas.", false);
    }
  }

  function isUpdateDismissed() {
    return getFlag(updateDismissKey);
  }

  function showUpdate() {
    if (isUpdateDismissed()) {
      return;
    }

    updateNode.hidden = false;
    updateNode.classList.add("is-visible");
  }

  function hideUpdate(rememberChoice) {
    if (rememberChoice) {
      setFlag(updateDismissKey, true);
    }

    updateNode.classList.remove("is-visible");
    updateNode.hidden = true;
  }

  function askForRefresh(worker) {
    waitingWorker = worker;
    setFlag(updateDismissKey, false);
    showUpdate();
  }

  dismissButton.addEventListener("click", function () {
    hideBanner(true);
  });

  updateDismissButton.addEventListener("click", function () {
    hideUpdate(true);
  });

  refreshButton.addEventListener("click", function () {
    if (!waitingWorker) {
      window.location.reload();
      return;
    }

    waitingWorker.postMessage({ type: "SKIP_WAITING" });
  });

  window.addEventListener("beforeinstallprompt", function (event) {
    event.preventDefault();
    deferredPrompt = event;
    showBanner("Instala la app para abrir UIAT Norte como acceso directo.", true);
  });

  installButton.addEventListener("click", function () {
    if (!deferredPrompt) {
      return;
    }

    deferredPrompt.prompt();
    deferredPrompt.userChoice.finally(function () {
      deferredPrompt = null;
      hideBanner(true);
    });
  });

  window.addEventListener("appinstalled", function () {
    deferredPrompt = null;
    hideBanner(true);
  });

  if (isIos && isSafari && !standalone) {
    showBanner("En iPhone o iPad usa Compartir y luego 'Agregar a pantalla de inicio'.", false);
  }

  updateConnectivityState(true);
  window.addEventListener("online", function () { updateConnectivityState(false); });
  window.addEventListener("offline", function () { updateConnectivityState(false); });

  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.addEventListener("controllerchange", function () {
      if (hasReloadedAfterUpdate) {
        return;
      }

      hasReloadedAfterUpdate = true;
      window.location.reload();
    });
  }
})();
