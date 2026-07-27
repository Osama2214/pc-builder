const API_BASE_URL = "http://127.0.0.1:8000/api";

class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

async function apiRequest(path, { method = "GET", body, auth = true } = {}) {
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
