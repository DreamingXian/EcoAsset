/**
 * EcoAsset Municipal Administration Portal
 * Shared Reusable Admin Sidebar Component
 *
 * Provides:
 * - Centralized 5-item navigation menu with active page highlighting
 * - Live digital system clock (seconds + calendar date)
 * - Administrator profile hydration from AuthGuard
 * - Global logout handler
 */

class AdminSidebar {
  /**
   * Initializes and renders the shared sidebar into the page.
   * @param {Object} options
   * @param {string} options.active - Active tab key ('overview' | 'tasks' | 'reports' | 'users' | 'account')
   */
  static init(options = {}) {
    const active = options.active || 'overview';
    const container = document.getElementById('adminSidebar') || document.querySelector('aside.admin-sidebar');
    if (!container) return;

    // Detect path depth to dynamically build correct relative links
    const path = window.location.pathname.replace(/\\/g, '/');
    const isModule3Admin = path.includes('/module3/admin/');
    const isModule4 = path.includes('/module4/');
    const isModule5 = path.includes('/module5/');
    const isAdminRoot = path.includes('/admin/') && !isModule3Admin;

    let prefix = '../';
    if (isModule3Admin) {
      prefix = '../../';
    } else if (isAdminRoot || isModule4 || isModule5) {
      prefix = '../';
    }

    // Build URL matrix
    const urls = {
      overview: isModule3Admin ? '../../admin/index.html' : (isAdminRoot ? 'index.html' : '../admin/index.html'),
      tasks: isModule3Admin ? 'index.html' : (prefix === '../../' ? '../../module3/admin/index.html' : '../module3/admin/index.html'),
      reports: isModule4 ? 'index.html' : (prefix === '../../' ? '../../module4/index.html' : '../module4/index.html'),
      users: isModule5 ? 'index.html' : (prefix === '../../' ? '../../module5/index.html' : '../module5/index.html'),
      account: isAdminRoot ? 'profile.html' : (prefix === '../../' ? '../../admin/profile.html' : '../admin/profile.html')
    };

    container.className = 'admin-sidebar';
    container.innerHTML = `
      <div class="sidebar-top-section">
        <!-- Branding Logo (Clickable to Overview) -->
        <a href="${urls.overview}" class="sidebar-brand" title="Return to Overview">
          <div class="brand-icon-box">
            <svg viewBox="0 0 24 24">
              <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
              <path d="M2 12l10 5 10-5"></path>
              <path d="M2 17l10 5 10-5"></path>
            </svg>
          </div>
          <div class="brand-title-group">
            <h2>EcoAsset</h2>
            <p>Municipal Platform</p>
          </div>
        </a>

        <!-- Prominent Live System Clock Card -->
        <div class="sidebar-clock-card">
          <div class="clock-card-header">
            <div class="clock-live-indicator">
              <span class="clock-dot"></span>
              <span class="clock-status-lbl">System Live</span>
            </div>
            <span class="clock-date-lbl" id="sidebarLiveDate">--</span>
          </div>
          <div class="clock-time-display" id="sidebarLiveClock">--:--:--</div>
        </div>

        <!-- Current Admin Profile Card -->
        <div class="admin-profile-card">
          <div class="signed-in-label">Signed in as</div>
          <div class="admin-name" id="sidebarAdminName">Administrator</div>
          <div class="admin-email" id="sidebarAdminEmail">admin@ecoasset.org</div>
        </div>

        <!-- Unified Navigation Links (5 Items) -->
        <nav class="sidebar-nav">
          <a href="${urls.overview}" class="nav-item ${active === 'overview' ? 'active' : ''}">
            <span class="nav-icon">🏠</span>
            <span>Overview</span>
          </a>
          <a href="${urls.tasks}" class="nav-item ${active === 'tasks' ? 'active' : ''}">
            <span class="nav-icon">🛠️</span>
            <span>Task Control</span>
          </a>
          <a href="${urls.reports}" class="nav-item ${active === 'reports' ? 'active' : ''}">
            <span class="nav-icon">📊</span>
            <span>Reports</span>
          </a>
          <a href="${urls.users}" class="nav-item ${active === 'users' ? 'active' : ''}">
            <span class="nav-icon">👥</span>
            <span>User Management</span>
          </a>
          <a href="${urls.account}" class="nav-item ${active === 'account' ? 'active' : ''}">
            <span class="nav-icon">👤</span>
            <span>Account</span>
          </a>
        </nav>
      </div>

      <!-- Sidebar Footer (Sign Out) -->
      <div class="sidebar-footer">
        <button type="button" class="btn-sidebar-logout" id="sidebarLogoutBtn">
          <span>🚪</span>
          <span>Sign Out</span>
        </button>
      </div>
    `;

    // 1. Attach Global Logout Handler
    const logoutBtn = document.getElementById('sidebarLogoutBtn');
    if (logoutBtn) {
      logoutBtn.onclick = () => {
        if (confirm('Are you sure you want to sign out from the Administrator Portal?')) {
          if (typeof AuthGuard !== 'undefined' && AuthGuard.logout) {
            AuthGuard.logout();
          } else {
            localStorage.clear();
            sessionStorage.clear();
            window.location.href = isModule3Admin ? '../../login.php' : '../login.php';
          }
        }
      };
    }

    // 2. Hydrate User Info
    AdminSidebar.hydrateUserData();

    // 3. Initialize and Start Digital Clock
    AdminSidebar.updateClock();
    if (!window._adminSidebarClockInterval) {
      window._adminSidebarClockInterval = setInterval(AdminSidebar.updateClock, 1000);
    }
  }

  /**
   * Hydrates the admin name and email in the sidebar.
   * @param {Object} [userData] - Optional user object with full_name and email
   */
  static hydrateUserData(userData) {
    let user = userData;
    if (!user && typeof AuthGuard !== 'undefined' && AuthGuard.getUser) {
      user = AuthGuard.getUser();
    }
    if (user) {
      const name = user.full_name || 'Administrator';
      const email = user.email || 'admin@ecoasset.org';
      const nameEl = document.getElementById('sidebarAdminName');
      const emailEl = document.getElementById('sidebarAdminEmail');
      if (nameEl) nameEl.textContent = name;
      if (emailEl) emailEl.textContent = email;
    }
  }

  /**
   * Updates the digital clock display and calendar date text.
   */
  static updateClock() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const dateStr = now.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });

    const timeEl = document.getElementById('sidebarLiveClock') || document.getElementById('liveClockDisplay');
    const dateEl = document.getElementById('sidebarLiveDate');

    if (timeEl) timeEl.textContent = timeStr;
    if (dateEl) dateEl.textContent = dateStr;
  }
}

// Global convenience helper
function initAdminSidebar(activePage = 'overview') {
  AdminSidebar.init({ active: activePage });
}
