/* FixMyHyderabad — service worker.
   Bump VERSION whenever you change the site's CSS, JS, images or page shell,
   otherwise visitors may keep seeing the cached copy for a while. */

const VERSION = "fmh-v3";

const CORE = [
  "./",
  "./index.html",
  "./report.html",
  "./map.html",
  "./stats.html",
  "./about.html",
  "./help.html",
  "./privacy.html",
  "./terms.html",
  "./app.html",
  "./manifest.webmanifest",
  "./assets/theme.css",
  "./assets/site.js",
  "./assets/brand/mark.svg",
  "./assets/brand/favicon.svg",
  "./assets/app/icon-32.png",
  "./assets/app/icon-180.png",
  "./assets/app/icon-192.png",
  "./assets/app/icon-512.png",
  "./assets/app/icon-maskable-512.png",
  "./assets/fonts/geist-latin.woff2",
  "./assets/fonts/geist-mono-latin.woff2",
  "./assets/img/ghanta-ghar.jpg"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(VERSION)
      .then((cache) => cache.addAll(CORE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys
            .filter((key) => (key.startsWith("fmh-") || key.startsWith("hc-")) && key !== VERSION)
            .map((key) => caches.delete(key))
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;

  if (request.method !== "GET") return;

  const url = new URL(request.url);

  // Only handle our own files. Map tiles, fonts CDNs, etc. go straight out.
  if (url.origin !== self.location.origin) return;
  if (url.pathname.includes("/api/")) return;

  // Pages: network first so updates show up, cache as an offline fallback.
  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(VERSION).then((cache) => cache.put(request, copy));
          return response;
        })
        .catch(() =>
          caches.match(request).then((cached) => cached || caches.match("./index.html"))
        )
    );
    return;
  }

  // Assets: serve the cache immediately, refresh it in the background.
  event.respondWith(
    caches.match(request).then((cached) => {
      const network = fetch(request)
        .then((response) => {
          if (response && response.ok) {
            const copy = response.clone();
            caches.open(VERSION).then((cache) => cache.put(request, copy));
          }
          return response;
        })
        .catch(() => cached);

      return cached || network;
    })
  );
});
