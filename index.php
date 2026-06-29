<?php
require_once __DIR__ . '/database.php';

// Now you can use the $conn variable to run queries
// Example: $result = $conn->query("SELECT * FROM users");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SchoDex — Library Management System</title>
<link rel="stylesheet" href="style.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
</head>
<body>

<!-- ════════════════════════════════════════════════
     TOAST CONTAINER
════════════════════════════════════════════════ -->
<div id="toast-container"></div>

<!-- ════════════════════════════════════════════════
     LOGIN SCREEN
════════════════════════════════════════════════ -->
<div id="login-screen">
  <div class="login-left">
    <div class="login-logo">
      <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="8" y="8" width="48" height="48" rx="4" fill="#c9a84c" opacity=".15"/>
        <path d="M16 16h10v32H16V16z" fill="#c9a84c"/>
        <path d="M30 16h18v4H30zM30 24h18v4H30zM30 32h14v4H30zM30 42h18v6H30z" fill="rgba(245,240,232,.5)"/>
        <circle cx="48" cy="48" r="10" fill="#c9a84c"/>
        <path d="M44 48h8M48 44v8" stroke="#0d0d0d" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <h1>SchoDex</h1>
      <p>Library Management System</p>
    </div>
    <p class="login-quote">"A library is not a luxury but one of the necessities of life."<br>— Henry Ward Beecher</p>
  </div>
  <div class="login-right">

    <!-- LOGIN PANEL -->
    <div class="login-panel active" id="panel-login">
      <h2>Welcome back</h2>
      <p class="subtitle">Sign in to your account to continue</p>
      <div class="form-group w-full">
        <label class="form-label">Username</label>
        <input class="form-input" id="login-username" type="text" placeholder="Enter username" autocomplete="username">
        <div class="field-error" id="login-username-error" aria-live="polite"></div>
      </div>
      <div class="form-group w-full">
        <label class="form-label">Password</label>
        <div class="password-field">
          <input class="form-input" id="login-password" type="password" placeholder="Enter password" autocomplete="current-password" onkeydown="if(event.key==='Enter')doLogin()">
          <button class="password-toggle password-toggle-hidden" id="login-password-toggle" type="button" aria-label="Show password" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <div class="field-error" id="login-password-error" aria-live="polite"></div>
      </div>
      <div class="form-error" id="login-form-error" aria-live="polite"></div>
      <label class="flex gap-1 center" style="justify-content:flex-start;font-size:.82rem;color:var(--muted);width:100%;margin-top:4px">
        <input id="login-remember" type="checkbox">
        Remember me
      </label>
      <button class="btn btn-primary btn-full mt-2" onclick="doLogin()">Sign In</button>
      <p class="login-switch mt-2">New student? <a onclick="showPanel('signup')">Create an account</a></p>
    </div>

    <!-- SIGNUP PANEL -->
    <div class="login-panel" id="panel-signup">
      <h2>Create Account</h2>
      <p class="subtitle">Register as a new library member</p>
      <div class="form-group w-full">
        <label class="form-label">First Name *</label>
        <input class="form-input" id="signup-firstname" type="text" placeholder="e.g. Juan">
        <div class="field-error" id="signup-firstname-error" aria-live="polite"></div>
      </div>
      <div class="form-group w-full">
        <label class="form-label">Last Name *</label>
        <input class="form-input" id="signup-lastname" type="text" placeholder="e.g. dela Cruz">
        <div class="field-error" id="signup-lastname-error" aria-live="polite"></div>
      </div>
      <div class="form-group w-full">
        <label class="form-label">Email *</label>
        <input class="form-input" id="signup-email" type="email" placeholder="you@email.com">
        <div class="field-error" id="signup-email-error" aria-live="polite"></div>
      </div>
      <div class="form-group w-full">
        <label class="form-label">Username *</label>
        <input class="form-input" id="signup-username" type="text" placeholder="Choose a username">
        <div class="field-error" id="signup-username-error" aria-live="polite"></div>
      </div>
      <div class="form-group w-full">
        <label class="form-label">Password *</label>
        <div class="password-field">
          <input class="form-input" id="signup-password" type="password" placeholder="Create a strong password" oninput="checkPasswordRequirements()">
          <button class="password-toggle password-toggle-hidden" id="signup-password-toggle" type="button" aria-label="Show password" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <div id="password-requirements" style="margin-top:8px;font-size:0.75rem;color:var(--muted)">
          <div id="req-length" style="display:flex;align-items:center;gap:6px;margin:4px 0"><span id="check-length">&#9744;</span> 12-30 characters</div>
          <div id="req-lowercase" style="display:flex;align-items:center;gap:6px;margin:4px 0"><span id="check-lowercase">&#9744;</span> Lowercase (a-z)</div>
          <div id="req-uppercase" style="display:flex;align-items:center;gap:6px;margin:4px 0"><span id="check-uppercase">&#9744;</span> Uppercase (A-Z)</div>
          <div id="req-number" style="display:flex;align-items:center;gap:6px;margin:4px 0"><span id="check-number">&#9744;</span> Number (0-9)</div>
          <div id="req-special" style="display:flex;align-items:center;gap:6px;margin:4px 0"><span id="check-special">&#9744;</span> Special (!@#$%^&*)</div>
        </div>
        <div class="field-error" id="signup-password-error" aria-live="polite"></div>
      </div>
      <div class="form-group w-full">
        <label class="form-label">Confirm Password *</label>
        <div class="password-field">
          <input class="form-input" id="signup-confirm-password" type="password" placeholder="Confirm password" onkeydown="if(event.key==='Enter')doSignup()">
          <button class="password-toggle password-toggle-hidden" id="signup-confirm-password-toggle" type="button" aria-label="Show password" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <div class="field-error" id="signup-confirm-password-error" aria-live="polite"></div>
      </div>
      <div class="form-error" id="signup-form-error" aria-live="polite"></div>
      <button class="btn btn-gold btn-full mt-2" onclick="doSignup()">Create Account</button>
      <p class="login-switch mt-2">Already have an account? <a onclick="showPanel('login')">Sign in</a></p>
    </div>

  </div>
