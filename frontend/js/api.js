// Local dev hits the Laravel dev server; anywhere else hits the deployed
// Render backend. If you rename the Render service, update the URL below
// to match (it must end in "/api").
const API_BASE_URL = (() => {
  const { hostname } = window.location;
  if (hostname === "localhost" || hostname === "127.0.0.1") {
    return "http://127.0.0.1:8000/api";
  }
  return "https://pc-builder-api-1b1i.onrender.com/api";
})();

class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

// Several pages independently fetch the same public GET endpoint within
// moments of each other on page load (e.g. the home page's product grid and
// the navbar's search-placeholder animation both request "/products"). This
// cache collapses those into a single network call — short-lived and GET-only,
// so it never risks serving stale data for anything that mutates.
const GET_RESPONSE_CACHE_TTL_MS = 15000;
const getResponseCache = new Map();

async function apiRequest(path, { method = "GET", body, auth = true } = {}) {
  const cacheKey = !auth && method === "GET" ? path : null;
  if (cacheKey) {
    const cached = getResponseCache.get(cacheKey);
    if (cached) {
      if (Date.now() - cached.savedAt < GET_RESPONSE_CACHE_TTL_MS) return cached.promise.then((data) => data);
      getResponseCache.delete(cacheKey);
    }
  }

  const requestPromise = performApiRequest(path, { method, body, auth });

  if (cacheKey) {
    getResponseCache.set(cacheKey, { savedAt: Date.now(), promise: requestPromise });
    requestPromise.catch(() => getResponseCache.delete(cacheKey));
  }

  return requestPromise;
}

async function performApiRequest(path, { method, body, auth }) {
  const headers = { Accept: "application/json" };
  const isFormData = body instanceof FormData;

  // For FormData, the browser must set Content-Type itself (with the
  // multipart boundary) — setting it manually here would break the upload.
  if (body !== undefined && !isFormData) {
    headers["Content-Type"] = "application/json";
  }

  if (auth) {
    const token = Auth.getToken();
    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }
  }

  let response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      method,
      headers,
      body: isFormData ? body : (body !== undefined ? JSON.stringify(body) : undefined),
    });
  } catch (networkError) {
    throw new ApiError("Could not reach the server. Please check your connection.", 0);
  }

  const text = await response.text();
  let data = null;
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      // non-JSON response body, leave data as null
    }
  }

  if (!response.ok) {
    const message = data?.message || `Request failed (${response.status})`;
    throw new ApiError(message, response.status, data?.errors || {});
  }

  return data;
}

const Api = {
  register: (payload) => apiRequest("/register", { method: "POST", body: payload, auth: false }),
  login: (payload) => apiRequest("/login", { method: "POST", body: payload, auth: false }),
  logout: () => apiRequest("/logout", { method: "POST" }),
  me: () => apiRequest("/user"),
};
