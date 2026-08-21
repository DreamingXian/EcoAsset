<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EcoAsset - Sign In</title>
  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    :root {
      --primary-color: #1e4a38;
      --primary-hover: #16382b;
      --primary-light: #e8f3ee;
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
      position: relative;
      overflow-x: hidden;
    }

    /* Screen Wrapper */
    .app-container {
      width: 100%;
      max-width: 412px;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 16px 8px 24px 8px;
      transition: filter 0.25s ease;
    }

    body.modal-open .app-container {
      filter: blur(2px);
    }

    /* Top Floating Success Notification Banner (Screenshot 6) */
    .top-notification {
      width: 100%;
      background-color: #eafaf1;
      border: 1px solid #b7ebd1;
      color: #0d6832;
      border-radius: 16px;
      padding: 14px 18px;
      font-size: 14px;
      font-weight: 600;
      display: none;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
      box-shadow: 0 4px 16px rgba(16, 185, 129, 0.12);
      animation: fadeInDown 0.35s ease;
    }

    .top-notification.show {
      display: flex;
    }

    .top-notification svg {
      width: 22px;
      height: 22px;
      flex-shrink: 0;
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
      font-size: 12px;
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
      stroke: var(--error-color);
      fill: none;
      stroke-width: 2.2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .error-action-link {
      color: #b91c1c;
      font-weight: 700;
      text-decoration: underline;
      cursor: pointer;
      margin-left: 4px;
    }

    .error-action-link:hover {
      color: #7f1d1d;
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
      stroke: currentColor;
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

    .btn-teal {
      background-color: var(--teal-btn);
      color: #ffffff;
      border: none;
      box-shadow: 0 4px 14px rgba(0, 168, 132, 0.25);
    }

    .btn-teal:hover:not(:disabled) {
      background-color: var(--teal-btn-hover);
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(0, 168, 132, 0.35);
    }

    .btn-teal:active:not(:disabled) {
      transform: translateY(0);
    }

    .btn-teal:disabled {
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

    .btn-cancel {
      background-color: #ffffff;
      color: #475569;
      border: 1.5px solid #e2e8f0;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
    }

    .btn-cancel:hover {
      background-color: #f8fafc;
      border-color: #cbd5e1;
      color: #1e293b;
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

    /* Modal Sheet Backdrop & Container */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background-color: rgba(15, 23, 42, 0.5);
      backdrop-filter: blur(4px);
      -webkit-backdrop-filter: blur(4px);
      z-index: 100;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.25s ease;
      padding: 0;
    }

    .modal-backdrop.active {
      opacity: 1;
      pointer-events: auto;
    }

    .bottom-sheet {
      width: 100%;
      max-width: 412px;
      background: #ffffff;
      border-radius: 28px 28px 0 0;
      padding: 24px 24px 28px 24px;
      box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.15);
      transform: translateY(100%);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal-backdrop.active .bottom-sheet {
      transform: translateY(0);
    }

    .sheet-handle {
      width: 36px;
      height: 4px;
      background-color: #cbd5e1;
      border-radius: 2px;
      margin: 0 auto 14px auto;
    }

    .sheet-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
    }

    .sheet-title-box {
      display: flex;
      flex-direction: column;
    }

    .sheet-title {
      font-size: 18px;
      font-weight: 700;
      color: #1e293b;
      letter-spacing: -0.2px;
    }

    .sheet-subtitle {
      font-size: 13px;
      color: var(--text-muted);
      font-weight: 500;
      margin-top: 3px;
    }

    .sheet-close-btn {
      background: none;
      border: none;
      color: #94a3b8;
      font-size: 18px;
      cursor: pointer;
      padding: 4px 6px;
      border-radius: 8px;
      line-height: 1;
      transition: color 0.15s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .sheet-close-btn:hover {
      color: #334155;
    }

    .btn-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-top: 22px;
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

    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-12px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

  <!-- Screen Wrapper -->
  <div class="app-container">

    <!-- Top Floating Success Banner (Screenshot 6) -->
    <div id="topNotification" class="top-notification" role="status">
      <!-- Green Checkmark in Solid Circle SVG -->
      <svg viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" fill="#00a884"></circle>
        <path d="M8 12.5l2.5 2.5L16 9" stroke="#ffffff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
      <span id="topNotificationText">Reset link sent! Check your inbox.</span>
    </div>

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

      <!-- General Global Alert -->
      <div id="generalAlert" class="general-alert" role="alert">
        <!-- Exclamation inside Circle SVG -->
        <svg id="alertIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
            <!-- Exclamation inside Circle SVG (!) -->
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
            <!-- Exclamation inside Circle SVG (!) -->
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
            Forgot Password? <span id="openForgotBtn" class="highlight">Click Here</span>
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

        <!-- Sign Up Button (Triggers Bottom Sheet Modal) -->
        <button type="button" id="openSignupBtn" class="btn btn-secondary">
          Sign Up
        </button>

      </form>
    </div>

  </div>

  <!-- Sign Up Bottom Modal Sheet -->
  <div id="signupModal" class="modal-backdrop" role="dialog" aria-modal="true">
    <div class="bottom-sheet">
      <div class="sheet-handle"></div>

      <div class="sheet-header">
        <div class="sheet-title-box">
          <h3 class="sheet-title">Create Account</h3>
          <p class="sheet-subtitle">Join EcoAsset as a public user</p>
        </div>
        <button type="button" id="closeSignupBtn" class="sheet-close-btn" aria-label="Close modal">
          <!-- Close X SVG -->
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>

      <form id="signupForm" novalidate>

        <!-- Full Name Field -->
        <div class="form-group" id="signupNameGroup">
          <label for="signupNameInput" class="form-label">Full Name</label>
          <div class="input-wrapper">
            <span class="input-icon">
              <!-- User Icon -->
              <svg viewBox="0 0 24 24">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
            </span>
            <input
              type="text"
              id="signupNameInput"
              class="form-input"
              placeholder="Full Name"
              autocomplete="name"
              required
            />
          </div>
          <div class="error-feedback" id="signupNameError">
            <!-- Exclamation inside Circle SVG (!) -->
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span id="signupNameErrorText">Full name is required.</span>
          </div>
        </div>

        <!-- Email Field -->
        <div class="form-group" id="signupEmailGroup">
          <label for="signupEmailInput" class="form-label">Email Address</label>
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
              id="signupEmailInput"
              class="form-input"
              placeholder="you@example.com"
              autocomplete="email"
              required
            />
          </div>
          <div class="error-feedback" id="signupEmailError">
            <!-- Exclamation inside Circle SVG (!) -->
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span id="signupEmailErrorText">Email address is required.</span>
          </div>
        </div>

        <!-- Password Field -->
        <div class="form-group" id="signupPasswordGroup">
          <label for="signupPasswordInput" class="form-label">Password</label>
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
              id="signupPasswordInput"
              class="form-input"
              placeholder="Min 8 characters"
              autocomplete="new-password"
              required
            />
          </div>
          <div class="error-feedback" id="signupPasswordError">
            <!-- Exclamation inside Circle SVG (!) -->
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span id="signupPasswordErrorText">Password is required.</span>
          </div>
        </div>

        <!-- Action Buttons Row -->
        <div class="btn-row">
          <button type="submit" id="signupSubmitBtn" class="btn btn-primary">
            <span id="signupBtnSpinner" class="spinner" style="display: none;"></span>
            <span id="signupBtnText">Create Account</span>
          </button>
          <button type="button" id="cancelSignupBtn" class="btn btn-cancel">
            Cancel
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- Reset Password Bottom Modal Sheet (Screenshots 1-5) -->
  <div id="forgotModal" class="modal-backdrop" role="dialog" aria-modal="true">
    <div class="bottom-sheet">
      <div class="sheet-handle"></div>

      <div class="sheet-header">
        <div class="sheet-title-box">
          <h3 class="sheet-title">Reset Password</h3>
          <p class="sheet-subtitle">We will send a reset link to your email</p>
        </div>
        <button type="button" id="closeForgotBtn" class="sheet-close-btn" aria-label="Close modal">
          <!-- Close X SVG -->
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>

      <form id="forgotForm" novalidate>

        <!-- Email Field -->
        <div class="form-group" id="forgotEmailGroup">
          <label for="forgotEmailInput" class="form-label">Email Address</label>
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
              id="forgotEmailInput"
              class="form-input"
              placeholder="you@example.com"
              autocomplete="email"
              required
            />
          </div>
          <div class="error-feedback" id="forgotEmailError">
            <!-- Exclamation inside Circle SVG (!) -->
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span id="forgotEmailErrorText">Email address is required.</span>
          </div>
        </div>

        <!-- Action Buttons Row -->
        <div class="btn-row">
          <button type="submit" id="forgotSubmitBtn" class="btn btn-primary">
            <span id="forgotBtnSpinner" class="spinner" style="display: none;"></span>
            <span id="forgotBtnText">Send Reset Link</span>
          </button>
          <button type="button" id="cancelForgotBtn" class="btn btn-cancel">
            Cancel
          </button>
        </div>

      </form>
    </div>
  </div>

  <script>
    // Top Notification
    const topNotification = document.getElementById('topNotification');
    const topNotificationText = document.getElementById('topNotificationText');

    function showTopNotification(message, duration = 6000) {
      topNotificationText.textContent = message;
      topNotification.classList.add('show');
      setTimeout(() => {
        topNotification.classList.remove('show');
      }, duration);
    }

    // Elements - Login
    const loginForm = document.getElementById('loginForm');
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

    // Elements - Signup Modal
    const signupModal = document.getElementById('signupModal');
    const openSignupBtn = document.getElementById('openSignupBtn');
    const closeSignupBtn = document.getElementById('closeSignupBtn');
    const cancelSignupBtn = document.getElementById('cancelSignupBtn');
    const signupForm = document.getElementById('signupForm');
    const signupNameInput = document.getElementById('signupNameInput');
    const signupEmailInput = document.getElementById('signupEmailInput');
    const signupPasswordInput = document.getElementById('signupPasswordInput');
    const signupNameGroup = document.getElementById('signupNameGroup');
    const signupEmailGroup = document.getElementById('signupEmailGroup');
    const signupPasswordGroup = document.getElementById('signupPasswordGroup');
    const signupNameErrorText = document.getElementById('signupNameErrorText');
    const signupEmailErrorText = document.getElementById('signupEmailErrorText');
    const signupPasswordErrorText = document.getElementById('signupPasswordErrorText');
    const signupSubmitBtn = document.getElementById('signupSubmitBtn');
    const signupBtnSpinner = document.getElementById('signupBtnSpinner');
    const signupBtnText = document.getElementById('signupBtnText');

    // Elements - Forgot Password Modal
    const forgotModal = document.getElementById('forgotModal');
    const openForgotBtn = document.getElementById('openForgotBtn');
    const closeForgotBtn = document.getElementById('closeForgotBtn');
    const cancelForgotBtn = document.getElementById('cancelForgotBtn');
    const forgotForm = document.getElementById('forgotForm');
    const forgotEmailInput = document.getElementById('forgotEmailInput');
    const forgotEmailGroup = document.getElementById('forgotEmailGroup');
    const forgotEmailErrorText = document.getElementById('forgotEmailErrorText');
    const forgotSubmitBtn = document.getElementById('forgotSubmitBtn');
    const forgotBtnSpinner = document.getElementById('forgotBtnSpinner');
    const forgotBtnText = document.getElementById('forgotBtnText');

    // Email regex validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // Helpers for error states
    function clearFieldError(group) {
      group.classList.remove('has-error');
    }

    function setFieldError(group, textElem, message, htmlContent = null) {
      group.classList.add('has-error');
      if (htmlContent) {
        textElem.innerHTML = htmlContent;
      } else {
        textElem.textContent = message;
      }
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

    // Modal Controls - Signup
    function openSignupModal() {
      closeForgotModal();
      signupModal.classList.add('active');
      document.body.classList.add('modal-open');
      signupNameInput.focus();
    }

    function closeSignupModal() {
      signupModal.classList.remove('active');
      if (!forgotModal.classList.contains('active')) {
        document.body.classList.remove('modal-open');
      }
      clearFieldError(signupNameGroup);
      clearFieldError(signupEmailGroup);
      clearFieldError(signupPasswordGroup);
    }

    // Modal Controls - Forgot Password
    function openForgotModal() {
      closeSignupModal();
      forgotModal.classList.add('active');
      document.body.classList.add('modal-open');
      forgotEmailInput.focus();
    }

    function closeForgotModal() {
      forgotModal.classList.remove('active');
      if (!signupModal.classList.contains('active')) {
        document.body.classList.remove('modal-open');
      }
      clearFieldError(forgotEmailGroup);
    }

    openSignupBtn.addEventListener('click', openSignupModal);
    closeSignupBtn.addEventListener('click', closeSignupModal);
    cancelSignupBtn.addEventListener('click', closeSignupModal);

    openForgotBtn.addEventListener('click', openForgotModal);
    closeForgotBtn.addEventListener('click', closeForgotModal);
    cancelForgotBtn.addEventListener('click', closeForgotModal);

    // Close when clicking backdrop
    signupModal.addEventListener('click', (e) => {
      if (e.target === signupModal) closeSignupModal();
    });

    forgotModal.addEventListener('click', (e) => {
      if (e.target === forgotModal) closeForgotModal();
    });

    // Real-time input clearing - Login
    emailInput.addEventListener('input', () => {
      clearFieldError(emailGroup);
      hideGeneralAlert();
    });

    passwordInput.addEventListener('input', () => {
      clearFieldError(passwordGroup);
      hideGeneralAlert();
    });

    // Real-time input clearing - Signup
    signupNameInput.addEventListener('input', () => clearFieldError(signupNameGroup));
    signupEmailInput.addEventListener('input', () => clearFieldError(signupEmailGroup));
    signupPasswordInput.addEventListener('input', () => clearFieldError(signupPasswordGroup));

    // Real-time input clearing - Forgot Password
    forgotEmailInput.addEventListener('input', () => clearFieldError(forgotEmailGroup));

    // Handle "Go to Login" link inside signup error message
    document.addEventListener('click', (e) => {
      if (e.target && e.target.id === 'modalGoToLogin') {
        e.preventDefault();
        const existingEmail = signupEmailInput.value.trim();
        closeSignupModal();
        emailInput.value = existingEmail;
        clearFieldError(emailGroup);
        passwordInput.focus();
      }
    });

    // Handle "Sign Up" link inside reset password error message (Screenshot 4)
    document.addEventListener('click', (e) => {
      if (e.target && e.target.id === 'resetGoToSignup') {
        e.preventDefault();
        const typedEmail = forgotEmailInput.value.trim();
        closeForgotModal();
        openSignupModal();
        signupEmailInput.value = typedEmail;
        clearFieldError(signupEmailGroup);
        signupNameInput.focus();
      }
    });

    // -------------------------------------------------------------
    // Login Form Submit
    // -------------------------------------------------------------
    loginForm.addEventListener('submit', async (e) => {
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

      if (hasError) return;

      // 3. API Call
      setLoginLoading(true);

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
          localStorage.setItem('ecoasset_token', result.data.token);
          localStorage.setItem('ecoasset_user', JSON.stringify(result.data.user));

          showGeneralAlert(`Welcome back, ${result.data.user.full_name}!`, 'success');
          setTimeout(() => {
            alert(`Logged in successfully as ${result.data.user.role}! Token saved.`);
          }, 600);

        } else {
          const errorMsg = result.message || 'Unable to log in. Please try again.';
          const errorDetails = result.details?.errors || {};

          if (response.status === 403 || errorDetails.email?.includes('deactivated')) {
            setFieldError(emailGroup, emailErrorText, 'Oops, your account has been deactivated and cannot login.');
          } else if (errorDetails.email) {
            setFieldError(emailGroup, emailErrorText, errorDetails.email);
          } else if (errorDetails.password) {
            setFieldError(passwordGroup, passwordErrorText, errorDetails.password);
          } else if (response.status === 401 || errorDetails.general) {
            showGeneralAlert('Incorrect email or password. Please try again.', 'error');
          } else {
            showGeneralAlert(errorMsg, 'error');
          }
        }
      } catch (err) {
        showGeneralAlert('Network error: Unable to connect to server.', 'error');
      } finally {
        setLoginLoading(false);
      }
    });

    function setLoginLoading(isLoading) {
      loginBtn.disabled = isLoading;
      if (isLoading) {
        btnSpinner.style.display = 'inline-block';
        btnText.textContent = 'Logging In...';
      } else {
        btnSpinner.style.display = 'none';
        btnText.textContent = 'Log In';
      }
    }

    // -------------------------------------------------------------
    // Signup Form Submit
    // -------------------------------------------------------------
    signupForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const fullName = signupNameInput.value.trim();
      const email = signupEmailInput.value.trim();
      const password = signupPasswordInput.value;
      let hasError = false;

      // 1. Validate Name
      if (!fullName) {
        setFieldError(signupNameGroup, signupNameErrorText, 'Full name is required.');
        hasError = true;
      } else {
        clearFieldError(signupNameGroup);
      }

      // 2. Validate Email
      if (!email) {
        setFieldError(signupEmailGroup, signupEmailErrorText, 'Email address is required.');
        hasError = true;
      } else if (!emailRegex.test(email)) {
        setFieldError(signupEmailGroup, signupEmailErrorText, 'Please enter a valid email address (e.g. you@example.com).');
        hasError = true;
      } else {
        clearFieldError(signupEmailGroup);
      }

      // 3. Validate Password
      if (!password) {
        setFieldError(signupPasswordGroup, signupPasswordErrorText, 'Password is required.');
        hasError = true;
      } else if (password.length < 8) {
        setFieldError(signupPasswordGroup, signupPasswordErrorText, 'Password must be at least 8 characters.');
        hasError = true;
      } else {
        clearFieldError(signupPasswordGroup);
      }

      if (hasError) return;

      // 4. API Registration Call
      setSignupLoading(true);

      try {
        const response = await fetch('/api/index.php/api/v1/auth/signup', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            full_name: fullName,
            email: email,
            password: password
          })
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
          localStorage.setItem('ecoasset_token', result.data.token);
          localStorage.setItem('ecoasset_user', JSON.stringify(result.data.user));

          closeSignupModal();
          signupForm.reset();

          showGeneralAlert(`Welcome to EcoAsset, ${result.data.user.full_name}! Account created.`, 'success');
          setTimeout(() => {
            alert(`Account registered successfully as ${result.data.user.role}! Token saved.`);
          }, 600);

        } else {
          const errorDetails = result.details?.errors || {};
          const isDeactivated = result.details?.deactivated;
          const exists = result.details?.exists;

          if (isDeactivated) {
            setFieldError(
              signupEmailGroup,
              signupEmailErrorText,
              'Account exists but has been deactivated, please try again with another email address.'
            );
          } else if (exists || errorDetails.email?.includes('exists')) {
            setFieldError(
              signupEmailGroup,
              signupEmailErrorText,
              '',
              `Email exists, please click on login instead. <a href="#" id="modalGoToLogin" class="error-action-link">Go to Login</a>`
            );
          } else if (errorDetails.email) {
            setFieldError(signupEmailGroup, signupEmailErrorText, errorDetails.email);
          }

          if (errorDetails.full_name) {
            setFieldError(signupNameGroup, signupNameErrorText, errorDetails.full_name);
          }

          if (errorDetails.password) {
            setFieldError(signupPasswordGroup, signupPasswordErrorText, errorDetails.password);
          }
        }
      } catch (err) {
        setFieldError(signupEmailGroup, signupEmailErrorText, 'Network error. Please try again.');
      } finally {
        setSignupLoading(false);
      }
    });

    function setSignupLoading(isLoading) {
      signupSubmitBtn.disabled = isLoading;
      if (isLoading) {
        signupBtnSpinner.style.display = 'inline-block';
        signupBtnText.textContent = 'Creating...';
      } else {
        signupBtnSpinner.style.display = 'none';
        signupBtnText.textContent = 'Create Account';
      }
    }

    // -------------------------------------------------------------
    // Forgot Password Form Submit (Screenshots 1-6)
    // -------------------------------------------------------------
    forgotForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const email = forgotEmailInput.value.trim();
      let hasError = false;

      // 1. Validate Email Input
      if (!email) {
        setFieldError(forgotEmailGroup, forgotEmailErrorText, 'Email address is required.');
        hasError = true;
      } else if (!emailRegex.test(email)) {
        setFieldError(forgotEmailGroup, forgotEmailErrorText, 'Please enter a valid email address (e.g. you@example.com).');
        hasError = true;
      } else {
        clearFieldError(forgotEmailGroup);
      }

      if (hasError) return;

      // 2. API Request
      setForgotLoading(true);

      try {
        const response = await fetch('/api/index.php/api/v1/auth/forgot-password', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ email })
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
          // Success State (Screenshot 6): Close sheet and display top green notification
          closeForgotModal();
          forgotForm.reset();
          
          const resetUrl = result.data?.reset_link || '#';
          console.log('%c🔗 [EcoAsset DEV] Password Reset Link:', 'color: #00a884; font-weight: bold; font-size: 14px;', window.location.origin + resetUrl);
          
          // Display top notification with clickable shortcut for dev testing
          topNotificationText.innerHTML = `Reset link sent! Check your inbox.`;
          topNotification.classList.add('show');
          setTimeout(() => {
            topNotification.classList.remove('show');
          }, 10000);

        } else {
          const errorDetails = result.details?.errors || {};
          const isDeactivated = result.details?.deactivated;
          const isNotFound = result.details?.not_found;

          // Deactivated user error (Screenshot 5)
          if (isDeactivated || response.status === 403) {
            setFieldError(
              forgotEmailGroup,
              forgotEmailErrorText,
              'Your account has been deactivated, password cannot be reset.'
            );
          }
          // Account not found error (Screenshot 4)
          else if (isNotFound || response.status === 404 || errorDetails.email?.includes('Account not found')) {
            setFieldError(
              forgotEmailGroup,
              forgotEmailErrorText,
              '',
              `Account not found, try sign up instead? <a href="#" id="resetGoToSignup" class="error-action-link">Sign Up</a>`
            );
          }
          // Invalid format or other email error
          else if (errorDetails.email) {
            setFieldError(forgotEmailGroup, forgotEmailErrorText, errorDetails.email);
          } else {
            setFieldError(forgotEmailGroup, forgotEmailErrorText, result.message || 'Unable to send reset link.');
          }
        }
      } catch (err) {
        setFieldError(forgotEmailGroup, forgotEmailErrorText, 'Network error: Unable to connect to server.');
      } finally {
        setForgotLoading(false);
      }
    });

    function setForgotLoading(isLoading) {
      forgotSubmitBtn.disabled = isLoading;
      if (isLoading) {
        forgotBtnSpinner.style.display = 'inline-block';
        forgotBtnText.textContent = 'Sending...';
      } else {
        forgotBtnSpinner.style.display = 'none';
        forgotBtnText.textContent = 'Send Reset Link';
      }
    }
  </script>
</body>
</html>