</div>

<!-- ════════════════════════════════════════════════
     APP SHELL
════════════════════════════════════════════════ -->
<div id="app">
  <!-- SIDEBAR -->
  <aside id="sidebar">
    <div class="sidebar-brand">
      <h2>SchoDex</h2>
      <p>Library System</p>
    </div>
    <div class="sidebar-user">
      <img class="sidebar-avatar" id="sidebar-avatar" src="" alt="avatar">
      <div class="sidebar-user-info">
        <div class="name" id="sidebar-name">User</div>
        <div class="role" id="sidebar-role">Role</div>
      </div>
    </div>
    <nav class="sidebar-nav" id="sidebar-nav"></nav>
    <div class="sidebar-footer">
      <button class="btn btn-ghost btn-sm btn-full" onclick="doLogout()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        Sign Out
      </button>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main id="main">
    <header id="admin-global-header" class="admin-global-header hidden">
      <div>
        <div class="admin-header-kicker">Admin Panel</div>
        <div class="admin-header-title" id="admin-header-title">Dashboard</div>
      </div>
      <div class="admin-header-actions">
        <div class="admin-header-action-wrap">
          <button class="admin-header-icon-btn" type="button" aria-label="Notifications" onclick="toggleAdminHeaderDropdown('notifications')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
            <span class="admin-header-badge hidden" id="admin-header-notification-badge"></span>
          </button>
          <div class="admin-header-dropdown hidden" id="admin-header-notifications"></div>
        </div>
        <div class="admin-header-action-wrap">
          <button class="admin-header-icon-btn" type="button" aria-label="Settings" onclick="toggleAdminHeaderDropdown('settings')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
          </button>
          <div class="admin-header-dropdown hidden" id="admin-header-settings"></div>
        </div>
        <div class="admin-header-action-wrap">
          <button class="admin-header-icon-btn" type="button" aria-label="Profile" onclick="toggleAdminHeaderDropdown('profile')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21a8 8 0 10-16 0"/><circle cx="12" cy="7" r="4"/></svg>
          </button>
          <div class="admin-header-dropdown hidden" id="admin-header-profile"></div>
        </div>
      </div>
    </header>

    <!-- ── ADMIN PAGES ── -->

    <!-- Dashboard -->
    <div class="page" id="page-dashboard">
      <div class="page-header">
        <div>
          <div class="page-title">Dashboard</div>
          <div class="page-subtitle">Good morning — here's an overview of the library.</div>
        </div>
      </div>
      <div class="stats-grid" id="admin-stats"></div>
      <div class="card">
        <div class="card-header"><span class="card-title">Monthly Borrowing Report</span></div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">
            <div>
              <div style="text-align:center;margin-bottom:20px">
                <div id="today-count" style="font-size:2.5rem;font-weight:bold;color:var(--gold)">0</div>
                <div style="color:var(--muted);margin-top:4px">Books borrowed this month</div>
              </div>
              <div id="today-stats" style="font-size:0.875rem;color:var(--muted);line-height:1.8"></div>
            </div>
            <div>
              <canvas id="borrowing-chart" style="max-height:250px"></canvas>
            </div>
          </div>
          <div id="today-breakdown" style="margin-top:20px;border-top:1px solid var(--border);padding-top:16px"></div>
        </div>
      </div>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">
        <div class="card">
          <div class="card-header"><span class="card-title">Recent Activity</span></div>
          <div class="card-body" id="recent-activity" style="padding:0"></div>
        </div>
        <div class="card">
          <div class="card-header"><span class="card-title">Quick Stats</span></div>
          <div class="card-body" id="quick-stats-widget"></div>
        </div>
      </div>
    </div>

    <!-- Borrow Requests (Admin) -->
    <div class="page" id="page-borrow-requests"></div>

    <!-- Reservations (Admin) -->
    <div class="page" id="page-reservations"></div>

    <!-- Genres (Admin) -->
    <div class="page" id="page-genres"></div>

    <!-- Authors (Admin) -->
    <div class="page" id="page-authors"></div>

    <!-- Publishers (Admin) -->
    <div class="page" id="page-publishers"></div>

    <!-- Books Catalog (Admin) -->
    <div class="page" id="page-books">
      <div class="page-header">
        <div>
          <div class="page-title">Books Management</div>
          <div class="page-subtitle">Manage books and physical copies.</div>
        </div>
        <div class="page-actions">
          <button class="btn btn-gold btn-sm" onclick="openModal('modal-add-book')">+ Add Book</button>
        </div>
      </div>
      <div id="admin-books-grid" class="book-grid"></div>
    </div>

    <!-- Users -->
    <div class="page" id="page-members">
      <div class="page-header">
        <div><div class="page-title">Users</div><div class="page-subtitle">Review library user accounts and status.</div></div>
        <div class="page-actions">
          <button class="btn btn-primary btn-sm" onclick="openCreatePrivilegedAccountModal()">+ Add Admin</button>
          <div class="search-bar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" placeholder="Search users by name, email, or phone…" id="member-search" oninput="renderMembers()">
          </div>
        </div>
      </div>
      <div class="stats-grid" id="users-summary"></div>
      <div class="card">
        <div class="card-body" style="padding:0">
          <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>User</th><th>Role</th><th>Phone</th><th>Joined</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="members-table"></tbody>
          </table></div>
        </div>
      </div>
    </div>

    <!-- Book Loans (Admin) -->
    <div class="page" id="page-transactions">
      <div class="page-header">
        <div><div class="page-title">Book Loans</div><div class="page-subtitle">All borrowing activity and returns.</div></div>
        <div class="page-actions">
          <div class="search-bar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" placeholder="Search transactions…" id="transaction-search" oninput="renderTransactions()">
          </div>
        </div>
      </div>
      <div class="stats-grid" id="transaction-stats"></div>
      <div class="card">
        <div class="card-body" style="padding:0">
          <div class="table-wrap"><table>
            <thead><tr><th>Loan ID</th><th>Book</th><th>User</th><th>Checkout Date</th><th>Due Date</th><th>Return Date</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="transactions-table"></tbody>
          </table></div>
        </div>
      </div>
    </div>

    <!-- Fines (Admin) -->
    <div class="page" id="page-fines">
      <div class="page-header">
        <div><div class="page-title">Fine Management</div><div class="page-subtitle">Track and manage member fines.</div></div>
        <div class="page-actions">
          <button class="btn btn-gold btn-sm" onclick="openFineRuleModal()">+ Add Fine Rule</button>
        </div>
      </div>
      <div class="card mb-3">
        <div class="card-header"><span class="card-title">Fine Rules</span></div>
        <div class="card-body" style="padding:0" id="fine-rules-panel"></div>
      </div>
      <div class="stats-grid" id="fine-stats"></div>
      <div class="card mb-3"><div class="card-body" id="fine-filter-panel"></div></div>
      <div class="card">
        <div class="card-body" style="padding:0">
          <div class="table-wrap"><table>
            <thead><tr><th>Fine ID</th><th>User</th><th>Book</th><th>Type</th><th>Amount</th><th>Paid</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody id="fines-table"></tbody>
          </table></div>
        </div>
      </div>
    </div>

    <!-- Notifications (Admin) -->
    <div class="page" id="page-reports"></div>

    <!-- Notifications (Admin) -->
    <div class="page" id="page-notifications">
      <div class="page-header">
        <div><div class="page-title">Notifications</div><div class="page-subtitle">Overdue books and reservation alerts.</div></div>
        <div class="page-actions">
          <div class="search-bar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" placeholder="Search notifications…" id="notification-search" oninput="renderNotifications()">
          </div>
          <button class="btn btn-ghost btn-sm" onclick="clearAllNotifications()">Mark All Read</button>
        </div>
      </div>
      <div id="notifications-list"></div>
    </div>

    <!-- Settings (Admin) -->
    <div class="page" id="page-settings">
      <div class="page-header"><div class="page-title">Account Settings</div></div>
      <div id="settings-content"></div>
    </div>

    <!-- ── STUDENT PAGES ── -->

    <!-- Student Dashboard -->
    <div class="page" id="page-student-dashboard">
      <div class="page-header">
        <div><div class="page-title" id="student-greeting">My Library</div><div class="page-subtitle">Your borrowing summary and activity.</div></div>
      </div>
      <div id="student-fine-alert"></div>
      <div class="stats-grid" id="student-stats"></div>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:20px">
        <div class="card">
          <div class="card-header"><span class="card-title">Currently Borrowed</span></div>
          <div class="card-body" style="padding:0" id="student-borrowed-widget"></div>
        </div>
        <div class="card">
          <div class="card-header"><span class="card-title">My Reservations</span></div>
          <div class="card-body" style="padding:0" id="student-reservations-widget"></div>
        </div>
      </div>
    </div>

    <!-- Catalog (Student) -->
    <div class="page" id="page-catalog">
      <div class="page-header">
        <div><div class="page-title">Browse Books</div><div class="page-subtitle">Browse our collection</div></div>
      </div>
      <div class="card mb-3">
        <div class="card-body">
          <div class="filter-control-row member-catalog-filters">
            <div class="search-bar member-catalog-search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
              <input type="text" placeholder="Search title, author, ISBN" id="student-book-search" oninput="renderStudentCatalog()">
            </div>
            <select class="form-select" id="student-category-filter" onchange="renderStudentCatalog()">
              <option value="">All Genres</option>
            </select>
            <select class="form-select" id="student-availability-filter" onchange="renderStudentCatalog()">
              <option value="all">All Availability</option>
              <option value="available">Available</option>
              <option value="unavailable">Unavailable</option>
              <option value="reserved">Reserved by Me</option>
            </select>
            <select class="form-select" id="student-sort-filter" onchange="renderStudentCatalog()">
              <option value="title">Title A-Z</option>
              <option value="author">Author A-Z</option>
              <option value="newest">Newest First</option>
              <option value="available">Most Available</option>
            </select>
            <button class="btn btn-ghost btn-sm" onclick="clearStudentCatalogFilters()">Clear Filters</button>
          </div>
        </div>
      </div>
      <div id="student-catalog-grid" class="book-grid member-catalog-grid"></div>
    </div>

    <!-- My Loans (Student) -->
    <div class="page" id="page-my-loans">
      <div class="page-header">
        <div><div class="page-title">My Loans</div><div class="page-subtitle">Track your borrowed books and loan history.</div></div>
        <div class="page-actions">
          <div class="search-bar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" placeholder="Search my loans…" id="my-loans-search" oninput="renderMyLoans()">
          </div>
        </div>
      </div>
      <div class="member-loans-tabs" role="tablist" aria-label="Loan status filters">
        <button class="active" type="button" data-loan-tab="all" onclick="setMyLoansTab('all')">All</button>
        <button type="button" data-loan-tab="active" onclick="setMyLoansTab('active')">Active</button>
        <button type="button" data-loan-tab="overdue" onclick="setMyLoansTab('overdue')">Overdue</button>
        <button type="button" data-loan-tab="returned" onclick="setMyLoansTab('returned')">Returned</button>
        <button type="button" data-loan-tab="lost" onclick="setMyLoansTab('lost')">Lost</button>
        <button type="button" data-loan-tab="damaged" onclick="setMyLoansTab('damaged')">Damaged</button>
      </div>
      <div id="my-loans-content" class="member-loans-grid"></div>
    </div>

    <!-- My Reservations (Student) -->
    <div class="page" id="page-my-reservations">
      <div class="page-header">
        <div><div class="page-title">My Reservations</div><div class="page-subtitle">Track reserved books and pickup availability.</div></div>
      </div>
      <div class="stats-grid" id="member-reservation-stats"></div>
      <div class="member-reservation-tabs" role="tablist" aria-label="Reservation filters">
        <button class="active" type="button" data-reservation-tab="all" onclick="setMyReservationsTab('all')">All Reservations</button>
        <button type="button" data-reservation-tab="active" onclick="setMyReservationsTab('active')">Active</button>
        <button type="button" data-reservation-tab="completed" onclick="setMyReservationsTab('completed')">Completed</button>
      </div>
      <div id="member-reservations-content" class="member-reservations-grid"></div>
    </div>

    <!-- My Fines (Student) -->
    <div class="page" id="page-my-fines">
      <div class="page-header">
        <div><div class="page-title">My Fines</div><div class="page-subtitle">Outstanding and paid fines.</div></div>
        <div class="page-actions">
          <div class="search-bar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" placeholder="Search my fines…" id="my-fines-search" oninput="renderMyFines()">
          </div>
        </div>
      </div>
      <div class="stats-grid" id="my-fines-stats"></div>
      <div class="card mb-3"><div class="card-body" id="my-fines-filters"></div></div>
      <div id="my-fines-content"></div>
    </div>

    <!-- Notifications (Student) -->
    <div class="page" id="page-student-notifications">
      <div class="page-header">
        <div><div class="page-title">Notifications</div></div>
        <div class="page-actions">
          <div class="search-bar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" placeholder="Search notifications…" id="student-notification-search" oninput="renderStudentNotifications()">
          </div>
          <button class="btn btn-ghost btn-sm" onclick="clearAllNotifications()">Mark All Read</button>
        </div>
      </div>
      <div id="student-notifications-list"></div>
    </div>

    <!-- Settings (Student) -->
    <div class="page" id="page-student-settings">
      <div class="page-header"><div><div class="page-title">Profile</div><div class="page-subtitle">Manage your account information and security.</div></div></div>
      <div id="student-settings-content"></div>
    </div>

    <!-- Settings (Student) -->
    <div class="page" id="page-student-preferences">
      <div class="page-header"><div><div class="page-title">Settings</div><div class="page-subtitle">Notification and communication preferences.</div></div></div>
      <div id="student-preferences-content"></div>
    </div>

  </main>
