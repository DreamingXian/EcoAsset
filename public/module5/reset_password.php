<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EcoAsset - Reset Password</title>
  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    :root {
      --primary-color: #1e4a38;
      --primary-hover: #16382b;
      --teal-btn: #00a884;
      --teal-btn-hover: #008f70;
      --text-main: #1e293b;
      --text-muted: #64748b;
      --text-placeholder: #94a3b8;
      --input-bg: #f1f5f9;
      --input-border-default: transparent;
      --error-color: #ef4444;
      --error-bg: #fff5f5;
      --error-border: #fca5a5;
      --card-bg: #ffffff;
      --card-border: #e2e8f0;
      --app-bg: #f8fafc;
      --font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: var(--font-family);
      background-color: var(--app-bg);
      color: var(--text-main);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 16px;
      -webkit-font-smoothing: antialiased;
    }

    .app-container {
      width: 100%;
      max-width: 412px;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 24px 8px;
    }

    /* Header & Branding */
    .brand-header {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      margin-bottom: 28px;
    }

    .brand-logo-badge {
      width: 68px;
      height: 68px;
      background: var(--primary-color);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 10px 25px rgba(30, 74, 56, 0.25);
      margin-bottom: 14px;
    }

    .brand-logo-badge svg {
      width: 38px;
      height: 38px;
      fill: none;
      stroke: #ffffff;
      stroke-width: 2.2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .brand-title {
      font-size: 26px;
      font-weight: 800;
      color: var(--text-main);
      letter-spacing: -0.5px;
      line-height: 1.2;
    }

    .brand-subtitle {
      font-size: 13.5px;
      color: var(--text-muted);
      font-weight: 500;
      margin-top: 4px;
    }

    /* Main Card */
    .auth-card {
      width: 100%;
      background: var(--card-bg);
      border-radius: 28px;
      border: 1px solid var(--card-border);
      box-shadow: 0 12px 36px rgba(15, 23, 42, 0.04);
      padding: 28px 24px;
    }

    .card-title {
      font-size: 18px;
      font-weight: 700;
      color: var(--text-main);
      margin-bottom: 6px;
    }

    .card-desc {
      font-size: 13px;
      color: var(--text-muted);
      margin-bottom: 22px;
      line-height: 1.4;
    }

    .user-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #f1f5f9;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 12.5px;
      font-weight: 600;
      color: #334155;
      margin-bottom: 18px;
    }

    /* Form */
    .form-group {
      display: flex;
      flex-direction: column;
      margin-bottom: 18px;
    }

    .form-label {
      font-size: 13.5px;
      font-weight: 600;
      color: #334155;
      margin-bottom: 7px;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
      width: 100%;
    }

    .input-icon {
      position: absolute;
      left: 14px;
      color: var(--text-placeholder);
      pointer-events: none;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .input-icon svg {
      width: 19px;
      height: 19px;
      stroke: currentColor;
      fill: none;
      stroke-width: 2;
    }

    .form-input {
      width: 100%;
      height: 48px;
      background-color: var(--input-bg);
      border: 1.5px solid var(--input-border-default);
      border-radius: 14px;
      padding: 0 16px 0 44px;
      font-family: inherit;
      font-size: 14px;
      color: var(--text-main);
      font-weight: 500;
      outline: none;
      transition: all 0.2s ease;
    }

    .form-input.has-toggle {
      padding-right: 44px;
    }

    .password-toggle-btn {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      color: var(--text-placeholder);
      cursor: pointer;
      padding: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      transition: color 0.15s ease, background-color 0.15s ease;
      z-index: 2;
    }

    .password-toggle-btn:hover {
      color: #334155;
      background-color: rgba(0, 0, 0, 0.04);
    }

    .password-toggle-btn:focus {
      outline: none;
      color: var(--primary-color);
    }

    .password-toggle-btn svg {
      width: 19px;
      height: 19px;
      stroke: currentColor;
      fill: none;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .form-input:focus {
      background-color: #ffffff;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 4px rgba(30, 74, 56, 0.08);
    }

    .form-group.has-error .form-input {
      border-color: var(--error-border);
      background-color: var(--error-bg);
      color: #991b1b;
    }

    .error-feedback {
      display: none;
      align-items: flex-start;
      gap: 6px;
      font-size: 12px;
      color: var(--error-color);
      margin-top: 6px;
      font-weight: 500;
    }

    .form-group.has-error .error-feedback {
      display: flex;
    }

    .error-feedback svg {
      width: 15px;
      height: 15px;
      flex-shrink: 0;
      margin-top: 2px;
      stroke: var(--error-color);
      fill: none;
      stroke-width: 2.2;
    }

    /* Alert */
    .alert-box {
      display: none;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 14px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 18px;
    }

    .alert-box.error {
      display: flex;
      background: #fee2e2;
      border: 1px solid #fca5a5;
      color: #991b1b;
    }

    .alert-box.success {
      display: flex;
      background: #ecfdf5;
      border: 1px solid #a7f3d0;
      color: #065f46;
    }

    /* Buttons */
    .btn {
      width: 100%;
      height: 48px;
      border-radius: 24px;
      font-family: inherit;
      font-size: 14.5px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s ease;
      outline: none;
      border: none;
      text-decoration: none;
    }

    .btn-primary {
      background-color: var(--primary-color);
      color: #ffffff;
      box-shadow: 0 4px 14px rgba(30, 74, 56, 0.2);
    }

    .btn-primary:hover:not(:disabled) {
      background-color: var(--primary-hover);
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(30, 74, 56, 0.28);
    }

    .btn-secondary {
      background-color: #ffffff;
      color: #475569;
      border: 1.5px solid #e2e8f0;
      margin-top: 10px;
    }

    .btn-secondary:hover {
      background-color: #f8fafc;
    }

    .spinner {
      width: 18px;
      height: 18px;
      border: 2.5px solid rgba(255, 255, 255, 0.3);
      border-top-color: #ffffff;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      margin-right: 8px;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
</head>
<body>

  <div class="app-container">

    <!-- Branding Header -->
    <div class="brand-header">
      <div class="brand-logo-badge">
        <svg viewBox="0 0 24 24">
          <path d="M12 2L2 7l10 5 10-5-10-5z" />
          <path d="M2 12l10 5 10-5" />
          <path d="M2 17l10 5 10-5" />
        </svg>
      </div>
      <h1 class="brand-title">EcoAsset</h1>
      <p class="brand-subtitle">Municipal Facility Management</p>
    </div>

    <!-- Reset Card -->
    <div class="auth-card">
      <h2 class="card-title">Set New Password</h2>
      <p class="card-desc">Enter your new secure password below to regain access to your account.</p>

      <div id="userBadge" class="user-pill" style="display: none;">
        <span>👤</span>
        <span id="userEmailText"></span>
      </div>

      <div id="alertBox" class="alert-box">
        <span id="alertMsg"></span>
      </div>

      <form id="resetForm" novalidate>

        <!-- New Password Field -->
        <div class="form-group" id="passGroup">
          <label for="passInput" class="form-label">New Password</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <svg viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
              </svg>
            </span>
            <input
              type="password"
              id="passInput"
              class="form-input has-toggle"
              placeholder="Min 8 characters"
              required
            />
            <button
              type="button"
              id="togglePassBtn"
              class="password-toggle-btn"
              aria-label="Toggle password visibility"
              title="Show / Hide password"
            >
              <svg class="eye-off-icon" viewBox="0 0 24 24">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                <line x1="1" y1="1" x2="23" y2="23" />
              </svg>
              <svg class="eye-on-icon" viewBox="0 0 24 24" style="display: none;">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
            </button>
          </div>
          <div class="error-feedback" id="passError">
            <svg viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span id="passErrorText">Password is required.</span>
          </div>
        </div>

        <!-- Confirm Password Field -->
        <div class="form-group" id="confirmPassGroup">
          <label for="confirmPassInput" class="form-label">Confirm New Password</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <svg viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
              </svg>
            </span>
            <input
              type="password"
              id="confirmPassInput"
              class="form-input has-toggle"
              placeholder="Re-enter new password"
              required
            />
            <button
              type="button"
              id="toggleConfirmPassBtn"
              class="password-toggle-btn"
              aria-label="Toggle password visibility"
              title="Show / Hide password"
            >
              <svg class="eye-off-icon" viewBox="0 0 24 24">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                <line x1="1" y1="1" x2="23" y2="23" />
              </svg>
              <svg class="eye-on-icon" viewBox="0 0 24 24" style="display: none;">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
            </button>
          </div>
          <div class="error-feedback" id="confirmPassError">
            <svg viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span id="confirmPassErrorText">Passwords do not match.</span>
          </div>
        </div>

        <!-- Submit Button -->
        <button type="submit" id="submitBtn" class="btn btn-primary">
          <span id="spinner" class="spinner" style="display: none;"></span>
          <span id="btnLabel">Update Password</span>
        </button>

        <!-- Back to Login -->
        <a href="login.php" class="btn btn-secondary">
          Back to Sign In
        </a>

      </form>
    </div>

  </div>

  <script>
    // Extract query parameters (?token=...&email=...)
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token') || '';
    const email = urlParams.get('email') || '';

    const resetForm = document.getElementById('resetForm');
    const passInput = document.getElementById('passInput');
    const confirmPassInput = document.getElementById('confirmPassInput');
    const passGroup = document.getElementById('passGroup');
    const confirmPassGroup = document.getElementById('confirmPassGroup');
    const passErrorText = document.getElementById('passErrorText');
    const confirmPassErrorText = document.getElementById('confirmPassErrorText');
    const alertBox = document.getElementById('alertBox');
    const alertMsg = document.getElementById('alertMsg');
    const submitBtn = document.getElementById('submitBtn');
    const spinner = document.getElementById('spinner');
    const btnLabel = document.getElementById('btnLabel');
    const userBadge = document.getElementById('userBadge');
    const userEmailText = document.getElementById('userEmailText');

    if (email) {
      userEmailText.textContent = email;
      userBadge.style.display = 'inline-flex';
    }

    // Check token existence
    if (!token) {
      showAlert('No password reset token provided. Please request a new link from the login page.', 'error');
      submitBtn.disabled = true;
    }

    function showAlert(msg, type = 'error') {
      alertBox.className = `alert-box ${type}`;
      alertMsg.textContent = msg;
      alertBox.style.display = 'flex';
    }

    function clearErrors() {
      passGroup.classList.remove('has-error');
      confirmPassGroup.classList.remove('has-error');
      alertBox.style.display = 'none';
    }

    passInput.addEventListener('input', clearErrors);
    confirmPassInput.addEventListener('input', clearErrors);

    resetForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors();

      const newPass = passInput.value;
      const confirmPass = confirmPassInput.value;
      let hasError = false;

      if (!newPass) {
        passGroup.classList.add('has-error');
        passErrorText.textContent = 'Password is required.';
        hasError = true;
      } else if (newPass.length < 8) {
        passGroup.classList.add('has-error');
        passErrorText.textContent = 'Password must be at least 8 characters.';
        hasError = true;
      }

      if (!confirmPass) {
        confirmPassGroup.classList.add('has-error');
        confirmPassErrorText.textContent = 'Please confirm your new password.';
        hasError = true;
      } else if (newPass !== confirmPass) {
        confirmPassGroup.classList.add('has-error');
        confirmPassErrorText.textContent = 'Passwords do not match.';
        hasError = true;
      }

      if (hasError) return;

      submitBtn.disabled = true;
      spinner.style.display = 'inline-block';
      btnLabel.textContent = 'Updating...';

      try {
        const res = await fetch('/api/index.php/api/v1/auth/reset-password', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            token: token,
            email: email,
            new_password: newPass
          })
        });

        const result = await res.json();

        if (res.ok && result.status === 'success') {
          showAlert('Password updated successfully! Redirecting to login...', 'success');
          setTimeout(() => {
            window.location.href = 'login.php';
          }, 1500);
        } else {
          showAlert(result.message || 'Unable to reset password.', 'error');
        }
      } catch (err) {
        showAlert('Network error: Unable to connect to server.', 'error');
      } finally {
        submitBtn.disabled = false;
        spinner.style.display = 'none';
        btnLabel.textContent = 'Update Password';
      }
    });

    // Password Visibility (Masking / Unmasking) Toggle Handler
    function initPasswordToggle(btnId, inputId) {
      const btn = document.getElementById(btnId);
      const input = document.getElementById(inputId);
      if (!btn || !input) return;

      const eyeOff = btn.querySelector('.eye-off-icon');
      const eyeOn = btn.querySelector('.eye-on-icon');

      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';

        if (eyeOff && eyeOn) {
          eyeOff.style.display = isPassword ? 'none' : 'block';
          eyeOn.style.display = isPassword ? 'block' : 'none';
        }

        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        input.focus();
      });
    }

    initPasswordToggle('togglePassBtn', 'passInput');
    initPasswordToggle('toggleConfirmPassBtn', 'confirmPassInput');
  </script>
</body>
</html>
