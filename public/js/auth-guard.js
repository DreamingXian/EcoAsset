/**
 * EcoAsset - Client-Side Authentication & Session Guard
 * Manages JWT tokens, user profile state, authenticated API requests, and role protection.
 */

const AuthGuard = {
  TOKEN_KEY: 'ecoasset_token',
  USER_KEY: 'ecoasset_user',

  getToken() {
    return localStorage.getItem(this.TOKEN_KEY);
  },

  getUser() {
    try {
      const userJson = localStorage.getItem(this.USER_KEY);
      return userJson ? JSON.parse(userJson) : null;
    } catch (e) {
      return null;
    }
  },

  setSession(token, user) {
    if (token) localStorage.setItem(this.TOKEN_KEY, token);
    if (user) localStorage.setItem(this.USER_KEY, JSON.stringify(user));
  },

  clearSession() {
    localStorage.removeItem(this.TOKEN_KEY);
    localStorage.removeItem(this.USER_KEY);
  },

  logout() {
    this.clearSession();
    const loginUrl = window.location.pathname.includes('/module') ? '../module5/login.php' : 'module5/login.php';
    window.location.href = loginUrl;
  },

  requireAuth(allowedRoles = []) {
    const token = this.getToken();
    const user = this.getUser();

    const loginUrl = window.location.pathname.includes('/module') ? '../module5/login.php' : 'module5/login.php';

    if (!token || !user) {
      this.clearSession();
      window.location.href = loginUrl;
      return null;
    }


    if (allowedRoles.length > 0 && !allowedRoles.includes(user.role)) {
      alert(`Access denied. Your role (${user.role}) is not authorized for this section.`);
      if (user.role === 'MAINTENANCE_CREW') {
        window.location.href = window.location.pathname.includes('/module') ? '../module3/index.html' : 'module3/index.html';
      } else {
        window.location.href = window.location.pathname.includes('/module') ? '../module1/index.html' : 'module1/index.html';
      }
      return null;
    }

    // Force first-time login users to update password before performing any action
    if (user.must_change_password === true && !window.location.pathname.includes('update-account.html')) {
      const updateUrl = window.location.pathname.includes('/module') ? '../module5/update-account.html?first_login=1' : 'module5/update-account.html?first_login=1';
      window.location.href = updateUrl;
      return null;
    }


    return user;
  },

  async fetchWithAuth(url, options = {}) {
    const token = this.getToken();
    const headers = {
      'Accept': 'application/json',
      ...(options.headers || {})
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    // Default JSON content-type if body is a plain object or string
    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }

    const response = await fetch(url, { ...options, headers });

    if (response.status === 401) {
      console.warn('Auth token expired or invalid. Redirecting to login.');
      this.logout();
      return null;
    }

    return response;
  },

  async syncUserProfile() {
    try {
      const response = await this.fetchWithAuth('/api/index.php/api/v1/auth/me');
      if (!response || !response.ok) return this.getUser();

      const result = await response.json();
      if (result.status === 'success' && result.data?.user) {
        this.setSession(null, result.data.user);
        return result.data.user;
      }
    } catch (err) {
      console.error('Failed to sync profile with server:', err);
    }
    return this.getUser();
  }
};

// Global shorthand for window scope
window.AuthGuard = AuthGuard;
window.handleLogout = function() {
  if (confirm('Are you sure you want to sign out?')) {
    AuthGuard.logout();
  }
};