</div>

<!-- ═══════════════════ MODALS ═══════════════════ -->

<!-- Add/Edit Book Modal -->
<div class="modal-overlay hidden" id="modal-add-book">
  <div class="modal modal-lg">
    <button class="modal-close" onclick="closeModal('modal-add-book')">&#10005;</button>
    <div class="modal-title" id="book-modal-title">Add New Book</div>
    <input type="hidden" id="edit-book-id">
    <div class="form-grid">
      <div class="form-group"><label class="form-label">Title *</label><input class="form-input" id="book-title" placeholder="Book title"></div>
      <div class="form-group"><label class="form-label">Author *</label><input class="form-input" id="book-author" list="book-author-options" placeholder="Author name"><datalist id="book-author-options"></datalist></div>
      <div class="form-group"><label class="form-label">ISBN</label><input class="form-input" id="book-isbn" placeholder="978-..."></div>
      <div class="form-group"><label class="form-label">Subject / Genre</label><input class="form-input" id="book-subject" placeholder="Fiction, Science..."></div>
      <div class="form-group"><label class="form-label">Publisher</label><input class="form-input" id="book-publisher" list="book-publisher-options" placeholder="Publisher name"><datalist id="book-publisher-options"></datalist></div>
      <div class="form-group"><label class="form-label">Year</label><input class="form-input" id="book-year" type="number" placeholder="2024"></div>
      <div class="form-group"><label class="form-label">Number of Copies *</label><input class="form-input" id="book-copies" type="number" min="1" placeholder="1"></div>
      <div class="form-group"><label class="form-label">Late Fee Per Day (₱)</label><input class="form-input" id="book-late-fee" type="number" min="0" placeholder="20"></div>
      <div class="form-group"><label class="form-label">Replacement Value (₱) — Lost or Damaged</label><input class="form-input" id="book-fine" type="number" min="0" placeholder="500"></div>
      <div class="form-group"><label class="form-label">Rack Number</label><input class="form-input" id="book-rack" placeholder="A-12"></div>
      <div class="form-group"><label class="form-label">Cover Image</label>
        <div style="display:flex;gap:8px;align-items:center">
          <input class="form-input" id="book-cover-file" type="file" accept="image/*" style="flex:1">
          <span style="color:var(--muted)">or</span>
          <input class="form-input" id="book-cover-url" placeholder="https://..." style="flex:1">
        </div>
        <div id="cover-validation-error" style="color:var(--rust);font-size:0.75rem;margin-top:4px;display:none">Please provide either a file upload or a URL.</div>
      </div>
    </div>
    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" id="book-desc" placeholder="Brief description…"></textarea></div>
    <div class="flex gap-2 mt-3">
      <button class="btn btn-primary btn-full" onclick="saveBook()">Save Book</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-add-book')">Cancel</button>
    </div>
  </div>
