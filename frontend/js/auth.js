const TOKEN_KEY = "pcbuilder_token";
const USER_KEY = "pcbuilder_user";

const Auth = {
  getToken() {
    return localStorage.getItem(TOKEN_KEY);
  },

  getUser() {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? JSON.parse(raw) : null;
  },

  isLoggedIn() {
    return Boolean(Auth.getToken());
  },

  saveSession(token, user) {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
  },

  clearSession() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  },

  // Call on pages that require login (e.g. cart, build, profile).
  requireAuth(loginPage = "/login.html") {
    if (!Auth.isLoggedIn()) {
      window.location.href = loginPage;
    }
  },

  // UX convenience only — the API independently enforces the 'admin' middleware
  // on every admin endpoint regardless of what this check does.
  requireAdmin(redirectTo = "/index.html") {
    if (!Auth.isLoggedIn() || Auth.getUser()?.role !== "admin") {
      window.location.href = redirectTo;
    }
  },

  // Call on login/register pages so an already-logged-in visitor skips them.
  redirectIfAuthenticated(target = "/index.html") {
    if (Auth.isLoggedIn()) {
      window.location.href = target;
    }
  },
};
