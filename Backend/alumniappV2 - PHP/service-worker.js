// === VERSION & CACHE NAME ===
const CACHE_NAME = "estate-cache-v10";

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
  console.log("🔧 Installing service worker...");

  event.waitUntil((async () => {
    const cache = await caches.open(CACHE_NAME);
    console.log("📦 Caching local and external assets...");

    for (const url of LOCAL_ASSETS) {
      if (!isHttpUrl(url)) continue;
      try {
        const res = await fetch(url);
        if (res && res.ok) await cache.put(url, res.clone());
      } catch (err) {
        console.warn("⚠️ Local asset skipped:", url, err.message);
      }
    }

    for (const extUrl of EXTERNAL_ASSETS) {
      try {
        const res = await fetch(extUrl, { mode: "no-cors" });
        if (res) await cache.put(extUrl, res);
      } catch (err) {
        console.warn("⚠️ External asset skipped:", extUrl, err.message);
      }
    }

    console.log("✅ Install complete.");
  })());
});

// === ACTIVATE EVENT ===
self.addEventListener("activate", event => {
  console.log("🧹 Activating service worker...");
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.map(key => {
      if (key !== CACHE_NAME) {
        console.log("🗑️ Removing old cache:", key);
        return caches.delete(key);
      }
    }));
    await self.clients.claim();
  })());
});

// === FETCH EVENT (Enhanced: Pages + AJAX/API) ===
self.addEventListener("fetch", event => {
  if (!event.request.url.startsWith("http")) return;

  event.respondWith((async () => {
    try {
      // Try network first
      const networkResponse = await fetch(event.request);

      // ✅ Cache normal GET requests
      if (event.request.method === "GET" && networkResponse && networkResponse.ok) {
        const cache = await caches.open(CACHE_NAME);
        cache.put(event.request, networkResponse.clone()).catch(() => {});
      }

      // ✅ Cache API responses (JSON)
      const acceptHeader = event.request.headers.get("accept") || "";
      if (event.request.method === "GET" && acceptHeader.includes("application/json")) {
        const cache = await caches.open(CACHE_NAME);
        cache.put(event.request, networkResponse.clone()).catch(() => {});
      }

      return networkResponse;
    } catch (err) {
      // 🚫 Offline or network failed
      const cache = await caches.open(CACHE_NAME);
      const cached = await cache.match(event.request);

      if (cached) {
        console.log("💾 Serving cached:", event.request.url);
        return cached;
      }

      // 🧠 For JSON/API requests
      const acceptHeader = event.request.headers.get("accept") || "";
      if (acceptHeader.includes("application/json")) {
        console.warn("📡 Offline JSON fallback for:", event.request.url);
        return new Response(
          JSON.stringify({
            error: true,
            message: "Offline mode — data unavailable",
            cached: false,
            url: event.request.url
          }),
          { headers: { "Content-Type": "application/json" } }
        );
      }

      // 🧭 For navigation (HTML pages)
      if (event.request.mode === "navigate") {
        const offlinePage =
          (await caches.match("/pwa/offline")) ||
          (await caches.match("/index.php/pwa/offline"));
        if (offlinePage) return offlinePage;
      }

      // 📄 Default fallback
      return new Response("Offline and no cached resource found.", {
        status: 503,
        statusText: "Service Unavailable",
        headers: { "Content-Type": "text/plain" }
      });
    }
  })());
});