</div>

<!-- Fine Rule Modal -->
<div class="modal-overlay hidden" id="modal-fine-rule">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-fine-rule')">&#10005;</button>
    <div class="modal-title" id="fine-rule-modal-title">Add Fine Rule</div>
    <input type="hidden" id="fine-rule-id">
    <div class="form-group">
      <label class="form-label">Rule Name</label>
      <input class="form-input" id="fine-rule-name" placeholder="Enter rule name">
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Type</label>
        <select class="form-select" id="fine-rule-type">
          <option value="overdue">Overdue</option>
          <option value="damaged">Damaged</option>
          <option value="lost">Lost</option>
          <option value="manual">Manual</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Amount (₱)</label>
        <input class="form-input" id="fine-rule-amount" type="number" min="0" step="0.01" placeholder="0.00">
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Grace Days</label>
        <input class="form-input" id="fine-rule-grace-days" type="number" min="0" step="1" value="0">
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" id="fine-rule-status">
          <option value="1">Active</option>
          <option value="0">Disabled</option>
        </select>
      </div>
    </div>
    <div class="flex gap-2">
      <button class="btn btn-gold btn-full" onclick="saveFineRule()">Save Rule</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-fine-rule')">Cancel</button>
    </div>
  </div>
</div>

<!-- Genre Modal -->
<div class="modal-overlay hidden" id="modal-genre">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-genre')">&#10005;</button>
    <div class="modal-title" id="genre-modal-title">Add Genre</div>
    <input type="hidden" id="genre-id">
    <div class="form-group">
      <label class="form-label">Genre Name</label>
      <input class="form-input" id="genre-name" placeholder="Enter genre name">
    </div>
    <div class="form-group">
      <label class="form-label">Description</label>
      <textarea class="form-textarea" id="genre-description" disabled placeholder="Not supported by current categories API"></textarea>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Parent Genre</label>
        <select class="form-select" id="genre-parent" disabled><option>Not supported</option></select>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select class="form-select" id="genre-status" disabled><option>Active</option></select>
      </div>
    </div>
    <p class="text-muted mb-3" style="font-size:.78rem">Only Genre Name is saved. Description, parent genre, and status need backend/schema support.</p>
    <div class="flex gap-2">
      <button class="btn btn-gold btn-full" onclick="saveGenre()">Save Genre</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-genre')">Cancel</button>
    </div>
  </div>
