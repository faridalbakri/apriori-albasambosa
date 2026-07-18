// Shared helpers for AlbaSambosa K6 load test scripts.
// These scripts test HTTP-level endpoints — Livewire stateful interactions
// are covered by Pest feature tests (StockRaceConditionTest, CheckoutTest).

/**
 * Extract CSRF token from an HTML page.
 * Checks both <meta name="csrf-token"> and hidden <input name="_token">.
 */
export function getCsrfToken(html) {
  // Try meta tag first (layouts used by login/register)
  const metaMatch = html.match(/<meta name="csrf-token" content="([^"]+)"/);
  if (metaMatch) return metaMatch[1];

  // Try hidden input (Livewire embeds this)
  const inputMatch = html.match(/<input[^>]*name="_token"[^>]*value="([^"]+)"/);
  if (inputMatch) return inputMatch[1];

  return null;
}

/**
 * Login as test customer. Returns session cookies.
 * Uses the standard Laravel POST /login endpoint (non-Livewire).
 *
 * @param {object} http - K6 HTTP module
 * @param {string} baseUrl - Base URL of the application (e.g. 'http://localhost:8000')
 */
export function loginAsCustomer(http, baseUrl) {
  const loginPage = http.get(`${baseUrl}/login`);
  const csrf = getCsrfToken(loginPage.body);

  const res = http.post(`${baseUrl}/login`, {
    _token: csrf,
    email: 'customer@test.com',
    password: 'password',
  });

  return {
    success: res.status === 302 || res.url.includes('verify-email') || res.status === 200,
    csrf: csrf,
  };
}

/**
 * Fetch product IDs from the catalog page for random browsing.
 * Products use numeric route model binding (/produk/{id}).
 * Returns an array of product IDs found on /menu.
 *
 * @param {object} http - K6 HTTP module
 * @param {string} baseUrl - Base URL of the application (e.g. 'http://localhost:8000')
 */
export function getProductUrls(http, baseUrl) {
  const res = http.get(`${baseUrl}/menu`);
  const matches = res.body.matchAll(/\/produk\/(\d+)/g);
  const ids = [...new Set([...matches].map((m) => m[1]))];
  return ids;
}

/**
 * Pick a random element from an array.
 */
export function randomItem(arr) {
  return arr[Math.floor(Math.random() * arr.length)];
}

/**
 * Generate a random Indonesian phone number for test data.
 */
export function randomPhone() {
  const prefix = ['0812', '0813', '0856', '0857', '0878', '0895'];
  const p = randomItem(prefix);
  const suffix = String(Math.floor(Math.random() * 100000000)).padStart(8, '0');
  return p + suffix;
}
