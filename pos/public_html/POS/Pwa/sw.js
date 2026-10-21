const CACHE_NAME = 'apex-pos-v1';
const ASSETS_TO_CACHE = [
  '/',
  '/pages/pos/index.html',
  '/assets/css/style.css',
  '/assets/js/app.js',
  '/assets/js/config.js',
  '/assets/js/services/api.service.js',
  '/assets/js/services/storage.service.js',
  '/assets/js/modules/pos.js',
  '/assets/js/modules/products.js',
  '/assets/js/modules/categories.js',
  '/assets/js/modules/sales.js',
  '/assets/js/modules/reports.js',
  '/Manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS_TO_CACHE))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(keys.map((key) => {
        if (key !== CACHE_NAME) return caches.delete(key);
      }));
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.url.includes('/api/')) {
    event.respondWith(
      fetch(event.request).catch(() => caches.match(event.request))
    );
  } else {
    event.respondWith(
      fetch(event.request).catch(() => caches.match(event.request))
    );
  }
});