</div>

<!-- Reference Modal -->
<div class="modal-overlay hidden" id="modal-reference">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-reference')">&#10005;</button>
    <div class="modal-title" id="reference-modal-title">Add Reference</div>
    <input type="hidden" id="reference-type">
    <input type="hidden" id="reference-id">
    <div class="form-group">
      <label class="form-label" id="reference-name-label">Name</label>
      <input class="form-input" id="reference-name" placeholder="Enter name">
    </div>
    <div class="flex gap-2">
      <button class="btn btn-gold btn-full" onclick="saveReference()">Save</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-reference')">Cancel</button>
    </div>
  </div>
</div>

<!-- Book Detail Modal -->
<div class="modal-overlay hidden" id="modal-book-detail">
  <div class="modal modal-lg">
    <button class="modal-close" onclick="closeModal('modal-book-detail')">&#10005;</button>
    <div id="book-detail-content"></div>
  </div>
</div>

<!-- Notification Detail Modal -->
<div class="modal-overlay hidden" id="modal-notification-detail">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-notification-detail')">&#10005;</button>
    <div id="notification-detail-content"></div>
  </div>
</div>

<!-- Issue Book Modal -->
<div class="modal-overlay hidden" id="modal-issue-book">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-issue-book')">&#10005;</button>
    <div class="modal-title">Issue Book</div>
    <div class="form-group"><label class="form-label">Select Member</label>
      <select class="form-select" id="issue-member"></select>
    </div>
    <input type="hidden" id="issue-book-id">
    <p class="text-muted" style="font-size:.8rem;margin-bottom:16px">Loan duration: <strong>10 days</strong>. Member must have fewer than 5 active loans.</p>
    <div class="flex gap-2">
      <button class="btn btn-gold btn-full" onclick="issueBook()">Issue Book</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-issue-book')">Cancel</button>
    </div>
  </div>
