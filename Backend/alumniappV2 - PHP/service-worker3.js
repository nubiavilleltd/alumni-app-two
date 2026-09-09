// === VERSION & CACHE NAME ===
const CACHE_NAME = "estate-cache-v8";

// === LOCAL ASSETS TO CACHE ===
const LOCAL_ASSETS = [
  "/", // homepage
  "/index.php", // root with index.php if CI uses it
  "/pwa/offline", // offline fallback
  "/index.php/pwa/offline", // fallback with index.php path
"/setup/view_users",
  // === CSS FILES ===
  "/assets/css/MonthPicker.min.css",
  "/assets/css/sweetalert.css",
  "/assets/css/animate.css",
  "/assets/css/jquery-ui.min.css",
  "/assets/css/editors/summernote.css",
  "/assets/css/magnific-popup.css",
  "/assets/css/xtras.css",
  "/assets/css/theme.css",
  "/assets/css/dashlite.css",

  // === JS FILES ===
  "/assets/js/jquery-3.7.0.min.js",
  "/assets/js/bundle.js",
  "/assets/js/scripts.js",
  "/assets/js/libs/datatable-btns.js",
  "/assets/js/jquery-ui.min.js",
  "/assets/js/MonthPicker.min.js",
  "/assets/js/jquery.easing.1.3.min.js",
  "/assets/js/jquery.sticky.js",
  "/assets/js/bootstrap-hover-dropdown.min.js",
  "/assets/js/jquery.stellar.min.js",
  "/assets/js/wow.min.js",
  "/assets/js/sweetalert.min.js",
  "/assets/js/jquery.magnific-popup.min.js",
  "/assets/js/custom.js",
  "/assets/js/dataTables.dateTime.min.js",
  "/assets/js/moment.min.js",
  "/assets/js/libs/editors/summernote.js",
  "/assets/js/editors.js"
];

// === OPTIONAL EXTERNAL ASSETS ===
const EXTERNAL_ASSETS = [
  "https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css",
  "https://cdn.datatables.net/rowreorder/1.4.1/css/rowReorder.dataTables.min.css",
  "https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css",
  "https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js",
  "https://cdn.datatables.net/rowreorder/1.4.1/js/dataTables.rowReorder.min.js",
  "https://mpryvkin.github.io/jquery-datatables-row-reordering/1.2.3/jquery.dataTables.rowReordering.js",
  "https://cdn.jsdelivr.net/npm/flatpickr",
  "https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js",
  "https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js",
  "https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"
];

// === HELPERS ===
function isHttpUrl(url) {
  return url && (url.startsWith("http://") || url.startsWith("https://") || url.startsWith("/"));
}

// === INSTALL EVENT ===
self.addEventListener("install", event => {
  console.log("🔧 Installing service worker for estate domain...");

  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    console.log("📦 Caching local assets for estate domain...");

    for (const url of LOCAL_ASSETS) {
      if (!isHttpUrl(url)) {
        console.warn("⚠️ Skipping invalid URL:", url);
        continue;
      }
      try {
        const res = await fetch(url);
        if (res && res.ok) {
          await cache.put(url, res.clone());
          console.log("✅ Cached local:", url);
        } else {
          console.warn("⚠️ Skipped local (bad status):", url, res && res.status);
        }
      } catch (err) {
        console.warn("⚠️ Skipped local (fetch failed):", url, err && err.message);
      }
    }

    for (const extUrl of EXTERNAL_ASSETS) {
      try {
        const res = await fetch(extUrl, { mode: "no-cors" }).catch(() => null);
        if (res) {
          try {
            await cache.put(extUrl, res);
            console.log("✅ Cached external:", extUrl);
          } catch {
            console.warn("⚠️ External put skipped:", extUrl);
          }
        } else {
          console.warn("⚠️ External fetch returned null:", extUrl);
        }
      } catch (err) {
        console.warn("⚠️ External skipped (failed):", extUrl, err && err.message);
      }
    }

    console.log("✅ Installation complete for estate domain.");
  })());
});

// === ACTIVATE EVENT ===
self.addEventListener("activate", event => {
  console.log("🧹 Activating service worker for estate domain...");
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map(key => {
      if (key !== CACHE_NAME) {
        console.log("🗑️ Deleting old cache:", key);
        return caches.delete(key);
      }
    }));
    return self.clients.claim();
  })());
});

// === FETCH EVENT ===
self.addEventListener("fetch", event => {
  if (!event.request.url.startsWith("http")) return;

  event.respondWith((async () => {
    try {
      const networkResponse = await fetch(event.request);
      if (event.request.method === "GET") {
        try {
          if (networkResponse && networkResponse.ok && networkResponse.type === "basic") {
            const cache = await caches.open(CACHE_NAME);
            cache.put(event.request, networkResponse.clone()).catch(() => {});
          } else {
            if (EXTERNAL_ASSETS.includes(event.request.url)) {
              const cache = await caches.open(CACHE_NAME);
              cache.put(event.request, networkResponse.clone()).catch(() => {});
            }
          }
        } catch { }
      }
      return networkResponse;
    } catch {
      const cached = await caches.match(event.request);
      if (cached) return cached;

      if (event.request.mode === "navigate") {
        const offlinePage =
          (await caches.match("/pwa/offline")) ||
          (await caches.match("/index.php/pwa/offline"));
        if (offlinePage) return offlinePage;
      }

      return new Response("Offline and no cached resource found.", {
        status: 503,
        statusText: "Service Unavailable",
        headers: { "Content-Type": "text/plain" }
      });
    }
  })());
});
