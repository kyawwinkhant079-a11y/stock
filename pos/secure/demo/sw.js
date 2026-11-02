const CACHE_NAME = 'nexuspos-v1';
const ASSETS = [
  './index.html',
  './manifest.json',
  './css/style.css',
  './css/components.css',
  './js/api.js',
  './js/store.js',
  './js/app.js',
  './js/modules/auth.js',
  './js/modules/pos.js',
  './js/modules/products.js',
  './js/modules/categories.js',
  './js/modules/inventory.js',
  './js/modules/sales.js',
  './js/modules/suppliers.js',
  './js/modules/reports.js',
  './js/modules/users.js',
  './js/modules/shops.js'
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS)));
});

self.addEventListener('fetch', e => {
  e.respondWith(caches.match(e.request).then(res => res || fetch(e.request)));
});