</div>

<!-- Mark Lost Modal -->
<div class="modal-overlay hidden" id="modal-mark-lost">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-mark-lost')">&#10005;</button>
    <div class="modal-title">Mark as Lost or Damaged</div>
    <p class="text-muted mb-3" style="font-size:.875rem">This will close the loan and immediately charge the member the book's full <strong>Replacement Value</strong>. The member will be blocked from borrowing until this fine is paid.</p>
    <div id="lost-book-info" class="mb-3"></div>
    <input type="hidden" id="lost-book-id">
    <input type="hidden" id="lost-type" value="lost">
    <div class="form-group"><label class="form-label">Select Transaction (who has it)</label>
      <select class="form-select" id="lost-transaction"></select>
    </div>
    <div class="form-group"><label class="form-label">Replacement Fee (₱) — charged immediately</label><input class="form-input" id="lost-fine-amount" type="number" placeholder="Auto-filled from book's replacement value"></div>
    <div class="flex gap-2 mt-3">
      <button class="btn btn-danger btn-full" onclick="markLost(this)">Confirm — Lost or Damaged</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-mark-lost')">Cancel</button>
    </div>
  </div>
</div>

<!-- Member Detail Modal -->
<div class="modal-overlay hidden" id="modal-member-detail">
  <div class="modal modal-lg">
    <button class="modal-close" onclick="closeModal('modal-member-detail')">&#10005;</button>
    <div id="member-detail-content"></div>
  </div>
