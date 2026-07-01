const CACHE_NAME = 'bengkelin-v1';
const ASSETS_TO_CACHE = [
  '/bengkelin/',
  '/bengkelin/login.php',
  '/bengkelin/assets/css/style.css',
  '/bengkelin/assets/css/login.css'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        // Coba cache asset jika ada, jika gagal abaikan
        return cache.addAll(ASSETS_TO_CACHE).catch(e => console.warn('Cache addAll failed', e));
      })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

self.addEventListener('fetch', (event) => {
  // Hanya intercept GET request dan yang HTML/CSS/JS (abaikan request API/PHP POST)
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .catch(() => {
        return caches.match(event.request);
      })
  );
});
