const STATIC_CACHE = "uiat-static-v4";
const PUBLIC_PAGES_CACHE = "uiat-public-pages-v1";
const ACTIVE_CACHES = [STATIC_CACHE, PUBLIC_PAGES_CACHE];
const STATIC_ASSETS = [
  "./offline.html",
  "./login.php",
  "./manifest.webmanifest",
  "./favicon.ico",
  "./assets/pwa/icon-192.png",
  "./assets/pwa/icon-512.png",
  "./assets/pwa/apple-touch-icon.png",
  "./assets/pwa/screenshot-wide.png",
  "./assets/pwa/screenshot-mobile.png",
  "./assets/pwa/pwa-register.js",
  "./style_gian.css",
  "./style_mushu.css"
];

function emptyResponse(status = 204) {
  return new Response("", { status });
}

function isPublicNavigation(url) {
  const pathname = url.pathname.toLowerCase();
  return pathname === "/" || pathname.endsWith("/login.php");
}

self.addEventListener("install", (event) => {
  event.waitUntil(
    Promise.all([
      caches.open(STATIC_CACHE).then((cache) => cache.addAll(STATIC_ASSETS)).catch(() => Promise.resolve()),
      caches.open(PUBLIC_PAGES_CACHE).then((cache) => cache.add("./login.php")).catch(() => Promise.resolve())
    ])
  );
  self.skipWaiting();
});

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => !ACTIVE_CACHES.includes(key))
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (request.method !== "GET") {
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request)
        .then((networkResponse) => {
          if (networkResponse && networkResponse.ok && isPublicNavigation(url) && !networkResponse.redirected) {
            caches.open(PUBLIC_PAGES_CACHE).then((cache) => cache.put(request, networkResponse.clone()));
          }

          return networkResponse;
        })
        .catch(() => {
          if (isPublicNavigation(url)) {
            return caches.match(request).then((cachedPage) => {
              if (cachedPage) {
                return cachedPage;
              }

              return caches.match("./login.php").then((cachedLogin) => cachedLogin || caches.match("./offline.html"));
            });
          }

          return caches.match("./offline.html");
        })
    );
    return;
  }

  const destination = request.destination;
  if (["style", "script", "image", "font"].includes(destination)) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        const networkFetch = fetch(request)
          .then((networkResponse) => {
            if (networkResponse && networkResponse.ok) {
              caches.open(STATIC_CACHE).then((cache) => cache.put(request, networkResponse.clone()));
            }

            return networkResponse;
          })
          .catch(() => {
            if (cachedResponse) {
              return cachedResponse;
            }

            if (url.pathname.endsWith("/favicon.ico")) {
              return caches.match("./favicon.ico").then((fallbackIcon) => fallbackIcon || emptyResponse());
            }

            return emptyResponse();
          });

        return cachedResponse || networkFetch;
      })
    );
  }
});