</div>

<div class="modal-overlay hidden" id="modal-user-account">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-user-account')">&#10005;</button>
    <div class="modal-title" id="user-account-modal-title">Manage Account</div>
    <input type="hidden" id="user-account-id">
    <div class="form-grid">
      <div class="form-group"><label class="form-label">First Name</label><input class="form-input" id="user-account-first-name"></div>
      <div class="form-group"><label class="form-label">Last Name</label><input class="form-input" id="user-account-last-name"></div>
      <div class="form-group"><label class="form-label">Email</label><input class="form-input" id="user-account-email" type="email"></div>
      <div class="form-group"><label class="form-label">Username</label><input class="form-input" id="user-account-username"></div>
      <div class="form-group"><label class="form-label">Phone</label><input class="form-input" id="user-account-phone"></div>
      <div class="form-group"><label class="form-label">Role</label>
        <select class="form-select" id="user-account-role">
          <option value="member">Member</option>
          <option value="admin">Administrator</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Password</label><input class="form-input" id="user-account-password" type="password" placeholder="Required for new accounts"></div>
    </div>
    <div id="user-account-error" class="form-error mt-2"></div>
    <div class="flex gap-2 mt-3">
      <button class="btn btn-primary btn-full" onclick="saveManagedUserAccount()">Save Account</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-user-account')">Cancel</button>
    </div>
  </div>
