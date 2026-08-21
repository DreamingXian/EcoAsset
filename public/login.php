<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EcoAsset - Sign In</title>
  <!-- Google Fonts: Plus Jakarta Sans / Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    :root {
      --primary-color: #1e4a38;
      --primary-hover: #16382b;
      --primary-light: #e8f3ee;
      --text-main: #1e293b;
      --text-muted: #64748b;
      --text-placeholder: #94a3b8;
      --input-bg: #f1f5f9;
      --input-border-default: transparent;
      --error-color: #ef4444;
      --error-bg: #fff5f5;
      --error-border: #fca5a5;
      --teal-link: #0d9488;
      --teal-link-hover: #0f766e;
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

    /* Screen Wrapper / Mobile Device Simulation Container */
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
      transition: transform 0.2s ease;
    }

    .brand-logo-badge:hover {
      transform: scale(1.03);
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

    /* Main Auth Card */
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
      margin-bottom: 22px;
      letter-spacing: -0.2px;
    }

    /* Form Groups */
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
      transition: color 0.15s ease;
    }

    .input-icon svg {
      width: 19px;
      height: 19px;
      stroke: currentColor;
      fill: none;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
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

    .form-input::placeholder {
      color: var(--text-placeholder);
      font-weight: 400;
    }

    .form-input:focus {
      background-color: #ffffff;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 4px rgba(30, 74, 56, 0.08);
    }

    .form-input:focus ~ .input-icon {
      color: var(--primary-color);
    }

    /* Error States */
    .form-group.has-error .form-input {
      border-color: var(--error-border);
      background-color: var(--error-bg);
      color: #991b1b;
    }

    .form-group.has-error .form-input:focus {
      border-color: var(--error-color);
      box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
    }

    .form-group.has-error .input-icon {
      color: var(--error-color);
    }

    .error-feedback {
      display: none;
      align-items: flex-start;
      gap: 6px;
      font-size: 12.5px;
      color: var(--error-color);
      margin-top: 6px;
      line-height: 1.4;
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
      stroke: currentColor;
      fill: none;
      stroke-width: 2.2;
    }

    /* Top General Alert Message */
    .general-alert {
      display: none;
      align-items: center;
      gap: 8px;
      padding: 12px 14px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 18px;
      animation: fadeIn 0.3s ease;
    }

    .general-alert.error {
      display: flex;
      background-color: #fee2e2;
      color: #991b1b;
      border: 1px solid #fca5a5;
    }

    .general-alert.success {
      display: flex;
      background-color: #ecfdf5;
      color: #065f46;
      border: 1px solid #a7f3d0;
    }

    .general-alert svg {
      width: 18px;
      height: 18px;
      flex-shrink: 0;
    }

    /* Links Row */
    .link-row {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      margin-top: -4px;
      margin-bottom: 20px;
    }

    .forgot-link {
      font-size: 12.5px;
      color: var(--text-muted);
      text-decoration: none;
      font-weight: 500;
    }

    .forgot-link .highlight {
      color: var(--teal-link);
      font-weight: 700;
      cursor: pointer;
      transition: color 0.15s ease;
    }

    .forgot-link .highlight:hover {
      color: var(--teal-link-hover);
      text-decoration: underline;
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
      text-decoration: none;
    }

    .btn-primary {
      background-color: var(--primary-color);
      color: #ffffff;
      border: none;
      box-shadow: 0 4px 14px rgba(30, 74, 56, 0.2);
    }

    .btn-primary:hover:not(:disabled) {
      background-color: var(--primary-hover);
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(30, 74, 56, 0.28);
    }

    .btn-primary:active:not(:disabled) {
      transform: translateY(0);
    }

    .btn-primary:disabled {
      opacity: 0.75;
      cursor: not-allowed;
    }

    .btn-secondary {
      background-color: #ffffff;
      color: var(--primary-color);
      border: 1.5px solid var(--primary-color);
    }

    .btn-secondary:hover {
      background-color: var(--primary-light);
    }

    /* Divider */
    .divider {
      display: flex;
      align-items: center;
      margin: 18px 0;
      color: #94a3b8;
      font-size: 12.5px;
      font-weight: 500;
    }

    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background-color: #e2e8f0;
    }

    .divider span {
      padding: 0 12px;
    }

    /* Spinner Animation */
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

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-4px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <div class="app-container">

    <!-- Branding Header -->
    <div class="brand-header">
      <div class="brand-logo-badge" title="EcoAsset">
        <!-- 3-tier Stack Layers SVG Icon -->
        <svg viewBox="0 0 24 24">
          <path d="M12 2L2 7l10 5 10-5-10-5z" />
          <path d="M2 12l10 5 10-5" />
          <path d="M2 17l10 5 10-5" />
        </svg>
      </div>
      <h1 class="brand-title">EcoAsset</h1>
      <p class="brand-subtitle">Municipal Facility Management</p>
    </div>

    <!-- Sign In Card -->
    <div class="auth-card">
      <h2 class="card-title">Sign in to your account</h2>

      <!-- General Global Alert (for credentials mismatch / system error / success) -->
      <div id="generalAlert" class="general-alert" role="alert">
        <svg id="alertIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span id="alertMessage"></span>
      </div>

      <form id="loginForm" novalidate>

        <!-- Email Field -->
        <div class="form-group" id="emailGroup">
          <label for="emailInput" class="form-label">Email Address</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <!-- Envelope Mail Icon -->
              <svg viewBox="0 0 24 24">
                <rect x="3" y="5" width="18" height="14" rx="2" />
                <polyline points="3 7 12 13 21 7" />
              </svg>
            </span>
            <input
              type="email"
              id="emailInput"
              class="form-input"
              placeholder="you@example.com"
              autocomplete="email"
              required
            />
          </div>
          <div class="error-feedback" id="emailError">
            <svg viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span id="emailErrorText">Email address is required.</span>
          </div>
        </div>

        <!-- Password Field -->
        <div class="form-group" id="passwordGroup">
          <label for="passwordInput" class="form-label">Password</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <!-- Padlock Lock Icon -->
              <svg viewBox="0 0 24 24">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
              </svg>
            </span>
            <input
              type="password"
              id="passwordInput"
              class="form-input"
              placeholder="Enter your password"
              autocomplete="current-password"
              required
            />
          </div>
          <div class="error-feedback" id="passwordError">
            <svg viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span id="passwordErrorText">Password is required.</span>
          </div>
        </div>

        <!-- Forgot Password Link -->
        <div class="link-row">
          <span class="forgot-link">
            Forgot Password? <span class="highlight" onclick="alert('Forgot password functionality will be available in the next release.')">Click Here</span>
          </span>
        </div>

        <!-- Log In Button -->
        <button type="submit" id="loginBtn" class="btn btn-primary">
          <span id="btnSpinner" class="spinner" style="display: none;"></span>
          <span id="btnText">Log In</span>
        </button>

        <!-- Divider -->
        <div class="divider">
          <span>or</span>
        </div>

        <!-- Sign Up Button -->
        <button type="button" class="btn btn-secondary" onclick="alert('Sign up flow will be opened in the next module.')">
          Sign Up
        </button>

      </form>
    </div>

  </div>

  <script>
    const form = document.getElementById('loginForm');
    const emailInput = document.getElementById('emailInput');
    const passwordInput = document.getElementById('passwordInput');
    const emailGroup = document.getElementById('emailGroup');
    const passwordGroup = document.getElementById('passwordGroup');
    const emailErrorText = document.getElementById('emailErrorText');
    const passwordErrorText = document.getElementById('passwordErrorText');
    const generalAlert = document.getElementById('generalAlert');
    const alertMessage = document.getElementById('alertMessage');
    const loginBtn = document.getElementById('loginBtn');
    const btnSpinner = document.getElementById('btnSpinner');
    const btnText = document.getElementById('btnText');

    // Email regex validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // Reset error state for a field
    function clearFieldError(group) {
      group.classList.remove('has-error');
    }

    function setFieldError(group, textElem, message) {
      group.classList.add('has-error');
      textElem.textContent = message;
    }

    function hideGeneralAlert() {
      generalAlert.style.display = 'none';
      generalAlert.className = 'general-alert';
    }

    function showGeneralAlert(message, type = 'error') {
      generalAlert.className = `general-alert ${type}`;
      alertMessage.textContent = message;
      generalAlert.style.display = 'flex';
    }

    // Real-time input clearing
    emailInput.addEventListener('input', () => {
      clearFieldError(emailGroup);
      hideGeneralAlert();
    });

    passwordInput.addEventListener('input', () => {
      clearFieldError(passwordGroup);
      hideGeneralAlert();
    });

    // Form Submission Handling
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      hideGeneralAlert();

      const email = emailInput.value.trim();
      const password = passwordInput.value;
      let hasError = false;

      // 1. Validate Email
      if (!email) {
        setFieldError(emailGroup, emailErrorText, 'Email address is required.');
        hasError = true;
      } else if (!emailRegex.test(email)) {
        setFieldError(emailGroup, emailErrorText, 'Please enter a valid email address (e.g. you@example.com).');
        hasError = true;
      } else {
        clearFieldError(emailGroup);
      }

      // 2. Validate Password
      if (!password) {
        setFieldError(passwordGroup, passwordErrorText, 'Password is required.');
        hasError = true;
      } else {
        clearFieldError(passwordGroup);
      }

      if (hasError) {
        return;
      }

      // 3. Trigger API Login Request
      setLoading(true);

      try {
        const response = await fetch('/api/index.php/api/v1/auth/login', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ email, password })
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
          // Store token and user details
          localStorage.setItem('ecoasset_token', result.data.token);
          localStorage.setItem('ecoasset_user', JSON.stringify(result.data.user));

          showGeneralAlert(`Welcome back, ${result.data.user.full_name}! Redirecting...`, 'success');
          
          setTimeout(() => {
            // Role-based routing or default dashboard
            const role = result.data.user.role;
            alert(`Login successful as ${role}! Token saved.`);
          }, 800);

        } else {
          // Handle specific backend error codes & messages
          const errorMsg = result.message || 'Unable to log in. Please try again.';
          const errorDetails = result.details?.errors || {};

          // Deactivated account scenario
          if (response.status === 403 || errorDetails.email?.includes('deactivated')) {
            setFieldError(emailGroup, emailErrorText, 'Oops, your account has been deactivated and cannot login.');
          }
          // Email required / invalid email format from server
          else if (errorDetails.email) {
            setFieldError(emailGroup, emailErrorText, errorDetails.email);
          }
          // Password required
          else if (errorDetails.password) {
            setFieldError(passwordGroup, passwordErrorText, errorDetails.password);
          }
          // Credential mismatch (Incorrect email or password)
          else if (response.status === 401 || errorDetails.general) {
            showGeneralAlert('Incorrect email or password. Please try again.', 'error');
          } else {
            showGeneralAlert(errorMsg, 'error');
          }
        }
      } catch (err) {
        showGeneralAlert('Network error: Unable to connect to server. Please check your connection.', 'error');
      } finally {
        setLoading(false);
      }
    });

    function setLoading(isLoading) {
      loginBtn.disabled = isLoading;
      if (isLoading) {
        btnSpinner.style.display = 'inline-block';
        btnText.textContent = 'Logging In...';
      } else {
        btnSpinner.style.display = 'none';
        btnText.textContent = 'Log In';
      }
    }
  </script>
</body>
</html>