</div>

<!-- Confirm Return Modal -->
<div class="modal-overlay hidden" id="modal-return">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-return')">&#10005;</button>
    <div class="modal-title">Return Book</div>
    <p class="text-muted mb-3" style="font-size:.875rem">Confirm the return of this book. If overdue, a late fine will be applied based on the book's daily rate.</p>
    <div id="return-info" class="mb-3"></div>
    <input type="hidden" id="return-tx-id">
    <div class="flex gap-2 mt-3">
      <button class="btn btn-sage btn-full" onclick="confirmReturn(this)">Confirm Return</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-return')">Cancel</button>
    </div>
  </div>
</div>

<!-- Edit Loan Modal -->
<div class="modal-overlay hidden" id="modal-edit-loan">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-edit-loan')">&#10005;</button>
    <div class="modal-title">Edit Loan</div>
    <div id="edit-loan-info" class="mb-3"></div>
    <input type="hidden" id="edit-loan-id">
    <div class="form-group">
      <label class="form-label">Checkout Date</label>
      <input class="form-input" type="date" id="edit-loan-checkout-date">
    </div>
    <div class="form-group">
      <label class="form-label">Due Date</label>
      <input class="form-input" type="date" id="edit-loan-due-date">
    </div>
    <div class="form-group" id="edit-loan-return-group">
      <label class="form-label">Return Date</label>
      <input class="form-input" type="date" id="edit-loan-return-date">
    </div>
    <div class="form-group">
      <label class="form-label">Status</label>
      <select class="form-select" id="edit-loan-status" disabled>
        <option value="borrowed">Borrowed</option>
        <option value="returned">Returned</option>
        <option value="lost">Lost</option>
        <option value="damaged">Damaged</option>
      </select>
      <div class="text-muted" style="font-size:.75rem;margin-top:5px">Use Return, Damage, or Loss actions to change status.</div>
    </div>
    <div class="form-error" id="edit-loan-error" aria-live="polite"></div>
    <div class="flex gap-2 mt-3">
      <button class="btn btn-gold btn-full" onclick="saveLoanEdit()">Save Changes</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-edit-loan')">Cancel</button>
    </div>
  </div>
</div>

<!-- Student Borrow Modal -->
<div class="modal-overlay hidden" id="modal-student-borrow">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('modal-student-borrow')">&#10005;</button>
    <div class="modal-title">Borrow Book</div>
    <div id="student-borrow-info" class="mb-3"></div>
    <input type="hidden" id="student-borrow-book-id">
    <div class="form-group">
      <label class="form-label">Choose Return Date <span style="color:var(--muted);font-weight:400;text-transform:none">(max 10 days from today)</span></label>
      <input class="form-input" type="date" id="student-return-date">
      <div style="font-size:.75rem;color:var(--muted);margin-top:5px" id="borrow-days-display"></div>
      <div style="font-size:.75rem;color:var(--rust);margin-top:5px;display:none" id="borrow-date-error"></div>
    </div>
    <div class="flex gap-2 mt-3">
      <button class="btn btn-gold btn-full" id="student-borrow-submit" onclick="studentBorrow()">Confirm Borrow</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-student-borrow')">Cancel</button>
    </div>
  </div>
</div>
<script src="app.js" defer></script>
</body>
</html>
