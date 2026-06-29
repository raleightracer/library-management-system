// ══════════════════════════════════════════════
// DATA LAYER
// ══════════════════════════════════════════════

const DEFAULT_COVERS = [
  'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&q=80',
  'https://images.unsplash.com/photo-1512820790803-83ca734da794?w=300&q=80',
  'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=300&q=80',
  'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?w=300&q=80',
  'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&q=80',
  'https://images.unsplash.com/photo-1519682337058-a94d519337bc?w=300&q=80',
  'https://images.unsplash.com/photo-1524578271613-d550eacf6090?w=300&q=80',
  'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=300&q=80',
];

// ══════════════════════════════════════════════
// AUTH
// ══════════════════════════════════════════════

let currentUser = null;

function showPanel(name) {
    document.querySelectorAll('.login-panel').forEach(p=>p.classList.remove('active'));
    document.getElementById('panel-'+name).classList.add('active');
    if(name==='login') {
      clearLoginErrors();
      updatePasswordToggle('login-password');
    }
    // clear signup fields when switching
    if(name==='signup') {
      ['signup-firstname','signup-lastname','signup-email','signup-username','signup-password','signup-confirm-password'].forEach(id=>{ 
        const el=document.getElementById(id); 
        if(el) el.value=''; 
    });
    clearSignupErrors();
    ['signup-password', 'signup-confirm-password'].forEach(resetPasswordField);
    document.getElementById('password-requirements').style.display = 'none';
  }
}

window.addEventListener('load', ()=>{
  initLoginValidation();
  initSignupValidation();
  initPasswordToggles();
  initStore();
});

window.addEventListener('pageshow', refreshAfterPaymentReturn);
window.addEventListener('focus', refreshAfterPaymentReturn);
document.addEventListener('visibilitychange', () => {
  if(!document.hidden) refreshAfterPaymentReturn();
});

function setFieldError(inputId, message) {
  const input = document.getElementById(inputId);
  const error = document.getElementById(`${inputId}-error`);
  if (input) {
    input.classList.add('input-error');
    input.setAttribute('aria-invalid', 'true');
  }
  if (error) error.textContent = message;
}

function clearFieldError(inputId) {
  const input = document.getElementById(inputId);
  const error = document.getElementById(`${inputId}-error`);
  if (input) {
    input.classList.remove('input-error');
    input.removeAttribute('aria-invalid');
  }
  if (error) error.textContent = '';
}

function clearLoginErrors() {
  clearFieldError('login-username');
  clearFieldError('login-password');
  const formError = document.getElementById('login-form-error');
  if (formError) formError.textContent = '';
}

function showLoginFormError(message) {
  const formError = document.getElementById('login-form-error');
  if (formError) formError.textContent = message;
}

function initLoginValidation() {
  ['login-username', 'login-password'].forEach(id => {
    const input = document.getElementById(id);
    if (!input) return;
    input.addEventListener('input', () => {
      clearFieldError(id);
      showLoginFormError('');
      if (id === 'login-password') updatePasswordToggle(id);
    });
  });
}

function clearSignupErrors() {
  [
    'signup-firstname',
    'signup-lastname',
    'signup-email',
    'signup-username',
    'signup-password',
    'signup-confirm-password',
  ].forEach(clearFieldError);
  showSignupFormError('');
}

function showSignupFormError(message) {
  const formError = document.getElementById('signup-form-error');
  if (formError) formError.textContent = message;
}

function initSignupValidation() {
  [
    'signup-firstname',
    'signup-lastname',
    'signup-email',
    'signup-username',
    'signup-password',
    'signup-confirm-password',
  ].forEach(id => {
    const input = document.getElementById(id);
    if (!input) return;
    input.addEventListener('input', () => {
      clearFieldError(id);
      showSignupFormError('');
      if (id === 'signup-password' || id === 'signup-confirm-password') {
        updatePasswordToggle(id);
        validateSignupPasswordsInline();
      }
    });
  });
}

function initPasswordToggles() {
  ['login-password', 'signup-password', 'signup-confirm-password'].forEach(inputId => {
    const input = document.getElementById(inputId);
    const button = ensurePasswordToggleButton(inputId);
    if (!input || !button) return;
    button.type = 'button';
    button.addEventListener('click', () => togglePasswordVisibility(inputId));
    input.addEventListener('input', () => updatePasswordToggle(inputId));
    resetPasswordField(inputId);
  });
}

function ensurePasswordToggleButton(inputId) {
  const input = document.getElementById(inputId);
  if (!input) return null;
  let button = document.getElementById(`${inputId}-toggle`);
  if (button) return button;

  button = document.createElement('button');
  button.id = `${inputId}-toggle`;
  button.type = 'button';
  button.className = 'password-toggle password-toggle-hidden';
  button.hidden = true;
  button.setAttribute('aria-label', 'Show password');
  button.innerHTML = passwordToggleIcon(false);

  let wrapper = input.closest('.password-field');
  if (!wrapper) {
    wrapper = document.createElement('div');
    wrapper.className = 'password-field';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
  }
  wrapper.appendChild(button);
  return button;
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isLetterChar(char) {
  return char.toLocaleLowerCase() !== char.toLocaleUpperCase();
}

function isValidSignupName(name) {
  const chars = Array.from(name);
  if (chars.length === 0 || chars.length > 80 || !isLetterChar(chars[0])) return false;
  return chars.every((char, index) => (
    index === 0 ||
    isLetterChar(char) ||
    char === ' ' ||
    char === '-' ||
    char === "'"
  ));
}

function signupNameError(label, value) {
  if (!value) return `${label} is required`;
  if (value.length > 80) return `${label} must be 80 characters or fewer`;
  if (!isValidSignupName(value)) return `${label} may only contain letters, spaces, hyphens, and apostrophes`;
  return '';
}

function signupUsernameError(username) {
  if (!username) return 'Username is required';
  if (username.length < 3) return 'Username must be at least 3 characters';
  if (username.length > 20) return 'Username must be 20 characters or fewer';
  if (!/^[A-Za-z]/.test(username)) return 'Username must start with a letter';
  if (!/^[A-Za-z0-9_.]+$/.test(username)) return 'Username may only contain letters, numbers, underscore, and period';
  if (!/^[A-Za-z][A-Za-z0-9_.]{2,19}$/.test(username)) return 'Enter a valid username';
  return '';
}

function signupPasswordError(password) {
  if (!password) return 'Password is required';
  if (password.length < 12) return 'Password must be at least 12 characters';
  if (password.length > 30) return 'Password must not exceed 30 characters';
  if (!isPasswordStrong(password)) return 'Password must have: lowercase, uppercase, number, and special character';
  return '';
}

function validateSignupPasswordsInline() {
  const password = document.getElementById('signup-password')?.value || '';
  const confirmPw = document.getElementById('signup-confirm-password')?.value || '';
  if (password) {
    const passwordError = signupPasswordError(password);
    if (passwordError) setFieldError('signup-password', passwordError);
    else clearFieldError('signup-password');
  }
  if (confirmPw) {
    if (password && confirmPw !== password) {
      setFieldError('signup-confirm-password', 'Passwords do not match');
    } else {
      clearFieldError('signup-confirm-password');
    }
  }
}

function passwordToggleIcon(isVisible) {
  if (isVisible) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17.94 17.94A10.9 10.9 0 0112 19C5.5 19 2 12 2 12a20.2 20.2 0 014.2-5.3"/><path d="M9.9 4.24A10.8 10.8 0 0112 4c6.5 0 10 8 10 8a20.3 20.3 0 01-2.3 3.5"/><path d="M14.12 14.12A3 3 0 019.88 9.88"/><path d="M3 3l18 18"/></svg>';
  }
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>';
}

function updatePasswordToggle(inputId) {
  const input = document.getElementById(inputId);
  const button = document.getElementById(`${inputId}-toggle`);
  if (!input || !button) return;
  const hasValue = input.value.length > 0;
  if (hasValue) {
    button.hidden = false;
    button.classList.remove('password-toggle-hidden');
  } else {
    button.hidden = true;
    button.classList.add('password-toggle-hidden');
    input.type = 'password';
  }
  const isVisible = input.type === 'text';
  button.innerHTML = passwordToggleIcon(isVisible);
  button.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
}

function resetPasswordField(inputId) {
  const input = document.getElementById(inputId);
  if (input) input.type = 'password';
  updatePasswordToggle(inputId);
}

function togglePasswordVisibility(inputId) {
  const input = document.getElementById(inputId);
  if (!input || !input.value) return;
  const shouldShow = input.type === 'password';
  input.type = shouldShow ? 'text' : 'password';
  updatePasswordToggle(inputId);
}

function toggleLoginPasswordVisibility() {
  togglePasswordVisibility('login-password');
}

function toggleSignupPasswordVisibility() {
  togglePasswordVisibility('signup-password');
}

function toggleSignupConfirmPasswordVisibility() {
  togglePasswordVisibility('signup-confirm-password');
}

// ══════════════════════════════════════════════
// NAVIGATION
// ══════════════════════════════════════════════

const ADMIN_NAV = [
  {section:'Overview'},
  {id:'dashboard',label:'Dashboard',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>`},
  {section:'Library'},
  {id:'books',label:'Books',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>`},
  {id:'genres',label:'Genres',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3H4a1 1 0 0 0-1 1v5.59A2 2 0 0 0 3.59 11l9.59 9.59a2 2 0 0 0 2.82 0l4.59-4.59a2 2 0 0 0 0-2.82z"/><circle cx="7.5" cy="7.5" r=".5"/></svg>`},
  {id:'authors',label:'Authors',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>`},
  {id:'publishers',label:'Publishers',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9v.01"/><path d="M9 13v.01"/><path d="M9 17v.01"/></svg>`},
  {id:'borrow-requests',label:'Borrow Requests',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>`,badge:'borrow-requests'},
  {id:'reservations',label:'Reservations',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h4"/><path d="M8 18h8"/></svg>`,badge:'reservations'},
  {id:'members',label:'Users',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>`},
  {id:'transactions',label:'Transactions',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>`},
  {section:'Finance'},
  {id:'fines',label:'Fines',icon:`<span class="nav-currency-icon">₱</span>`,badge:'fines'},
  {id:'reports',label:'Reports',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 16v-5"/><path d="M12 16V7"/><path d="M17 16v-8"/></svg>`},
  {section:'System'},
  {id:'notifications',label:'Notifications',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>`,badge:'notifications'},
  {id:'settings',label:'Settings',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>`},
];

const STUDENT_NAV = [
  {section:'My Library'},
  {id:'student-dashboard',label:'Dashboard',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>`},
  {id:'catalog',label:'Browse Catalog',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>`},
  {id:'my-loans',label:'My Loans',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>`},
  {id:'my-reservations',label:'My Reservations',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="M8 14h4"/><path d="M8 18h8"/></svg>`,badge:'my-reservations'},
  {section:'Account'},
  {id:'my-fines',label:'My Fines',icon:`<span class="nav-currency-icon">₱</span>`,badge:'my-fines'},
  {id:'student-notifications',label:'Notifications',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>`,badge:'notifications'},
  {id:'student-settings',label:'Profile',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 10-16 0"/><circle cx="12" cy="7" r="4"/></svg>`},
  {id:'student-preferences',label:'Settings',icon:`<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>`},
];

const ADMIN_PAGE_TITLES = {
  dashboard: 'Dashboard',
  books: 'Books Management',
  genres: 'Genres',
  authors: 'Authors',
  publishers: 'Publishers',
  'borrow-requests': 'Borrow Requests',
  reservations: 'Reservations',
  members: 'Users',
  transactions: 'Book Loans',
  fines: 'Fines',
  reports: 'Reports',
  notifications: 'Notifications',
  settings: 'Settings',
};

function buildSidebar() {
  const nav = document.getElementById('sidebar-nav');
  const items = currentUser.role==='admin' ? ADMIN_NAV : STUDENT_NAV;
  nav.innerHTML = items.map(item => {
    if(item.section) return `<div class="nav-section-label">${item.section}</div>`;
    let badgeHtml='';
    if(item.badge) {
      const count = getBadgeCount(item.badge);
      if(count>0) badgeHtml=`<span class="nav-badge">${count}</span>`;
    }
    return `<div class="nav-item" id="nav-${item.id}" onclick="navigateTo('${item.id}')">${item.icon}<span>${item.label}</span>${badgeHtml}</div>`;
  }).join('');
}

function getBadgeCount(type) {
  if(type==='borrow-requests') return getRequests().filter(r=>r.status==='pending').length;
  if(type==='reservations') return apiState.reservations.filter(reservationIsOpen).length;
  if(type==='fines') return getFines().filter(f=>fineIsUnpaid(f)).length;
  if(type==='my-fines') return getFines().filter(f=>f.userId===currentUser?.id&&fineIsUnpaid(f)).length;
  if(type==='my-reservations') {
    const currentUserId = normalizeId(currentUser?.id);
    const currentMemberId = normalizeId(currentUser?.memberId);
    return apiState.reservations.filter(r=>
      (r.userId === currentUserId || (currentMemberId && r.memberId === currentMemberId)) &&
      reservationIsOpen(r)
    ).length;
  }
  if(type==='notifications') return getMyNotifications().filter(n=>!n.read).length;
  return 0;
}

function updateSidebarUser() {
  const users = getUsers();
  const u = users.find(x=>x.id===currentUser.id)||currentUser;
  const fullName = u.firstName && u.lastName ? `${u.firstName} ${u.lastName}` : u.name || 'User';
  document.getElementById('sidebar-name').textContent = fullName;
  document.getElementById('sidebar-role').textContent = userRoleLabel(u);
  document.getElementById('sidebar-avatar').src = u.avatar || `https://api.dicebear.com/7.x/personas/svg?seed=${u.id}`;
}

function updateAdminHeader(page) {
  const header = document.getElementById('admin-global-header');
  if(!header) return;
  if(!currentUser || currentUser.role !== 'admin') {
    header.classList.add('hidden');
    closeAdminHeaderDropdowns();
    return;
  }
  header.classList.remove('hidden');
  const activePage = page || document.querySelector('.page.active')?.id?.replace('page-', '') || 'dashboard';
  const title = document.getElementById('admin-header-title');
  if(title) title.textContent = ADMIN_PAGE_TITLES[activePage] || 'Admin Panel';
  updateAdminHeaderBadge();
  renderAdminHeaderDropdowns();
}

function defaultPageForCurrentUser() {
  if(currentUser?.role === 'admin') return 'dashboard';
  return 'student-dashboard';
}

function updateAdminHeaderBadge() {
  const badge = document.getElementById('admin-header-notification-badge');
  if(!badge) return;
  const count = getMyNotifications().filter(n=>!n.read).length;
  badge.textContent = count > 99 ? '99+' : String(count);
  badge.classList.toggle('hidden', count === 0);
}

function toggleAdminHeaderDropdown(type) {
  const target = document.getElementById(`admin-header-${type}`);
  if(!target) return;
  const willOpen = target.classList.contains('hidden');
  closeAdminHeaderDropdowns();
  if(willOpen) {
    renderAdminHeaderDropdowns();
    target.classList.remove('hidden');
    document.querySelector(`[aria-label="${headerDropdownLabel(type)}"]`)?.classList.add('active');
  }
}

function closeAdminHeaderDropdowns() {
  ['notifications', 'settings', 'profile'].forEach(type => {
    document.getElementById(`admin-header-${type}`)?.classList.add('hidden');
  });
  document.querySelectorAll('.admin-header-icon-btn.active').forEach(btn=>btn.classList.remove('active'));
}

function headerDropdownLabel(type) {
  return type === 'notifications' ? 'Notifications' : type === 'settings' ? 'Settings' : 'Profile';
}

function renderAdminHeaderDropdowns() {
  renderAdminHeaderNotifications();
  renderAdminHeaderSettings();
  renderAdminHeaderProfile();
}

function renderAdminHeaderNotifications() {
  const el = document.getElementById('admin-header-notifications');
  if(!el) return;
  const notes = getMyNotifications().slice(0, 6);
  const body = notes.length ? notes.map(n=>`
    <button class="admin-header-menu-item" type="button" onclick="closeAdminHeaderDropdowns();openNotificationDetail('${n.id}')">
      <div style="font-weight:700;font-size:.85rem">${n.title || notificationTypeTag(n).replace(/<[^>]+>/g, '')}</div>
      <div class="admin-header-menu-note">${n.message}</div>
      <div class="text-muted" style="font-size:.72rem;margin-top:4px">${dateStr(n.date)}</div>
    </button>
  `).join('') : '<div class="admin-header-menu-note">No notifications.</div>';
  el.innerHTML = `
    <div class="admin-header-dropdown-title">Notifications</div>
    <div class="admin-header-dropdown-body">${body}</div>
    <div class="admin-header-dropdown-body" style="border-top:1px solid var(--border)">
      <button class="btn btn-ghost btn-sm btn-full" type="button" onclick="clearAllNotifications()">Mark all as read</button>
    </div>`;
}

function renderAdminHeaderSettings() {
  const el = document.getElementById('admin-header-settings');
  if(!el) return;
  const u = getUsers().find(x=>x.id===currentUser?.id) || currentUser;
  el.innerHTML = `
    <div class="admin-header-dropdown-title">Settings</div>
    <div class="admin-header-dropdown-body">
      <div class="admin-header-menu-note">Account settings are available here without leaving the current section.</div>
      <div class="mt-2"><strong>${getFullName(u)}</strong></div>
      <div class="admin-header-menu-note">${u?.email || u?.username || ''}</div>
      <div class="admin-header-menu-note mt-2">Use the Settings sidebar page for full profile, photo, and security edits.</div>
    </div>`;
}

function renderAdminHeaderProfile() {
  const el = document.getElementById('admin-header-profile');
  if(!el) return;
  const u = getUsers().find(x=>x.id===currentUser?.id) || currentUser;
  const avatar = u?.avatar || `https://api.dicebear.com/7.x/personas/svg?seed=${encodeURIComponent(u?.username || u?.id || 'admin')}`;
  el.innerHTML = `
    <div class="admin-header-dropdown-title">Profile</div>
    <div class="admin-header-dropdown-body">
      <div class="admin-header-profile-row">
        <img src="${avatar}" alt="avatar">
        <div>
          <div style="font-weight:700">${getFullName(u)}</div>
          <div class="admin-header-menu-note">${userRoleLabel(u)}</div>
        </div>
      </div>
      <div class="admin-header-menu-note mt-2">${u?.email || u?.username || ''}</div>
      <button class="btn btn-ghost btn-sm btn-full mt-2" type="button" onclick="closeAdminHeaderDropdowns();doLogout()">Sign Out</button>
    </div>`;
}

document.addEventListener('click', event => {
  if(!event.target.closest('.admin-header-action-wrap')) closeAdminHeaderDropdowns();
});

document.addEventListener('keydown', event => {
  if(event.key === 'Escape') closeAdminHeaderDropdowns();
});

function navigateTo(page) {
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
  const el = document.getElementById('page-'+page);
  if(el) el.classList.add('active');
  const navEl = document.getElementById('nav-'+page);
  if(navEl) navEl.classList.add('active');
  // Refresh page content
  const renders = {
    'dashboard': renderAdminDashboard,
    'books': renderAdminBooks,
    'genres': renderAdminGenres,
    'authors': renderAdminAuthors,
    'publishers': renderAdminPublishers,
    'borrow-requests': renderBorrowRequests,
    'reservations': renderAdminReservations,
    'members': renderMembers,
    'transactions': renderTransactions,
    'fines': renderFinesAdmin,
    'reports': renderReports,
    'notifications': renderNotifications,
    'settings': renderSettings,
    'student-dashboard': renderStudentDashboard,
    'catalog': renderStudentCatalog,
    'my-loans': renderMyLoans,
    'my-reservations': renderMyReservations,
    'my-fines': renderMyFines,
    'student-notifications': renderStudentNotifications,
    'student-settings': renderStudentSettings,
    'student-preferences': renderStudentPreferences,
  };
  if(renders[page]) renders[page]();
  buildSidebar(); // refresh badges
  updateAdminHeader(page);
}

// ══════════════════════════════════════════════
// UTILS
// ══════════════════════════════════════════════

function uid(){ return 'id-'+Date.now()+'-'+Math.random().toString(36).substr(2,6); }
function getFullName(user) {
  if(!user) return 'Unknown';
  if(user.firstName && user.lastName) return `${user.firstName} ${user.lastName}`;
  return user.name || 'Unknown';
}
function dateStr(d){ return new Date(d).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}); }
function daysAgo(d){ return Math.floor((Date.now()-new Date(d))/(1000*60*60*24)); }
function daysLeft(d){ return Math.ceil((new Date(d)-Date.now())/(1000*60*60*24)); }
function addDays(d,n){ const r=new Date(d); r.setDate(r.getDate()+n); return r; }

function escapeAttr(value) {
  return String(value || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function searchValue(id) {
  return (document.getElementById(id)?.value || '').trim().toLowerCase();
}

function matchesSearch(query, parts) {
  if(!query) return true;
  return parts.some(part => String(part || '').toLowerCase().includes(query));
}

function isPasswordStrong(password) {
  const hasLowercase = /[a-z]/.test(password);
  const hasUppercase = /[A-Z]/.test(password);
  const hasNumber = /[0-9]/.test(password);
  const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
  return hasLowercase && hasUppercase && hasNumber && hasSpecial;
}

function checkPasswordRequirements() {
  const password = document.getElementById('signup-password').value;
  const reqsContainer = document.getElementById('password-requirements');
  
  if(!password) {
    reqsContainer.style.display = 'none';
    return;
  }
  
  reqsContainer.style.display = 'block';
  
  const hasLength = password.length >= 12 && password.length <= 30;
  const hasLowercase = /[a-z]/.test(password);
  const hasUppercase = /[A-Z]/.test(password);
  const hasNumber = /[0-9]/.test(password);
  const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
  
  updateReqDisplay('req-length', 'check-length', hasLength);
  updateReqDisplay('req-lowercase', 'check-lowercase', hasLowercase);
  updateReqDisplay('req-uppercase', 'check-uppercase', hasUppercase);
  updateReqDisplay('req-number', 'check-number', hasNumber);
  updateReqDisplay('req-special', 'check-special', hasSpecial);
}

function updateReqDisplay(reqId, checkId, isMet) {
  const req = document.getElementById(reqId);
  const check = document.getElementById(checkId);
  if(isMet) {
    req.style.color = 'var(--sage)';
    check.textContent = '\u2713';
    check.style.color = 'var(--sage)';
  } else {
    req.style.color = 'var(--muted)';
    check.textContent = '\u2610';
    check.style.color = 'var(--muted)';
  }
}

const TOAST_DURATION_MS = 3500;
const pendingActions = new Set();

function getOrCreateToastContainer() {
  const containers = Array.from(document.querySelectorAll('#toast-container'));
  let container = containers[0];

  containers.slice(1).forEach(extra => extra.remove());
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }

  return container;
}

function removeToast(toast) {
  if (!toast || toast.dataset.removing === 'true') return;
  toast.dataset.removing = 'true';
  window.clearTimeout(Number(toast.dataset.timeoutId || 0));
  toast.classList.add('toast-hide');
  setTimeout(() => toast.remove(), 300);
}

function showToast(msg, type='success') {
  const container = getOrCreateToastContainer();
  Array.from(container.children).forEach(toast => {
    window.clearTimeout(Number(toast.dataset.timeoutId || 0));
  });
  container.replaceChildren();

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = msg;
  toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
  toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');

  container.appendChild(toast);
  const timeoutId = setTimeout(() => removeToast(toast), TOAST_DURATION_MS);
  toast.dataset.timeoutId = String(timeoutId);
}

async function withPendingAction(key, action) {
  if (pendingActions.has(key)) return;
  pendingActions.add(key);
  try {
    return await action();
  } finally {
    pendingActions.delete(key);
  }
}

function openModal(id){ document.getElementById(id).classList.remove('hidden'); }
function closeModal(id){ document.getElementById(id).classList.add('hidden'); }

async function refreshAfterPaymentReturn() {
  const fineId = sessionStorage.getItem(PAYMENT_RETURN_FINE_KEY);
  if(!fineId || !currentUser) return;
  if(pendingActions.has('refreshAfterPaymentReturn')) return;

  await withPendingAction('refreshAfterPaymentReturn', async () => {
    try {
      await refreshState();
      pendingFinePaymentClicks.delete(normalizeId(fineId));
      const fine = getFines().find(f=>f.id===normalizeId(fineId));
      if(!fine || !fineIsUnpaid(fine)) {
        sessionStorage.removeItem(PAYMENT_RETURN_FINE_KEY);
      }
      renderRelatedViews(renderMyFines, renderStudentDashboard, renderFinesAdmin, renderAdminDashboard, renderMembers);
    } catch (error) {
      // Keep the marker so another focus/pageshow can retry after the webhook lands.
    }
  });
}

function switchTab(group, tab) {
  const allContents = document.querySelectorAll(`[id^="loans-tab-"]`);
  const allBtns = document.querySelectorAll(`.tab-btn`);
  allContents.forEach(c=>c.classList.remove('active'));
  allBtns.forEach(b=>b.classList.remove('active'));
  document.getElementById(`loans-tab-${tab}`).classList.add('active');
  event.target.classList.add('active');
  renderMyLoans();
}

function setBookFilter(el, filter) {
  document.querySelectorAll('[data-filter]').forEach(c=>c.classList.remove('active'));
  el.classList.add('active');
  window._bookFilter = filter;
  renderAdminBooks();
}

function setStudentFilter(el, filter) {
  document.querySelectorAll('[data-sfilter]').forEach(c=>c.classList.remove('active'));
  el.classList.add('active');
  window._studentFilter = filter;
  renderStudentCatalog();
}

function clearStudentCatalogFilters() {
  const search = document.getElementById('student-book-search');
  const genre = document.getElementById('student-category-filter');
  const availability = document.getElementById('student-availability-filter');
  const sort = document.getElementById('student-sort-filter');
  if(search) search.value = '';
  if(genre) genre.value = '';
  if(availability) availability.value = 'all';
  if(sort) sort.value = 'title';
  window._studentFilter = 'all';
  renderStudentCatalog();
}

function getAvailableCopies(bookId) {
  const book = getBooks().find(b=>b.id===bookId);
  if(!book) return 0;
  const activeTx = getTransactions().filter(t=>t.bookId===bookId&&!t.returned&&!t.lost);
  return Math.max(0, book.copies - activeTx.length);
}

// recipientId: user ID for student-specific, 'admin' for librarian only, 'both' for all
function addNotification(msg, type, recipientId) {
  void msg;
  void type;
  void recipientId;
}

function getMyNotifications() {
  if(!currentUser) return [];
  return getNotifications().filter(function(n) {
    if(normalizeId(n.recipientId) === normalizeId(currentUser.id)) return true;
    if(n.isStaffOnly && currentUser.memberType !== 'staff') return false;
    if(n.recipientId === 'both') return true;
    if(currentUser.role === 'admin') return n.recipientId === 'admin' || n.recipientId === 'both';
    return n.recipientId === 'member' || n.recipientId === 'both';
  });
}

function checkOverdueBooks() {
  const txs = getTransactions().filter(t=>!t.returned&&!t.lost);
  txs.forEach(tx=>{
    if(new Date(tx.dueDate) < Date.now()) {
      const book = getBooks().find(b=>b.id===tx.bookId);
      const user = getUsers().find(u=>u.id===tx.userId);
      const notes = getNotifications();
      const daysLate = daysAgo(tx.dueDate);
      const existAdmin = notes.find(n=>n.txId===tx.id&&n.type==='overdue'&&n.recipientId==='admin');
      if(!existAdmin) {
        notes.unshift({id:uid(),txId:tx.id,message:'Overdue: "'+book?.title+'" borrowed by '+getFullName(user)+' — '+daysLate+' day(s) overdue.',type:'overdue',date:Date.now(),read:false,recipientId:'admin'});
      }
      const existStudent = notes.find(n=>n.txId===tx.id&&n.type==='overdue'&&n.recipientId===tx.userId);
      if(!existStudent) {
        notes.unshift({id:uid(),txId:tx.id,message:'Your copy of "'+book?.title+'" is '+daysLate+' day(s) overdue. Please return it to avoid further fines.',type:'overdue',date:Date.now(),read:false,recipientId:tx.userId});
      }
    }
  });
}

function clearAllNotifications() {
  const notes = getNotifications().map(function(n) {
    const mine = currentUser.role==='admin'
      ? (n.recipientId==='admin'||n.recipientId==='both')
      : (n.recipientId===currentUser.id||n.recipientId==='both');
    return mine ? Object.assign({},n,{read:true}) : n;
  });
  buildSidebar();
  if(currentUser.role==='admin') renderNotifications();
  else renderStudentNotifications();
}

// ══════════════════════════════════════════════
// ADMIN — DASHBOARD HELPERS
// ══════════════════════════════════════════════

function getMonthlyBorrowings() {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 1);
  return getTransactions().filter(tx => {
    const txDate = new Date(tx.created);
    return txDate >= start && txDate < end;
  });
}

function getBorrowingsByDayOfMonth() {
  const now = new Date();
  const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
  const dayCounts = Array(daysInMonth).fill(0);
  getMonthlyBorrowings().forEach(tx => {
    const day = new Date(tx.created).getDate();
    if(day >= 1 && day <= daysInMonth) dayCounts[day - 1]++;
  });
  return dayCounts;
}

function getMonthlyBorrowingsByBook() {
  const monthlyTxs = getMonthlyBorrowings();
  const bookCounts = {};
  
  monthlyTxs.forEach(tx => {
    bookCounts[tx.bookId] = (bookCounts[tx.bookId] || 0) + 1;
  });
  
  return bookCounts;
}

function initBorrowingChart() {
  const canvas = document.getElementById('borrowing-chart');
  if (!canvas) return;
  
  // Destroy existing chart if any
  if (window._borrowingChart) {
    window._borrowingChart.destroy();
  }
  
  const dayData = getBorrowingsByDayOfMonth();
  const labels = dayData.map((_, i) => String(i + 1));
  
  try {
    window._borrowingChart = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Books Borrowed',
          data: dayData,
          backgroundColor: '#c9a84c',
          borderColor: '#b5952a',
          borderWidth: 1,
          borderRadius: 4,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        indexAxis: 'x',
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              font: { size: 10 }
            },
            grid: {
              color: 'rgba(0,0,0,0.05)'
            }
          },
          x: {
            ticks: {
              font: { size: 9 },
              maxRotation: 0,
              minRotation: 0
            },
            grid: {
              display: false
            }
          }
        }
      }
    });
  } catch(e) {
    // Chart creation error - silently fail
  }
}

// ══════════════════════════════════════════════
// ADMIN — DASHBOARD
// ══════════════════════════════════════════════

function renderAdminDashboard() {
  const books = getBooks();
  const txs = getTransactions();
  const users = getUsers();
  const fines = getFines();
  const active = txs.filter(t=>!t.returned&&!t.lost);
  const overdue = active.filter(t=>new Date(t.dueDate)<Date.now());
  const totalFines = fines.filter(f=>fineIsUnpaid(f)).reduce((s,f)=>s+f.amount,0);

  document.getElementById('admin-stats').innerHTML = `
    <div class="stat-card"><div class="stat-label">Book Titles</div><div class="stat-value">${books.length}</div><div class="stat-sub">${books.reduce((s,b)=>s+b.copies,0).toLocaleString()} physical copies</div></div>
    <div class="stat-card sage"><div class="stat-label">Active Loans</div><div class="stat-value">${active.length}</div><div class="stat-sub">${overdue.length} overdue</div></div>
    <div class="stat-card rust"><div class="stat-label">Outstanding Fines</div><div class="stat-value">₱${totalFines.toLocaleString('en-PH')}</div><div class="stat-sub">${fines.filter(f=>fineIsUnpaid(f)).length} unpaid records</div></div>
    <div class="stat-card slate"><div class="stat-label">Users</div><div class="stat-value">${users.length}</div><div class="stat-sub">${users.filter(u=>u.status==='active').length} active accounts</div></div>
  `;

  const monthlyBorrowings = getMonthlyBorrowings();
  const borrowingsByBook = getMonthlyBorrowingsByBook();
  const totalMonthly = monthlyBorrowings.length;
  
  const countEl = document.getElementById('today-count');
  if (countEl) {
    countEl.textContent = totalMonthly;
  }
  
  const booksWithCheckins = new Set(monthlyBorrowings.map(tx => tx.bookId)).size;
  const topBookEntry = Object.entries(borrowingsByBook).sort((a, b) => b[1] - a[1])[0];
  const topBook = topBookEntry ? books.find(b => b.id === topBookEntry[0]) : null;
  const monthLabel = new Date().toLocaleDateString('en-PH', {month:'long', year:'numeric'});
  
  let statsHtml = monthlyBorrowings.length ? `
    <div style="font-size:0.85rem;line-height:1.8;color:var(--muted)">
      <div><strong>${booksWithCheckins}</strong> unique books checked out in ${monthLabel}</div>
      <div><strong>${new Set(monthlyBorrowings.map(tx => tx.userId)).size}</strong> different members</div>
  ` : '<div class="empty-state"><p>No monthly borrowing data yet.</p></div>';
  
  if (topBook) {
    const timesWord = topBookEntry[1] === 1 ? 'time' : 'times';
    statsHtml += `<div>Most borrowed: <strong>"${topBook.title}"</strong> (${topBookEntry[1]} ${timesWord})</div>`;
  }
  
  if(monthlyBorrowings.length) statsHtml += `</div>`;
  
  document.getElementById('today-stats').innerHTML = statsHtml;
  
  const breakdownEntries = Object.entries(borrowingsByBook)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5);
  
  let breakdownHtml = `<div style="font-size:0.85rem"><strong style="color:var(--ink)">Monthly Top Checkouts</strong><div style="margin-top:8px">`;
  
  if (breakdownEntries.length) {
    breakdownHtml += breakdownEntries.map(([bookId, count]) => {
      const book = books.find(b => b.id === bookId);
      return `<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--cream)">
        <span>${book?.title || 'Unknown'}</span>
        <span style="font-weight:bold;color:var(--gold)">${count}</span>
      </div>`;
    }).join('');
  } else {
    breakdownHtml += '<div class="empty-state"><p>No monthly data exists.</p></div>';
  }
  
  breakdownHtml += `</div></div>`;
  document.getElementById('today-breakdown').innerHTML = breakdownHtml;
  
  setTimeout(() => {
    initBorrowingChart();
  }, 100);

  // Recent Activity
  const recentTxs = [...txs].sort((a,b)=>b.created-a.created).slice(0,6);
  document.getElementById('recent-activity').innerHTML = recentTxs.length ? `
    <table><tbody>${recentTxs.map(tx=>{
      const book = books.find(b=>b.id===tx.bookId);
      const user = getUsers().find(u=>u.id===tx.userId);
      const status = tx.lost ? '<span class="tag tag-lost">Lost</span>' : tx.returned ? '<span class="tag tag-available">Returned</span>' : '<span class="tag tag-borrowed">Borrowed</span>';
      return `<tr><td><strong>${book?.title||'Unknown'}</strong><br><small class="text-muted">${user?.name||'?'}</small></td><td>${status}</td><td class="text-muted mono" style="font-size:.75rem">${dateStr(tx.created)}</td></tr>`;
    }).join('')}</tbody></table>` : '<div class="empty-state"><p>No recent activities yet.</p></div>';

  const pendingRequests = getRequests().filter(r=>r.status==='pending').length;
  const pendingReservations = apiState.reservations.filter(reservationIsOpen).length + pendingRequests;
  const activeSubscriptions = users.filter(u=>{
    const status = String(u.subscriptionStatus || u.subscription_status || '').toLowerCase();
    return status === 'active' || u.subscriptionActive === true || u.subscription_active === true;
  }).length;
  const hasSubscriptionFields = users.some(u=>
    u.subscriptionStatus !== undefined ||
    u.subscription_status !== undefined ||
    u.subscriptionActive !== undefined ||
    u.subscription_active !== undefined
  );
  const quickStatsEl = document.getElementById('quick-stats-widget');
  if(quickStatsEl) {
    quickStatsEl.innerHTML = `
      <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
        <div class="stat-card rust" style="margin:0">
          <div class="stat-label">Overdue Loans</div>
          <div class="stat-value">${overdue.length}</div>
          <div class="stat-sub">${active.length} active loans</div>
        </div>
        <div class="stat-card" style="margin:0">
          <div class="stat-label" style="color:#7c3aed">Pending Reservations</div>
          <div class="stat-value" style="color:#7c3aed">${pendingReservations}</div>
          <div class="stat-sub">${pendingRequests} borrow requests pending</div>
        </div>
        <div class="stat-card sage" style="margin:0">
          <div class="stat-label">Active Subscriptions</div>
          <div class="stat-value">${activeSubscriptions}</div>
          <div class="stat-sub">${hasSubscriptionFields ? 'Active member subscriptions' : 'No subscription records found'}</div>
        </div>
      </div>`;
  }
}

// ══════════════════════════════════════════════
// ADMIN — BOOKS
// ══════════════════════════════════════════════

function renderAdminBooks() {
  const q = (document.getElementById('admin-book-search')?.value||'').toLowerCase();
  const filter = window._bookFilter||'all';
  let books = getBooks().filter(b=>{
    const match = !q||b.title.toLowerCase().includes(q)||b.author.toLowerCase().includes(q)||b.subject?.toLowerCase().includes(q);
    if(!match) return false;
    if(filter==='available') return getAvailableCopies(b.id)>0;
    if(filter==='borrowed') { const a=getTransactions().filter(t=>t.bookId===b.id&&!t.returned&&!t.lost); return a.length>0; }
    if(filter==='lost') { const l=getTransactions().filter(t=>t.bookId===b.id&&t.lost); return l.length>0; }
    return true;
  });

  const grid = document.getElementById('admin-books-grid');
  if(!books.length){ grid.innerHTML='<div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg><p>No books found.</p></div>'; return; }

  grid.innerHTML = books.map(b=>{
    const avail = getAvailableCopies(b.id);
    const activeTx = getTransactions().filter(t=>t.bookId===b.id&&!t.returned&&!t.lost).length;
    const lostCopies = getTransactions().filter(t=>t.bookId===b.id&&t.lost).length;
    const tag = lostCopies>0 ? '<span class="tag tag-lost">Lost</span>' : avail===0 ? '<span class="tag tag-borrowed">All Out</span>' : `<span class="tag tag-available">${avail} avail.</span>`;
    const coverImg = b.cover ? `<img class="book-cover" src="${b.cover}" alt="${b.title}" onerror="this.style.display='none'">` : `<div class="book-cover-placeholder">${b.title}</div>`;
    return `<div class="book-card">
      <div onclick="viewBookDetail('${b.id}')">${coverImg}
        <div class="book-info">
          <div class="book-title">${b.title}</div>
          <div class="book-author">${b.author}</div>
          <div class="book-meta">${tag}<span class="book-qty mono">${activeTx}/${b.copies} out</span></div>
        </div>
      </div>
      <div class="book-actions">
        <button class="btn btn-gold btn-sm" onclick="openIssueModal('${b.id}')">Issue</button>
        <button class="btn btn-ghost btn-sm" onclick="openEditBook('${b.id}')">Edit</button>
        <button class="btn btn-danger btn-sm" onclick="openMarkLost('${b.id}')">Lost/Damaged</button>
      </div>
    </div>`;
  }).join('');
}

function saveBook() {
  const id = document.getElementById('edit-book-id').value;
  const title = document.getElementById('book-title').value.trim();
  const author = document.getElementById('book-author').value.trim();
  const copies = parseInt(document.getElementById('book-copies').value)||1;
  if(!title||!author){ showToast('Title and author are required','error'); return; }

  // Cover: file upload takes priority over URL
  const fileInput = document.getElementById('book-cover-file');
  const urlInput = document.getElementById('book-cover-url');
  const errEl = document.getElementById('cover-validation-error');
  const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
  const urlVal = urlInput ? urlInput.value.trim() : '';

  if(!hasFile && !urlVal) {
    if(errEl) errEl.style.display = 'block';
    showToast('Please provide a cover image (file or URL)','error');
    return;
  }
  if(errEl) errEl.style.display = 'none';

  function doSave(coverValue) {
    const books = getBooks();
    const bookData = {
      title, author,
      isbn: document.getElementById('book-isbn').value.trim(),
      subject: document.getElementById('book-subject').value.trim(),
      publisher: document.getElementById('book-publisher').value.trim(),
      year: parseInt(document.getElementById('book-year').value)||new Date().getFullYear(),
      copies,
      lateFeePerDay: parseFloat(document.getElementById('book-late-fee').value)||20,
      baseFine: parseFloat(document.getElementById('book-fine').value)||300,
      rack: document.getElementById('book-rack').value.trim(),
      cover: coverValue,
      desc: document.getElementById('book-desc').value.trim(),
    };
    if(id) {
      const idx = books.findIndex(b=>b.id===id);
      books[idx] = {...books[idx],...bookData};
      showToast('Book updated successfully','success');
    } else {
      books.push({id:uid(),...bookData});
      showToast('Book added to catalog','success');
      addNotification('New book added: "'+title+'" by '+author, 'info', 'admin');
    }
    closeModal('modal-add-book');
    renderAdminBooks();
  }

  if(hasFile) {
    const reader = new FileReader();
    reader.onload = function(e){ doSave(e.target.result); };
    reader.onerror = function(){ showToast('Failed to read image file','error'); };
    reader.readAsDataURL(fileInput.files[0]);
  } else {
    doSave(urlVal);
  }
}

function openEditBook(id) {
  const b = getBooks().find(x=>x.id===id);
  if(!b) return;
  document.getElementById('book-modal-title').textContent='Edit Book';
  document.getElementById('edit-book-id').value=id;
  document.getElementById('book-title').value=b.title;
  document.getElementById('book-author').value=b.author;
  document.getElementById('book-isbn').value=b.isbn||'';
  document.getElementById('book-subject').value=b.subject||'';
  document.getElementById('book-publisher').value=b.publisher||'';
  document.getElementById('book-year').value=b.year||'';
  document.getElementById('book-copies').value=b.copies;
  document.getElementById('book-late-fee').value=b.lateFeePerDay||20;
  document.getElementById('book-fine').value=b.baseFine||300;
  document.getElementById('book-rack').value=b.rack||'';
  // For edit: populate URL field; file input stays empty (user can choose to replace)
  const urlInput = document.getElementById('book-cover-url');
  if(urlInput) urlInput.value = (b.cover && !b.cover.startsWith('data:')) ? b.cover : '';
  const fileInput = document.getElementById('book-cover-file');
  if(fileInput) fileInput.value = '';
  const errEl = document.getElementById('cover-validation-error');
  if(errEl) errEl.style.display = 'none';
  document.getElementById('book-desc').value=b.desc||'';
  openModal('modal-add-book');
}

function viewBookDetail(id) {
  const b = getBooks().find(x=>x.id===id);
  if(!b) return;
  const txs = getTransactions().filter(t=>t.bookId===id&&!t.returned&&!t.lost);
  const avail = getAvailableCopies(id);
  const reservations = getReservations().filter(r=>r.bookId===id);
  const coverImg = b.cover ? `<img src="${b.cover}" style="width:100%;max-height:260px;object-fit:cover;border-radius:6px;margin-bottom:20px" onerror="this.style.display='none'">` : '';
  document.getElementById('book-detail-content').innerHTML = `
    ${coverImg}
    <div class="flex gap-2" style="align-items:flex-start;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <div style="font-family:'Playfair Display',serif;font-size:1.6rem;margin-bottom:4px">${b.title}</div>
        <div class="text-muted mb-2">${b.author} ${b.year?'· '+b.year:''}</div>
        <div class="flex gap-1 mb-3" style="flex-wrap:wrap">
          ${b.subject?`<span class="tag" style="background:var(--cream);color:var(--slate)">${b.subject}</span>`:''}
          ${avail>0?`<span class="tag tag-available">${avail} available</span>`:'<span class="tag tag-borrowed">All copies out</span>'}
        </div>
        <p class="text-muted" style="font-size:.875rem;line-height:1.7">${b.desc||'No description.'}</p>
        <div class="mt-3" style="font-size:.8rem;color:var(--muted)">
          <div>ISBN: <span class="mono">${b.isbn||'N/A'}</span></div>
          <div>Publisher: ${b.publisher||'N/A'}</div>
          <div>Rack: <span class="bold">${b.rack||'N/A'}</span></div>
          <div>Copies: ${b.copies} total · Late fee: ₱${b.lateFeePerDay||20}/day · Replacement value: ₱${b.baseFine||300}</div>
        </div>
      </div>
    </div>
    ${txs.length?`<div class="mt-3"><div class="bold mb-2">Currently Borrowed By</div><table><tbody>${txs.map(tx=>{
      const u=getUsers().find(u=>u.id===tx.userId);
      const overdue=new Date(tx.dueDate)<Date.now();
      return `<tr><td>${getFullName(u)||'?'}</td><td>Due: ${dateStr(tx.dueDate)}</td><td>${overdue?'<span class="tag tag-overdue">Overdue</span>':'<span class="tag tag-borrowed">Active</span>'}</td></tr>`;
    }).join('')}</tbody></table></div>`:''}
    ${reservations.length?`<div class="mt-3"><div class="bold mb-2">Reservations (${reservations.length})</div><table><tbody>${reservations.map(r=>{const u=getUsers().find(u=>u.id===r.userId);return`<tr><td>${getFullName(u)||'?'}</td><td class="text-muted">${dateStr(r.date)}</td></tr>`;}).join('')}</tbody></table></div>`:''}
    <div class="flex gap-2 mt-3">
      <button class="btn btn-gold" onclick="closeModal('modal-book-detail');openIssueModal('${id}')">Issue Book</button>
      <button class="btn btn-ghost" onclick="closeModal('modal-book-detail');openEditBook('${id}')">Edit</button>
      <button class="btn btn-danger" onclick="closeModal('modal-book-detail');openMarkLost('${id}')">Lost / Damaged</button>
    </div>`;
  openModal('modal-book-detail');
}

// ══════════════════════════════════════════════
// ISSUE / RETURN
// ══════════════════════════════════════════════

function openIssueModal(bookId) {
  const avail = getAvailableCopies(bookId);
  if(avail<=0){ showToast('No copies available','error'); return; }
  document.getElementById('issue-book-id').value = bookId;
  const members = getUsers().filter(u=>u.role==='student');
  document.getElementById('issue-member').innerHTML = members.map(m=>`<option value="${m.memberId || m.id}">${getFullName(m)} (${m.username})</option>`).join('');
  openModal('modal-issue-book');
}

function issueBook() {
  const bookId = document.getElementById('issue-book-id').value;
  const userId = document.getElementById('issue-member').value;
  const book = getBooks().find(b=>b.id===bookId);
  const user = getUsers().find(u=>u.id===userId);
  const activeLoanCount = getTransactions().filter(t=>t.userId===userId&&!t.returned&&!t.lost).length;
  if(activeLoanCount>=5){ showToast(`${getFullName(user)} already has 5 active loans (limit reached)`,  'error'); return; }
  if(getAvailableCopies(bookId)<=0){ showToast('No copies available','error'); return; }
  const dueDate = addDays(new Date(), 10);
  const tx = {id:uid(),bookId,userId,created:Date.now(),dueDate:dueDate.toISOString(),returned:false,lost:false,renewed:0};
  const txs = getTransactions();
  txs.push(tx);
  addNotification('"'+book.title+'" issued to '+user.name+'. Due: '+dateStr(dueDate), 'info', 'admin');
  addNotification('A copy of "'+book.title+'" has been issued to you. Due date: '+dateStr(dueDate)+'.', 'info', userId);
  closeModal('modal-issue-book');
  showToast(`Book issued to ${user.name}. Due in 10 days.`,'success');
  renderAdminBooks();
}

async function openReturnModal(txId) {
  await refreshState();
  const normalizedTxId = normalizeId(txId);
  const tx = getTransactions().find(t=>t.id===normalizedTxId && !t.returned && !t.lost);
  if(!tx) {
    showToast('Active loan not found.', 'error');
    renderRelatedViews(renderTransactions, renderAdminBooks, renderMembers, renderAdminDashboard);
    return;
  }
  const book = getBooks().find(b=>b.id===tx.bookId);
  const user = getUsers().find(u=>u.id===tx.userId);
  const overdueDays = daysAgo(tx.dueDate);
  const ratePerDay = book?.lateFeePerDay||20;
  const fine = overdueDays>0 ? overdueDays*ratePerDay : 0;
  document.getElementById('return-tx-id').value = normalizedTxId;
  document.getElementById('return-info').innerHTML = `
    <div class="card"><div class="card-body">
      <strong>${book?.title}</strong> → ${user?.name}<br>
      Due: ${dateStr(tx.dueDate)}<br>
      ${fine>0?`<span class="text-rust bold">Overdue by ${overdueDays} day(s) — Late fine: ₱${fine}</span>`:'<span class="text-sage">On time return &#10003;</span>'}
    </div></div>`;
  openModal('modal-return');
}

function confirmReturn() {
  const txId = document.getElementById('return-tx-id').value;
  const txs = getTransactions();
  const idx = txs.findIndex(t=>t.id===txId);
  const tx = txs[idx];
  const book = getBooks().find(b=>b.id===tx.bookId);
  const overdueDays = Math.max(0,Math.ceil((Date.now()-new Date(tx.dueDate))/(1000*60*60*24)));
  txs[idx].returned = true;
  txs[idx].returnedDate = Date.now();
  if(overdueDays>0) {
    const ratePerDay = book?.lateFeePerDay||20;
    const fine = overdueDays*ratePerDay;
    const fines = getFines();
    fines.push({id:uid(),userId:tx.userId,bookId:tx.bookId,txId,reason:`Late return — ${overdueDays} day(s) overdue (₱${ratePerDay}/day)`,amount:fine,date:Date.now(),paid:false});
    showToast(`Returned with ₱${fine} late fine applied.`,'info');
    addNotification('Late fine of ₱'+fine+' applied for returning "'+(book?.title)+'" '+overdueDays+' day(s) late.', 'overdue', 'admin');
    addNotification('You returned "'+(book?.title)+'" '+overdueDays+' day(s) late. A fine of ₱'+fine+' has been added to your account.', 'overdue', tx.userId);
  } else {
    showToast('Book returned successfully!','success');
  }
  // Check reservations
  const reservations = getReservations().filter(r=>r.bookId===tx.bookId);
  if(reservations.length>0) {
    const nextUser = getUsers().find(u=>u.id===reservations[0].userId);
    addNotification('"'+(book?.title)+'" is now available for '+getFullName(nextUser)+' (reservation ready).', 'info', 'admin');
  addNotification('Good news! "'+(book?.title)+'" is now available. Visit the library to borrow it.', 'info', reservations[0].userId);
  }
  closeModal('modal-return');
  navigateTo(currentUser.role==='admin'?'transactions':'my-loans');
}

// ══════════════════════════════════════════════
// ADMIN — BORROW REQUESTS
// ══════════════════════════════════════════════

const borrowRequestFilters = {
  tab: 'pending',
  status: 'all',
  userQuery: '',
  bookQuery: '',
};

function setBorrowRequestTab(tab) {
  borrowRequestFilters.tab = tab === 'history' ? 'history' : 'pending';
  renderBorrowRequests();
}

function clearBorrowRequestFilters() {
  borrowRequestFilters.status = 'all';
  borrowRequestFilters.userQuery = '';
  borrowRequestFilters.bookQuery = '';
  renderBorrowRequests();
}

function renderBorrowRequests() {
  const el = document.getElementById('page-borrow-requests');
  if(!el) return;
  const activeControlId = document.activeElement?.id;
  const requests = [...getRequests()].sort((a,b)=>b.created-a.created);
  const books = getBooks();
  const users = getUsers();
  borrowRequestFilters.status = document.getElementById('borrow-status-filter')?.value ?? borrowRequestFilters.status;
  borrowRequestFilters.userQuery = document.getElementById('borrow-user-filter')?.value?.trim() ?? borrowRequestFilters.userQuery;
  borrowRequestFilters.bookQuery = document.getElementById('borrow-book-filter')?.value?.trim() ?? borrowRequestFilters.bookQuery;
  const requestMatches = r => {
    const book = books.find(b=>b.id===r.bookId);
    const user = users.find(u=>u.id===r.userId);
    if(borrowRequestFilters.status !== 'all' && r.status !== borrowRequestFilters.status) return false;
    if(!matchesSearch(borrowRequestFilters.userQuery.toLowerCase(), [getFullName(user), user?.username, user?.email])) return false;
    if(!matchesSearch(borrowRequestFilters.bookQuery.toLowerCase(), [book?.title, book?.author])) return false;
    return true;
  };
  const pending = requests.filter(r=>r.status==='pending' && requestMatches(r));
  const handled = requests.filter(r=>r.status!=='pending' && requestMatches(r));
  const visibleRequests = borrowRequestFilters.tab === 'history' ? handled : pending;
  resetPaginationOnFilterChange('borrowRequests', JSON.stringify(borrowRequestFilters));
  const pagedRequests = paginateItems(visibleRequests, paginationState.borrowRequests || 1);
  paginationState.borrowRequests = pagedRequests.currentPage;
  const stats = {
    total: requests.length,
    approved: requests.filter(r=>r.status==='approved').length,
    cancelled: requests.filter(r=>r.status==='cancelled').length,
    rejected: requests.filter(r=>r.status==='rejected').length,
    pending: requests.filter(r=>r.status==='pending').length,
  };

  let html = `<div class="page-header"><div><div class="page-title">Borrow Requests</div><div class="page-subtitle">Review and approve or reject student borrow requests.</div></div></div>
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Total Book Requests</div><div class="stat-value">${stats.total}</div></div>
      <div class="stat-card sage"><div class="stat-label">Approved</div><div class="stat-value">${stats.approved}</div></div>
      <div class="stat-card slate"><div class="stat-label">Cancelled</div><div class="stat-value">${stats.cancelled}</div></div>
      <div class="stat-card rust"><div class="stat-label">Rejected</div><div class="stat-value">${stats.rejected}</div></div>
      <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value">${stats.pending}</div></div>
    </div>
    <div class="card mb-3"><div class="card-body">
      <div class="filter-control-row">
        <select class="form-select" id="borrow-status-filter" onchange="renderBorrowRequests()">
          <option value="all" ${borrowRequestFilters.status==='all'?'selected':''}>All Statuses</option>
          <option value="pending" ${borrowRequestFilters.status==='pending'?'selected':''}>Pending</option>
          <option value="approved" ${borrowRequestFilters.status==='approved'?'selected':''}>Approved</option>
          <option value="cancelled" ${borrowRequestFilters.status==='cancelled'?'selected':''}>Cancelled</option>
          <option value="rejected" ${borrowRequestFilters.status==='rejected'?'selected':''}>Rejected</option>
        </select>
        <input class="form-input" id="borrow-user-filter" value="${escapeAttr(borrowRequestFilters.userQuery)}" placeholder="Filter by user" oninput="renderBorrowRequests()">
        <input class="form-input" id="borrow-book-filter" value="${escapeAttr(borrowRequestFilters.bookQuery)}" placeholder="Filter by book" oninput="renderBorrowRequests()">
        <button class="btn btn-ghost btn-sm" onclick="clearBorrowRequestFilters()">${transactionActionIcon('loss')} Clear Filters</button>
      </div>
    </div></div>`;

  if(!requests.length) {
    html += '<div class="empty-state"><p>No borrow requests yet.</p></div>';
    el.innerHTML = html; return;
  }

  html += `<div class="card"><div class="card-body">
    <div class="tab-bar">
      <button class="tab-btn ${borrowRequestFilters.tab==='pending'?'active':''}" onclick="setBorrowRequestTab('pending')">Pending</button>
      <button class="tab-btn ${borrowRequestFilters.tab==='history'?'active':''}" onclick="setBorrowRequestTab('history')">History</button>
    </div>`;
  if(visibleRequests.length) {
    html += `<div class="table-wrap"><table>
      <thead><tr><th>Book</th><th>Student</th><th>Requested</th><th>Due Date</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>${pagedRequests.items.map(r=>{
        const book=books.find(b=>b.id===r.bookId);
        const user=users.find(u=>u.id===r.userId);
        const avail=getAvailableCopies(r.bookId);
        const tag=r.status==='approved'
          ? '<span class="tag tag-available">Approved</span>'
          : r.status==='cancelled'
            ? '<span class="tag tag-reserved">Cancelled</span>'
            : r.status==='pending'
              ? '<span class="tag tag-borrowed">Pending</span>'
              : '<span class="tag tag-lost">Rejected</span>';
        const actions = r.status === 'pending'
          ? `${avail>0
              ? `<button class="btn btn-sage btn-sm" onclick="approveBorrowRequest('${r.id}')">&#10003; Approve</button>`
              : `<span class="tag tag-lost" style="margin-right:6px">No copies</span>`}
             <button class="btn btn-danger btn-sm" onclick="denyBorrowRequest('${r.id}')">&#10005; Reject</button>`
          : '';
        return `<tr>
          <td><strong>${book?.title||'?'}</strong><br><small class="text-muted">${book?.author||''}</small></td>
          <td>${getFullName(user)||'?'}<br><small class="text-muted">${user?.username||''}</small></td>
          <td class="text-muted mono" style="font-size:.75rem">${dateStr(r.created)}</td>
          <td class="mono" style="font-size:.75rem">${dateStr(r.dueDate)}</td>
          <td>${tag}</td>
          <td class="td-actions">${actions}</td>
        </tr>`;
      }).join('')}</tbody>
    </table></div>${renderPaginationControls({key:'borrowRequests', totalItems:pagedRequests.totalItems, currentPage:pagedRequests.currentPage, onPageChange:'renderBorrowRequests'})}`;
  } else {
    html += `<div class="empty-state"><p>No ${borrowRequestFilters.tab} requests match your filters.</p></div>`;
  }
  html += '</div></div>';

  el.innerHTML = html;
  if(activeControlId?.startsWith('borrow-')) {
    const input = document.getElementById(activeControlId);
    if(input) {
      input.focus();
      if(input.setSelectionRange) input.setSelectionRange(input.value.length, input.value.length);
    }
  }
}

const adminReservationFilters = {
  status: 'all',
  activeOnly: false,
  userId: '',
  bookId: '',
};

function clearAdminReservationFilters() {
  adminReservationFilters.status = 'all';
  adminReservationFilters.activeOnly = false;
  adminReservationFilters.userId = '';
  adminReservationFilters.bookId = '';
  renderAdminReservations();
}

function reservationStatusTag(status) {
  const normalized = String(status || 'active').toLowerCase();
  if(normalized === 'cancelled') return '<span class="tag tag-lost">Cancelled</span>';
  if(normalized === 'ready_for_pickup') return '<span class="tag tag-available">Ready for Pickup</span>';
  if(normalized === 'completed' || normalized === 'fulfilled' || normalized === 'approved') return '<span class="tag tag-available">Completed</span>';
  if(normalized === 'expired') return '<span class="tag tag-neutral">Expired</span>';
  if(normalized === 'active') return '<span class="tag tag-borrowed">Active</span>';
  return '<span class="tag tag-borrowed">Pending</span>';
}

function reservationIsOpen(reservation) {
  return ['pending','active','ready_for_pickup'].includes(String(reservation?.status || 'active').toLowerCase());
}

function reservationIsWaiting(reservation) {
  return ['pending','active'].includes(String(reservation?.status || 'active').toLowerCase());
}

async function cancelAdminReservation(reservationId) {
  return withPendingAction(`cancelAdminReservation:${reservationId}`, async () => {
    try {
      await apiRequest(API_ENDPOINTS.reservations, {action:'cancel', reservation_id:reservationId});
      await refreshState();
      renderRelatedViews(renderAdminReservations, renderAdminDashboard, renderStudentCatalog, renderMyLoans);
      showToast('Reservation cancelled.', 'info');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function markReservationReady(reservationId) {
  return withPendingAction(`markReservationReady:${reservationId}`, async () => {
    try {
      await apiRequest(API_ENDPOINTS.reservations, {action:'ready', reservation_id:reservationId});
      await refreshState();
      renderRelatedViews(renderAdminReservations, renderAdminDashboard, renderStudentCatalog, renderMyReservations);
      showToast('Reservation marked ready for pickup.', 'success');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function fulfillReservation(reservationId) {
  return withPendingAction(`fulfillReservation:${reservationId}`, async () => {
    try {
      await apiRequest(API_ENDPOINTS.reservations, {action:'fulfill', reservation_id:reservationId});
      await refreshState();
      renderRelatedViews(renderAdminReservations, renderTransactions, renderAdminBooks, renderAdminDashboard, renderMyReservations);
      showToast('Reservation fulfilled and loan issued.', 'success');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function expireReservation(reservationId) {
  return withPendingAction(`expireReservation:${reservationId}`, async () => {
    try {
      await apiRequest(API_ENDPOINTS.reservations, {action:'expire', reservation_id:reservationId});
      await refreshState();
      renderRelatedViews(renderAdminReservations, renderAdminDashboard, renderStudentCatalog, renderMyReservations);
      showToast('Reservation expired.', 'info');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

function renderAdminReservations() {
  const el = document.getElementById('page-reservations');
  if(!el) return;
  const activeControlId = document.activeElement?.id;
  const books = getBooks();
  const users = getUsers();
  adminReservationFilters.status = document.getElementById('reservation-status-filter')?.value ?? adminReservationFilters.status;
  adminReservationFilters.activeOnly = Boolean(document.getElementById('reservation-active-only')?.checked);
  adminReservationFilters.userId = document.getElementById('reservation-user-id-filter')?.value?.trim() ?? adminReservationFilters.userId;
  adminReservationFilters.bookId = document.getElementById('reservation-book-id-filter')?.value?.trim() ?? adminReservationFilters.bookId;

  const reservations = [...apiState.reservations].sort((a,b)=>b.date-a.date);
  const stats = {
    pending: reservations.filter(reservationIsOpen).length,
    approved: reservations.filter(r=>['completed','fulfilled','approved'].includes(r.status)).length,
    ready: reservations.filter(r=>r.status==='ready_for_pickup').length,
    cancelled: reservations.filter(r=>r.status==='cancelled').length,
    total: reservations.length,
  };
  const filtered = reservations.filter(r=>{
    if(adminReservationFilters.activeOnly && !reservationIsOpen(r)) return false;
    if(adminReservationFilters.status !== 'all' && r.status !== adminReservationFilters.status) return false;
    if(adminReservationFilters.userId && ![r.userId, r.memberId].includes(normalizeId(adminReservationFilters.userId))) return false;
    if(adminReservationFilters.bookId && normalizeId(r.bookId) !== normalizeId(adminReservationFilters.bookId)) return false;
    return true;
  });
  resetPaginationOnFilterChange('adminReservations', JSON.stringify(adminReservationFilters));
  const pagedReservations = paginateItems(filtered, paginationState.adminReservations || 1);
  paginationState.adminReservations = pagedReservations.currentPage;

  el.innerHTML = `
    <div class="page-header">
      <div><div class="page-title">Reservations</div><div class="page-subtitle">Manage reservation queue, pickup readiness, and fulfillment.</div></div>
    </div>
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Open Reservations</div><div class="stat-value">${stats.pending}</div><div class="stat-sub">Pending, active, or ready</div></div>
      <div class="stat-card sage"><div class="stat-label">Ready for Pickup</div><div class="stat-value">${stats.ready}</div><div class="stat-sub">Available for fulfillment</div></div>
      <div class="stat-card rust"><div class="stat-label">Cancelled Reservations</div><div class="stat-value">${stats.cancelled}</div></div>
      <div class="stat-card slate"><div class="stat-label">Total Reservations</div><div class="stat-value">${stats.total}</div></div>
    </div>
    <div class="card mb-3"><div class="card-body">
      <div class="filter-control-row">
        <select class="form-select" id="reservation-status-filter" onchange="renderAdminReservations()">
          <option value="all" ${adminReservationFilters.status==='all'?'selected':''}>All Statuses</option>
          <option value="active" ${adminReservationFilters.status==='active'?'selected':''}>Active</option>
          <option value="pending" ${adminReservationFilters.status==='pending'?'selected':''}>Pending</option>
          <option value="ready_for_pickup" ${adminReservationFilters.status==='ready_for_pickup'?'selected':''}>Ready for Pickup</option>
          <option value="completed" ${adminReservationFilters.status==='completed'?'selected':''}>Completed</option>
          <option value="cancelled" ${adminReservationFilters.status==='cancelled'?'selected':''}>Cancelled</option>
          <option value="expired" ${adminReservationFilters.status==='expired'?'selected':''}>Expired</option>
        </select>
        <label class="btn btn-ghost btn-sm" style="justify-content:flex-start"><input type="checkbox" id="reservation-active-only" ${adminReservationFilters.activeOnly?'checked':''} onchange="renderAdminReservations()"> Active Only</label>
        <input class="form-input" id="reservation-user-id-filter" value="${escapeAttr(adminReservationFilters.userId)}" placeholder="User ID" oninput="renderAdminReservations()">
        <input class="form-input" id="reservation-book-id-filter" value="${escapeAttr(adminReservationFilters.bookId)}" placeholder="Book ID" oninput="renderAdminReservations()">
        <button class="btn btn-ghost btn-sm" onclick="clearAdminReservationFilters()">${transactionActionIcon('loss')} Clear Filters</button>
      </div>
    </div></div>
    <div class="card"><div class="card-body" style="padding:0">
      ${filtered.length ? `<div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Book</th><th>User</th><th>Reserved On</th><th>Priority</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>${pagedReservations.items.map(r=>{
          const book = books.find(b=>b.id===r.bookId);
          const user = users.find(u=>u.id===r.userId);
          const canReady = reservationIsWaiting(r) && getAvailableCopies(r.bookId) > 0;
          const canFulfill = r.status === 'ready_for_pickup';
          const canClose = reservationIsOpen(r);
          return `<tr>
            <td class="mono">${r.id}</td>
            <td><div class="td-primary">${book?.title||'Unknown book'}</div><div class="td-secondary">Book ID: ${r.bookId}</div></td>
            <td><div class="td-primary">${getFullName(user)}</div><div class="td-secondary">User ID: ${r.userId || '-'} · Member ID: ${r.memberId || '-'}</div></td>
            <td class="text-muted mono" style="font-size:.75rem">${dateStr(r.date)}</td>
            <td>${r.position || r.queue_position || '-'}</td>
            <td>${reservationStatusTag(r.status)}</td>
            <td class="td-actions">
              ${canReady ? `<button class="btn btn-sage btn-sm" onclick="markReservationReady('${r.id}')">Ready</button>` : ''}
              ${canFulfill ? `<button class="btn btn-gold btn-sm" onclick="fulfillReservation('${r.id}')">Fulfill</button>` : ''}
              ${canClose ? `<button class="btn btn-ghost btn-sm" onclick="expireReservation('${r.id}')">Expire</button> <button class="btn btn-danger btn-sm" onclick="cancelAdminReservation('${r.id}')">Cancel</button>` : '<button class="btn btn-ghost btn-sm" disabled>No actions</button>'}
            </td>
          </tr>`;
        }).join('')}</tbody>
      </table></div>${renderPaginationControls({key:'adminReservations', totalItems:pagedReservations.totalItems, currentPage:pagedReservations.currentPage, onPageChange:'renderAdminReservations'})}` : '<div class="empty-state"><p>No results found</p></div>'}
    </div></div>`;

  if(activeControlId?.startsWith('reservation-')) {
    const input = document.getElementById(activeControlId);
    input?.focus();
    if(input?.setSelectionRange) input.setSelectionRange(input.value.length, input.value.length);
  }
}

function genreTrashIcon() {
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>';
}

function renderAdminGenres() {
  const el = document.getElementById('page-genres');
  if(!el) return;
  const genres = [...apiState.categories].sort((a,b)=>(a.name || '').localeCompare(b.name || ''));
  const pagedGenres = paginateItems(genres, paginationState.genres || 1);
  paginationState.genres = pagedGenres.currentPage;
  const bookCounts = genres.reduce((counts, genre) => {
    counts[normalizeId(genre.id)] = getBooks().filter(book=>normalizeId(book.categoryId)===normalizeId(genre.id)).length;
    return counts;
  }, {});

  el.innerHTML = `
    <div class="page-header">
      <div><div class="page-title">Genres</div><div class="page-subtitle">Manage book genres backed by existing categories.</div></div>
      <div class="page-actions"><button class="btn btn-gold btn-sm" onclick="openGenreModal()">+ Add Genre</button></div>
    </div>
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Root Genres</div><div class="stat-value">${genres.length}</div><div class="stat-sub">Parent genres unsupported</div></div>
      <div class="stat-card slate"><div class="stat-label">Total Genres</div><div class="stat-value">${genres.length}</div></div>
      <div class="stat-card sage"><div class="stat-label">Active Genres</div><div class="stat-value">${genres.length}</div><div class="stat-sub">Deleted genres are hidden by API</div></div>
    </div>
    <div class="card"><div class="card-body" style="padding:0">
      ${genres.length ? `<div class="table-wrap"><table>
        <thead><tr><th>Genre Name</th><th>Description</th><th>Parent Genre</th><th>Books</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>${pagedGenres.items.map(genre=>`<tr>
          <td><div class="td-primary">${genre.name || '-'}</div><div class="td-secondary">${genre.slug || ''}</div></td>
          <td class="text-muted">Not supported</td>
          <td class="text-muted">Root</td>
          <td>${bookCounts[normalizeId(genre.id)] || 0}</td>
          <td><span class="tag tag-available">Active</span></td>
          <td class="td-actions">
            <button class="btn btn-ghost btn-sm btn-icon" onclick="openGenreModal('${genre.id}')" title="Edit genre" aria-label="Edit genre">${transactionActionIcon('edit')}</button>
            <button class="btn btn-danger btn-sm btn-icon" onclick="deleteGenre('${genre.id}')" title="Delete genre" aria-label="Delete genre">${genreTrashIcon()}</button>
          </td>
        </tr>`).join('')}</tbody>
      </table></div>${renderPaginationControls({key:'genres', totalItems:pagedGenres.totalItems, currentPage:pagedGenres.currentPage, onPageChange:'renderAdminGenres'})}` : '<div class="empty-state"><p>No genres found</p></div>'}
    </div></div>`;
}

function openGenreModal(genreId = '') {
  const genre = genreId ? apiState.categories.find(item=>normalizeId(item.id)===normalizeId(genreId)) : null;
  document.getElementById('genre-modal-title').textContent = genre ? 'Edit Genre' : 'Add Genre';
  document.getElementById('genre-id').value = genre?.id || '';
  document.getElementById('genre-name').value = genre?.name || '';
  document.getElementById('genre-description').value = '';
  openModal('modal-genre');
}

async function saveGenre() {
  const id = document.getElementById('genre-id').value;
  const name = document.getElementById('genre-name').value.trim();
  if(!name) {
    showToast('Genre name is required.', 'error');
    return;
  }
  try {
    await apiRequest(API_ENDPOINTS.categories, id ? {action:'update', id, name} : {action:'create', name});
    await refreshState();
    closeModal('modal-genre');
    renderRelatedViews(renderAdminGenres, renderAdminBooks);
    showToast(id ? 'Genre updated.' : 'Genre added.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function deleteGenre(genreId) {
  if(!confirm('Delete this genre? Books will keep their records, but this genre will no longer appear in active category lists.')) return;
  try {
    await apiRequest(API_ENDPOINTS.categories, {action:'delete', id:genreId});
    await refreshState();
    renderRelatedViews(renderAdminGenres, renderAdminBooks);
    showToast('Genre deleted.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function referenceConfig(type) {
  const configs = {
    authors: {
      title: 'Authors',
      singular: 'Author',
      endpoint: API_ENDPOINTS.authors,
      stateKey: 'authors',
      pageId: 'page-authors',
      paginationKey: 'authors',
      render: renderAdminAuthors,
      usedCount: item => getBooks().filter(book =>
        String(book.author || '').split(',').map(name=>name.trim().toLowerCase()).includes(String(item.name || '').trim().toLowerCase())
      ).length,
    },
    publishers: {
      title: 'Publishers',
      singular: 'Publisher',
      endpoint: API_ENDPOINTS.publishers,
      stateKey: 'publishers',
      pageId: 'page-publishers',
      paginationKey: 'publishers',
      render: renderAdminPublishers,
      usedCount: item => getBooks().filter(book =>
        normalizeId(book.publisherId) === normalizeId(item.id) ||
        String(book.publisher || '').trim().toLowerCase() === String(item.name || '').trim().toLowerCase()
      ).length,
    },
  };
  return configs[type];
}

function renderReferenceAdmin(type) {
  const config = referenceConfig(type);
  const el = document.getElementById(config.pageId);
  if(!el) return;

  const items = [...(apiState[config.stateKey] || [])].sort((a,b)=>(a.name || '').localeCompare(b.name || ''));
  const paged = paginateItems(items, paginationState[config.paginationKey] || 1);
  paginationState[config.paginationKey] = paged.currentPage;
  const counts = items.reduce((acc, item)=>{
    acc[normalizeId(item.id)] = config.usedCount(item);
    return acc;
  }, {});

  el.innerHTML = `
    <div class="page-header">
      <div><div class="page-title">${config.title}</div><div class="page-subtitle">Manage ${config.title.toLowerCase()} used by book records.</div></div>
      <div class="page-actions"><button class="btn btn-gold btn-sm" onclick="openReferenceModal('${type}')">+ Add ${config.singular}</button></div>
    </div>
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Total ${config.title}</div><div class="stat-value">${items.length}</div></div>
      <div class="stat-card sage"><div class="stat-label">Referenced</div><div class="stat-value">${Object.values(counts).filter(count=>count > 0).length}</div><div class="stat-sub">Used by books</div></div>
      <div class="stat-card slate"><div class="stat-label">Unused</div><div class="stat-value">${Object.values(counts).filter(count=>count === 0).length}</div></div>
    </div>
    <div class="card"><div class="card-body" style="padding:0">
      ${items.length ? `<div class="table-wrap"><table>
        <thead><tr><th>${config.singular}</th><th>Slug</th><th>Books</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>${paged.items.map(item=>{
          const used = counts[normalizeId(item.id)] || 0;
          return `<tr>
            <td><div class="td-primary">${escapeAttr(item.name || '-')}</div></td>
            <td class="text-muted">${escapeAttr(item.slug || '')}</td>
            <td>${used}</td>
            <td><span class="tag tag-available">Active</span></td>
            <td class="td-actions">
              <button class="btn btn-ghost btn-sm btn-icon" onclick="openReferenceModal('${type}','${item.id}')" title="Edit ${config.singular.toLowerCase()}" aria-label="Edit ${config.singular.toLowerCase()}">${transactionActionIcon('edit')}</button>
              ${used > 0
                ? `<button class="btn btn-ghost btn-sm" disabled title="Referenced by ${used} book(s)">In Use</button>`
                : `<button class="btn btn-danger btn-sm btn-icon" onclick="deleteReference('${type}','${item.id}')" title="Delete ${config.singular.toLowerCase()}" aria-label="Delete ${config.singular.toLowerCase()}">${genreTrashIcon()}</button>`}
            </td>
          </tr>`;
        }).join('')}</tbody>
      </table></div>${renderPaginationControls({key:config.paginationKey, totalItems:paged.totalItems, currentPage:paged.currentPage, onPageChange:config.render.name})}` : `<div class="empty-state"><p>No ${config.title.toLowerCase()} found</p></div>`}
    </div></div>`;
}

function renderAdminAuthors() {
  renderReferenceAdmin('authors');
}

function renderAdminPublishers() {
  renderReferenceAdmin('publishers');
}

function openReferenceModal(type, id = '') {
  const config = referenceConfig(type);
  const item = id ? (apiState[config.stateKey] || []).find(ref=>normalizeId(ref.id)===normalizeId(id)) : null;
  document.getElementById('reference-modal-title').textContent = item ? `Edit ${config.singular}` : `Add ${config.singular}`;
  document.getElementById('reference-name-label').textContent = `${config.singular} Name`;
  document.getElementById('reference-type').value = type;
  document.getElementById('reference-id').value = item?.id || '';
  document.getElementById('reference-name').value = item?.name || '';
  openModal('modal-reference');
}

async function saveReference() {
  const type = document.getElementById('reference-type').value;
  const id = document.getElementById('reference-id').value;
  const name = document.getElementById('reference-name').value.trim();
  const config = referenceConfig(type);
  if(!config || !name) {
    showToast('Name is required.', 'error');
    return;
  }

  try {
    await apiRequest(config.endpoint, id ? {action:'update', id, name} : {action:'create', name});
    await refreshState();
    closeModal('modal-reference');
    renderRelatedViews(config.render, renderAdminBooks);
    showToast(id ? `${config.singular} updated.` : `${config.singular} added.`, 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function deleteReference(type, id) {
  const config = referenceConfig(type);
  if(!config) return;
  if(!confirm(`Delete this ${config.singular.toLowerCase()}?`)) return;

  try {
    await apiRequest(config.endpoint, {action:'delete', id});
    await refreshState();
    renderRelatedViews(config.render, renderAdminBooks);
    showToast(`${config.singular} deleted.`, 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function approveBorrowRequest(reqId) {
  const requests = getRequests();
  const idx = requests.findIndex(r=>r.id===reqId);
  if(idx<0) return;
  const req = requests[idx];
  const book = getBooks().find(b=>b.id===req.bookId);
  const user = getUsers().find(u=>u.id===req.userId);

  if(getAvailableCopies(req.bookId)<=0){ showToast('No copies available to approve this request','error'); return; }
  const activeLoanCount = getTransactions().filter(t=>t.userId===req.userId&&!t.returned&&!t.lost).length;
  if(activeLoanCount>=5){ showToast(`${getFullName(user)} already has 5 active loans`,'error'); return; }

  // Create transaction
  const tx = {id:uid(),bookId:req.bookId,userId:req.userId,created:Date.now(),dueDate:req.dueDate,returned:false,lost:false,renewed:0};
  const txs = getTransactions();
  txs.push(tx);

  // Mark request approved
  requests[idx].status = 'approved';
  requests[idx].handledDate = Date.now();

  addNotification('"'+book?.title+'" issued to '+getFullName(user)+'. Due: '+dateStr(req.dueDate), 'info', 'admin');
  addNotification('Your borrow request for "'+book?.title+'" has been APPROVED! Due date: '+dateStr(req.dueDate)+'. Please pick it up from the library.', 'info', req.userId);
  showToast(`Request approved. "${book?.title}" issued to ${getFullName(user)}.`,'success');
  renderBorrowRequests();
  buildSidebar();
}

function denyBorrowRequest(reqId) {
  const requests = getRequests();
  const idx = requests.findIndex(r=>r.id===reqId);
  if(idx<0) return;
  const req = requests[idx];
  const book = getBooks().find(b=>b.id===req.bookId);
  requests[idx].status = 'rejected';
  requests[idx].handledDate = Date.now();
  addNotification('Your borrow request for "'+book?.title+'" has been REJECTED by the librarian. Please visit the library for more information.', 'info', req.userId);
  showToast('Request rejected.','info');
  renderBorrowRequests();
  buildSidebar();
}



async function openMarkLost(bookId, selectedTxId = '') {
  await refreshState();
  const normalizedBookId = normalizeId(bookId);
  const normalizedTxId = normalizeId(selectedTxId);
  const book = getBooks().find(b=>b.id===normalizedBookId);
  const title = document.querySelector('#modal-mark-lost .modal-title');
  if(title) title.textContent = 'Mark as Lost or Damaged';
  const typeInput = document.getElementById('lost-type');
  if(typeInput) typeInput.value = 'lost';
  document.getElementById('lost-book-id').value=normalizedBookId || '';
  document.getElementById('lost-fine-amount').value=book?.baseFine||300;
  const activeTxs = getTransactions().filter(t=>t.bookId===normalizedBookId&&!t.returned&&!t.lost);
  document.getElementById('lost-book-info').innerHTML=`<div class="card"><div class="card-body"><strong>${book?.title}</strong> by ${book?.author}<br>Replacement value: <strong>₱${book?.baseFine||300}</strong> · Late fee: ₱${book?.lateFeePerDay||20}/day</div></div>`;
  const sel = document.getElementById('lost-transaction');
  if(!activeTxs.length){ sel.innerHTML='<option value="">No active loans for this book</option>'; }
  else {
    sel.innerHTML = activeTxs.map(tx=>{ const u=getUsers().find(u=>u.id===tx.userId); return `<option value="${tx.id}">${getFullName(u)} — borrowed ${dateStr(tx.created)}</option>`; }).join('');
    if(normalizedTxId && activeTxs.some(tx=>tx.id===normalizedTxId)) sel.value = normalizedTxId;
  }
  openModal('modal-mark-lost');
}

function markLost() {
  const bookId = document.getElementById('lost-book-id').value;
  const txId = document.getElementById('lost-transaction').value;
  const fineAmt = parseFloat(document.getElementById('lost-fine-amount').value)||300;
  const book = getBooks().find(b=>b.id===bookId);
  const txs = getTransactions();
  const idx = txs.findIndex(t=>t.id===txId);
  if(idx<0){ showToast('No valid transaction selected','error'); return; }
  txs[idx].lost = true;
  txs[idx].lostOrDamaged = true;
  txs[idx].returnedDate = Date.now();
  const fines = getFines();
  fines.push({id:uid(),userId:txs[idx].userId,bookId,txId,reason:`Lost or Damaged — "${book?.title}" (replacement fee)`,amount:fineAmt,date:Date.now(),paid:false,isReplacementFee:true});
  const user = getUsers().find(u=>u.id===txs[idx].userId);
  addNotification('"'+(book?.title)+'" marked as LOST OR DAMAGED by '+getFullName(user)+'. Replacement fee of ₱'+fineAmt+' applied.', 'overdue', 'admin');
  addNotification('A copy of "'+(book?.title)+'" has been marked as Lost or Damaged. A replacement fee of ₱'+fineAmt+' has been charged to your account. Borrowing is suspended until paid.', 'overdue', txs[idx].userId);
  closeModal('modal-mark-lost');
  showToast(`Book marked as Lost or Damaged. ₱${fineAmt} replacement fee applied. Member borrowing suspended.`,'error');
  renderAdminBooks();
}

// ══════════════════════════════════════════════
// ADMIN — USERS
// ══════════════════════════════════════════════

function userPhone(user) {
  return user?.phone || user?.phoneNumber || user?.phone_number || user?.contactNumber || user?.contact_number || '';
}

function userJoinedDate(user) {
  return user?.approvedAt || user?.approved_at || user?.createdAt || user?.created_at || '';
}

function userRoleLabel(user) {
  const role = user?.roleSlug || user?.role_slug || user?.role;
  if(role === 'admin') return 'Administrator';
  if(user?.memberType) return `${user.memberType.charAt(0).toUpperCase()}${user.memberType.slice(1)} User`;
  return 'Member';
}

function userStatusMeta(user) {
  const status = String(user?.status || '').toLowerCase();
  if(status === 'suspended') return {label:'Suspended', className:'tag-overdue'};
  if(status === 'deactivated') return {label:'Deactivated', className:'tag-lost'};
  if(status === 'pending') return {label:'Pending', className:'tag-neutral'};
  if(status === 'inactive') return {label:'Inactive', className:'tag-lost'};
  return {label:'Active', className:'tag-available'};
}

function userAccountActions(user) {
  const status = String(user.status || '').toLowerCase();
  const actions = [`<button class="btn btn-ghost btn-sm btn-icon" onclick="openEditUserAccountModal('${user.id}')" title="Edit account" aria-label="Edit account">${transactionActionIcon('edit')}</button>`];
  if(user.id === currentUser?.id) return actions.join(' ');
  if(status === 'pending') {
    actions.push(`<button class="btn btn-sage btn-sm" onclick="approveUserAccount('${user.id}')">Approve</button>`);
    actions.push(`<button class="btn btn-danger btn-sm" onclick="rejectUserAccount('${user.id}')">Reject</button>`);
  } else if(status === 'active') {
    actions.push(`<button class="btn btn-danger btn-sm" onclick="suspendUserAccount('${user.id}')">Suspend</button>`);
  } else if(['inactive', 'suspended', 'deactivated', 'rejected'].includes(status)) {
    actions.push(`<button class="btn btn-sage btn-sm" onclick="reactivateUserAccount('${user.id}')">Reactivate</button>`);
  }
  return actions.join(' ');
}

function renderUsersSummary(users) {
  const summary = document.getElementById('users-summary');
  if(!summary) return;
  const countByStatus = status => users.filter(u=>String(u.status || '').toLowerCase()===status).length;
  const cards = [
    {label:'Total Users', value:users.length, sub:'Accounts visible in current state', tone:'slate'},
    {label:'Administrators', value:users.filter(u=>u.role==='admin').length, sub:'Admin role accounts', tone:'gold'},
    {label:'Staff Members', value:users.filter(u=>u.memberType==='staff').length, sub:'Member type only, same access as students', tone:'slate'},
    {label:'Active Users', value:countByStatus('active'), sub:'Status from existing records', tone:'sage'},
    {label:'Inactive Users', value:countByStatus('inactive'), sub:'Stored inactive status only', tone:'rust'},
    {label:'Suspended Users', value:countByStatus('suspended'), sub:'Stored suspended status only', tone:'slate'},
  ];
  if(users.some(u=>String(u.status || '').toLowerCase()==='deactivated')) {
    cards.push({label:'Deactivated Users', value:countByStatus('deactivated'), sub:'Stored deactivated status', tone:'rust'});
  }
  summary.innerHTML = cards.map(card => `
    <div class="stat-card ${card.tone}">
      <div class="stat-label">${card.label}</div>
      <div class="stat-value">${card.value}</div>
      <div class="stat-sub">${card.sub}</div>
    </div>
  `).join('');
}

function renderMembers() {
  const q = (document.getElementById('member-search')?.value||'').toLowerCase();
  const users = getUsers();
  renderUsersSummary(users);
  resetPaginationOnFilterChange('users', q);
  const filteredUsers = users.filter(u=>{
    if(!q) return true;
    const fullName = getFullName(u);
    return matchesSearch(q, [fullName, u.email, userPhone(u)]);
  });
  const pagedUsers = paginateItems(filteredUsers, paginationState.users || 1);
  paginationState.users = pagedUsers.currentPage;
  const tbody = document.getElementById('members-table');
  if(!filteredUsers.length){
    tbody.innerHTML='<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:40px">No users found.</td></tr>';
    renderPaginationMount('users', tbody.closest('.table-wrap'), '');
    return;
  }
  tbody.innerHTML = pagedUsers.items.map(m=>{
    const status = userStatusMeta(m);
    const phone = userPhone(m);
    const joined = userJoinedDate(m);
    const role = userRoleLabel(m);
    const actions = userAccountActions(m);
    return `<tr>
      <td class="mono">${m.id}</td>
      <td><div class="flex gap-2" style="align-items:center"><img src="${m.avatar||`https://api.dicebear.com/7.x/personas/svg?seed=${m.id}`}" style="width:32px;height:32px;border-radius:50%;object-fit:cover"><div><strong>${getFullName(m)}</strong><br><small class="text-muted">${m.email||''}</small></div></div></td>
      <td>${role}</td>
      <td>${phone || '-'}</td>
      <td class="text-muted mono" style="font-size:.75rem">${joined ? dateStr(joined) : '-'}</td>
      <td><span class="tag ${status.className}">${status.label}</span></td>
      <td class="td-actions"><button class="btn btn-ghost btn-sm" onclick="viewMemberDetail('${m.id}')">View</button>${actions ? ` ${actions}` : ''}</td>
    </tr>`;
  }).join('');
  renderPaginationMount('users', tbody.closest('.table-wrap'), renderPaginationControls({
    key:'users',
    totalItems:pagedUsers.totalItems,
    currentPage:pagedUsers.currentPage,
    onPageChange:'renderMembers',
  }));
}

function viewMemberDetail(userId) {
  const user = getUsers().find(u=>u.id===userId);
  if(!user) return;
  const txs = getTransactions().filter(t=>t.userId===userId);
  const active = txs.filter(t=>!t.returned&&!t.lost);
  const fines = getFines().filter(f=>f.userId===userId);
  const unpaidFines = fines.filter(f=>fineIsUnpaid(f)).reduce((s,f)=>s+f.amount,0);
  const status = userStatusMeta(user);
  const role = userRoleLabel(user);
  document.getElementById('member-detail-content').innerHTML = `
    <div class="flex gap-3 mb-3" style="align-items:center">
      <img src="${user.avatar||`https://api.dicebear.com/7.x/personas/svg?seed=${user.id}`}" style="width:72px;height:72px;border-radius:50%;border:3px solid var(--gold)">
      <div>
        <div style="font-family:'Playfair Display',serif;font-size:1.4rem">${getFullName(user)}</div>
        <div class="text-muted">${role} · ${user.email||''}</div>
        <div class="mt-1"><span class="tag ${status.className}">${status.label}</span> ${unpaidFines>0?`<span class="tag tag-overdue">₱${unpaidFines} outstanding fines</span>`:'<span class="tag tag-available">No outstanding fines</span>'}</div>
      </div>
    </div>
    <div class="tab-bar" style="margin-bottom:16px">
      <div style="font-weight:700;font-size:.9rem;padding:8px 0;color:var(--gold)">Active Loans (${active.length}/5)</div>
    </div>
    ${active.length?`<table><tbody>${active.map(tx=>{
      const book=getBooks().find(b=>b.id===tx.bookId);
      const overdue=new Date(tx.dueDate)<Date.now();
      return `<tr><td><strong>${book?.title||'?'}</strong></td><td>Due: ${dateStr(tx.dueDate)}</td><td>${overdue?'<span class="tag tag-overdue">Overdue</span>':'<span class="tag tag-borrowed">Active</span>'}</td><td><button class="btn btn-sage btn-sm" onclick="closeModal('modal-member-detail');openReturnModal('${tx.id}')">Return</button></td></tr>`;
    }).join('')}</tbody></table>`:'<p class="text-muted">No active loans.</p>'}
    <div class="mt-3"><div class="bold mb-2">Fines</div>
    ${fines.length?`<table><tbody>${fines.map(f=>`<tr><td>${f.reason}</td><td class="${fineIsUnpaid(f)?'text-rust bold':'text-muted'}">₱${f.amount}</td><td>${fineStatusTag(f)}</td>${fineIsUnpaid(f)?`<td><button class="btn btn-sage btn-sm" onclick="payFine('${f.id}')">Pay</button></td>`:'<td></td>'}</tr>`).join('')}</tbody></table>`:'<p class="text-muted">No fines on record.</p>'}
    </div>`;
  openModal('modal-member-detail');
}

async function deactivateMember(userId) {
  const reason = prompt('Reason for deactivation:', 'graduated');
  if (reason === null) return;
  try {
    await apiRequest(API_ENDPOINTS.members, {action:'deactivate', user_id:userId, reason});
    await refreshState();
    renderRelatedViews(renderMembers, renderAdminDashboard);
    showToast('Member deactivated. History was preserved.','success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function runUserAccountAction(userId, action, successMessage, reason = null) {
  const payload = {action, user_id:userId};
  if(reason !== null) payload.reason = reason;
  try {
    const data = await apiRequest(API_ENDPOINTS.members, payload);
    await refreshState();
    renderRelatedViews(renderMembers, renderAdminDashboard);
    const mail = data.mail;
    const mailNote = mail && mail.sent === false ? ` Email not sent: ${mail.error}` : '';
    showToast(successMessage + mailNote, 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function approveUserAccount(userId) {
  if(!confirm('Approve this user account?')) return;
  runUserAccountAction(userId, 'approve_user', 'User approved.');
}

function rejectUserAccount(userId) {
  const reason = prompt('Reason for rejection:', '');
  if(reason === null) return;
  runUserAccountAction(userId, 'reject_user', 'User rejected.', reason);
}

function suspendUserAccount(userId) {
  const reason = prompt('Reason for suspension:', '');
  if(reason === null) return;
  runUserAccountAction(userId, 'suspend_user', 'User suspended.', reason);
}

function reactivateUserAccount(userId) {
  if(!confirm('Reactivate this user account?')) return;
  runUserAccountAction(userId, 'reactivate_user', 'User reactivated.');
}

function openCreatePrivilegedAccountModal() {
  document.getElementById('user-account-modal-title').textContent = 'Add Admin Account';
  document.getElementById('user-account-id').value = '';
  ['first-name','last-name','email','username','phone','password'].forEach(suffix => {
    const el = document.getElementById(`user-account-${suffix}`);
    if(el) el.value = '';
  });
  document.getElementById('user-account-role').value = 'admin';
  document.querySelector('#user-account-role option[value="member"]')?.setAttribute('disabled', 'disabled');
  document.getElementById('user-account-password').placeholder = 'Required for new accounts';
  document.getElementById('user-account-error').textContent = '';
  openModal('modal-user-account');
}

function openEditUserAccountModal(userId) {
  const user = getUsers().find(u=>u.id===normalizeId(userId));
  if(!user) return;
  document.getElementById('user-account-modal-title').textContent = 'Edit User Account';
  document.getElementById('user-account-id').value = user.id;
  document.getElementById('user-account-first-name').value = user.firstName || '';
  document.getElementById('user-account-last-name').value = user.lastName || '';
  document.getElementById('user-account-email').value = user.email || '';
  document.getElementById('user-account-username').value = user.username || '';
  document.getElementById('user-account-phone').value = userPhone(user);
  document.getElementById('user-account-role').value = user.roleSlug || (user.role === 'admin' ? 'admin' : 'member');
  document.querySelector('#user-account-role option[value="member"]')?.removeAttribute('disabled');
  document.getElementById('user-account-password').value = '';
  document.getElementById('user-account-password').placeholder = 'Leave blank to keep current';
  document.getElementById('user-account-error').textContent = '';
  openModal('modal-user-account');
}

async function saveManagedUserAccount() {
  const errorEl = document.getElementById('user-account-error');
  const userId = normalizeId(document.getElementById('user-account-id').value);
  const roleSlug = document.getElementById('user-account-role').value;
  const payload = {
    first_name: document.getElementById('user-account-first-name').value.trim(),
    last_name: document.getElementById('user-account-last-name').value.trim(),
    email: document.getElementById('user-account-email').value.trim(),
    username: document.getElementById('user-account-username').value.trim(),
    phone: document.getElementById('user-account-phone').value.trim(),
    role_slug: roleSlug,
  };
  const password = document.getElementById('user-account-password').value;
  if(password) payload.password = password;
  if(!payload.first_name || !payload.last_name || !payload.email || !payload.username) {
    errorEl.textContent = 'Name, email, and username are required.';
    return;
  }
  if(!userId && !password) {
    errorEl.textContent = 'Password is required for new admin accounts.';
    return;
  }

  try {
    const message = userId ? 'Account updated.' : 'Account created.';
    if(userId) {
      await apiRequest(API_ENDPOINTS.members, {action:'update_account', user_id:userId, ...payload});
      const user = getUsers().find(u=>u.id===userId);
      if(user && (user.roleSlug || user.role) !== roleSlug) {
        await apiRequest(API_ENDPOINTS.members, {action:'update_role', user_id:userId, role_slug:roleSlug});
      }
    } else {
      await apiRequest(API_ENDPOINTS.members, {action:'create_privileged', ...payload});
    }
    await refreshState();
    closeModal('modal-user-account');
    renderRelatedViews(renderMembers, renderAdminDashboard);
    showToast(message, 'success');
  } catch (error) {
    errorEl.textContent = error.message;
  }
}

// ══════════════════════════════════════════════
// ADMIN — TRANSACTIONS
// ══════════════════════════════════════════════

function loanStatusMeta(tx) {
  const rawStatus = String(tx.status || '').toLowerCase();
  if(tx.returned || rawStatus === 'returned') return {label:'RETURNED', className:'tag-neutral', searchable:'returned'};
  if(rawStatus === 'damaged') return {label:'DAMAGED', className:'tag-lost', searchable:'damaged lost'};
  if(tx.lost || rawStatus === 'lost') return {label:'LOST', className:'tag-lost', searchable:'lost'};
  if(new Date(tx.dueDate) < Date.now()) return {label:'OVERDUE', className:'tag-overdue', searchable:'overdue'};
  return {label:'CHECKED_OUT', className:'tag-available', searchable:'checked out active borrowed'};
}

function transactionActionIcon(type) {
  const icons = {
    return: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
    damage: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    loss: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>',
    edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>',
  };
  return icons[type] || '';
}

async function openMarkLostForTransaction(txId, type = 'lost') {
  await refreshState();
  const tx = getTransactions().find(t=>t.id===normalizeId(txId) && !t.returned && !t.lost);
  if(!tx || tx.returned || tx.lost) {
    showToast('Only active loans can be marked lost or damaged.', 'error');
    renderRelatedViews(renderTransactions, renderAdminBooks, renderMembers, renderAdminDashboard);
    return;
  }
  await openMarkLost(tx.bookId, tx.id);
  const typeInput = document.getElementById('lost-type');
  if(typeInput) typeInput.value = type === 'damaged' ? 'damaged' : 'lost';
  const title = document.querySelector('#modal-mark-lost .modal-title');
  if(title) title.textContent = type === 'damaged' ? 'Mark as Damaged' : 'Mark as Lost';
}

function dateInputValue(value) {
  if(!value) return '';
  if(typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) return value.slice(0, 10);
  const date = new Date(value);
  if(Number.isNaN(date.getTime())) return '';
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function openEditLoan(txId) {
  const tx = getTransactions().find(t=>t.id===normalizeId(txId));
  if(!tx) {
    showToast('Loan not found.', 'error');
    return;
  }
  const book = getBooks().find(b=>b.id===tx.bookId);
  const user = getUsers().find(u=>u.id===tx.userId);
  const status = String(tx.status || (tx.returned ? 'returned' : tx.lost ? 'lost' : 'borrowed')).toLowerCase();
  const isClosed = ['returned', 'lost', 'damaged'].includes(status) || tx.returned || tx.lost;

  document.getElementById('edit-loan-id').value = tx.id;
  document.getElementById('edit-loan-checkout-date').value = dateInputValue(tx.created);
  document.getElementById('edit-loan-due-date').value = dateInputValue(tx.dueDate);
  document.getElementById('edit-loan-return-date').value = isClosed ? dateInputValue(tx.returnedDate || tx.created) : '';
  document.getElementById('edit-loan-status').value = status;
  document.getElementById('edit-loan-return-group').style.display = isClosed ? '' : 'none';
  document.getElementById('edit-loan-error').textContent = '';
  document.getElementById('edit-loan-info').innerHTML = `
    <div class="card"><div class="card-body" style="padding:14px 16px">
      <div class="td-primary">${book?.title || 'Unknown book'}</div>
      <div class="td-secondary">${getFullName(user)} · ${user?.email || user?.username || ''}</div>
    </div></div>`;
  openModal('modal-edit-loan');
}

async function saveLoanEdit() {
  const txId = document.getElementById('edit-loan-id').value;
  const checkoutDate = document.getElementById('edit-loan-checkout-date').value;
  const dueDate = document.getElementById('edit-loan-due-date').value;
  const returnDate = document.getElementById('edit-loan-return-date').value;
  const status = document.getElementById('edit-loan-status').value;
  const errorEl = document.getElementById('edit-loan-error');
  errorEl.textContent = '';

  if(!checkoutDate || !dueDate) {
    errorEl.textContent = 'Checkout date and due date are required.';
    return;
  }
  if(dueDate < checkoutDate) {
    errorEl.textContent = 'Due date cannot be before checkout date.';
    return;
  }
  if(returnDate && returnDate < checkoutDate) {
    errorEl.textContent = 'Return date cannot be before checkout date.';
    return;
  }

  try {
    await apiRequest(API_ENDPOINTS.loans, {
      action:'update',
      transaction_id:txId,
      checkout_date:checkoutDate,
      due_date:dueDate,
      return_date:returnDate,
      status,
    });
    await refreshState();
    closeModal('modal-edit-loan');
    renderRelatedViews(renderTransactions, renderMembers, renderAdminDashboard);
    showToast('Loan updated.', 'success');
  } catch (error) {
    errorEl.textContent = error.message;
  }
}

function renderTransactions() {
  const q = searchValue('transaction-search');
  const books = getBooks();
  const users = getUsers();
  const allTxs = [...getTransactions()];
  const statsEl = document.getElementById('transaction-stats');
  if(statsEl) {
    const activeLoans = allTxs.filter(tx=>!tx.returned&&!tx.lost).length;
    const overdueLoans = allTxs.filter(tx=>!tx.returned&&!tx.lost&&new Date(tx.dueDate)<Date.now()).length;
    const returnedLoans = allTxs.filter(tx=>tx.returned).length;
    const totalFines = getFines().filter(fineIsUnpaid).reduce((sum,f)=>sum+Number(f.amount || 0),0);
    statsEl.innerHTML = `
      <div class="stat-card sage"><div class="stat-label">Active Loans</div><div class="stat-value">${activeLoans}</div><div class="stat-sub">Currently checked out</div></div>
      <div class="stat-card rust"><div class="stat-label">Overdue Loans</div><div class="stat-value">${overdueLoans}</div><div class="stat-sub">Past due date</div></div>
      <div class="stat-card slate"><div class="stat-label">Returned</div><div class="stat-value">${returnedLoans}</div><div class="stat-sub">Completed loans</div></div>
      <div class="stat-card"><div class="stat-label">Total Fines</div><div class="stat-value">₱${totalFines.toLocaleString('en-PH')}</div><div class="stat-sub">Unpaid balance</div></div>`;
  }
  const txs = [...getTransactions()].filter(tx => {
    const book = books.find(b=>b.id===tx.bookId);
    const user = users.find(u=>u.id===tx.userId);
    const status = loanStatusMeta(tx);
    return matchesSearch(q, [
      tx.id,
      book?.title,
      book?.author,
      getFullName(user),
      user?.email,
      user?.username,
      status.searchable,
      status.label,
      tx.status,
      dateStr(tx.created),
      dateStr(tx.dueDate),
      tx.returnedDate ? dateStr(tx.returnedDate) : '',
    ]);
  }).sort((a,b)=>b.created-a.created);
  resetPaginationOnFilterChange('transactions', q);
  const pagedTxs = paginateItems(txs, paginationState.transactions || 1);
  paginationState.transactions = pagedTxs.currentPage;
  const tbody = document.getElementById('transactions-table');
  if(!txs.length){
    tbody.innerHTML=`<tr><td colspan="8"><div class="empty-state"><p>${allTxs.length ? 'No results found' : 'All caught up!'}</p></div></td></tr>`;
    renderPaginationMount('transactions', tbody.closest('.table-wrap'), '');
    return;
  }
  tbody.innerHTML = pagedTxs.items.map(tx=>{
    const book = books.find(b=>b.id===tx.bookId);
    const user = users.find(u=>u.id===tx.userId);
    const status = loanStatusMeta(tx);
    const isActive = !tx.returned&&!tx.lost;
    const actions = isActive
      ? `<button class="btn btn-sage btn-sm btn-icon" onclick="openReturnModal('${tx.id}')" title="Return loan" aria-label="Return loan">${transactionActionIcon('return')}</button>
         <button class="btn btn-gold btn-sm btn-icon" onclick="openMarkLostForTransaction('${tx.id}','damaged')" title="Mark damaged" aria-label="Mark damaged">${transactionActionIcon('damage')}</button>
         <button class="btn btn-danger btn-sm btn-icon" onclick="openMarkLostForTransaction('${tx.id}','lost')" title="Mark lost" aria-label="Mark lost">${transactionActionIcon('loss')}</button>
         <button class="btn btn-ghost btn-sm btn-icon" onclick="openEditLoan('${tx.id}')" title="Edit loan" aria-label="Edit loan">${transactionActionIcon('edit')}</button>`
      : `<button class="btn btn-ghost btn-sm btn-icon" onclick="openEditLoan('${tx.id}')" title="Edit loan" aria-label="Edit loan">${transactionActionIcon('edit')}</button>`;
    return `<tr>
      <td class="mono">${tx.id}</td>
      <td><div class="td-primary">${book?.title||'Unknown book'}</div><div class="td-secondary">${book?.author||'Unknown author'}</div></td>
      <td><div class="td-primary">${getFullName(user)||'Unknown user'}</div><div class="td-secondary">${user?.email||user?.username||''}</div></td>
      <td class="text-muted mono" style="font-size:.75rem">${dateStr(tx.created)}</td>
      <td class="mono" style="font-size:.75rem">${dateStr(tx.dueDate)}</td>
      <td class="text-muted mono" style="font-size:.75rem">${tx.returned||tx.lost?dateStr(tx.returnedDate||tx.created):'-'}</td>
      <td><span class="tag ${status.className}">${status.label}</span></td>
      <td class="td-actions">${actions}</td>
    </tr>`;
  }).join('');
  renderPaginationMount('transactions', tbody.closest('.table-wrap'), renderPaginationControls({
    key:'transactions',
    totalItems:pagedTxs.totalItems,
    currentPage:pagedTxs.currentPage,
    onPageChange:'renderTransactions',
  }));
}

// ══════════════════════════════════════════════
// ADMIN — FINES
// ══════════════════════════════════════════════

const fineAdminFilters = {
  status: 'all',
  type: 'all',
  userQuery: '',
  bookQuery: '',
};

const reportsState = {
  type: 'issued',
  from: '',
  to: '',
  user: '',
  status: 'all',
  rows: [],
  totals: null,
  loaded: false,
};
let reportFilterTimer = null;

function fineTypeValue(fine) {
  const raw = String(fine.fine_type || fine.type || '').toLowerCase();
  if(raw) return raw;
  const reason = String(fine.reason || '').toLowerCase();
  if(reason.includes('overdue') || reason.includes('late')) return 'overdue';
  if(reason.includes('damaged')) return 'damaged';
  if(reason.includes('lost')) return 'lost';
  return fine.isReplacementFee ? 'replacement' : 'manual';
}

function fineTypeLabel(fine) {
  const type = fineTypeValue(fine);
  if(type === 'overdue') return 'Overdue';
  if(type === 'lost') return 'Lost';
  if(type === 'damaged') return 'Damaged';
  if(type === 'replacement') return 'Replacement';
  return 'Manual';
}

function fineStatusValue(fine) {
  if(fine.waived || fine.status === 'waived') return 'waived';
  return fineIsUnpaid(fine) ? 'unpaid' : 'paid';
}

function fineRuleTypeLabel(type) {
  if(type === 'overdue') return 'Overdue';
  if(type === 'lost') return 'Lost';
  if(type === 'damaged') return 'Damaged';
  return 'Manual';
}

function fineRuleStatusTag(rule) {
  return rule.isActive ? '<span class="tag tag-available">Active</span>' : '<span class="tag tag-neutral">Disabled</span>';
}

function renderFineRulesAdmin() {
  const panel = document.getElementById('fine-rules-panel');
  if(!panel) return;
  const rules = [...(apiState.fineRules || [])].sort((a,b)=>{
    if(a.isActive !== b.isActive) return a.isActive ? -1 : 1;
    return Number(b.id || 0) - Number(a.id || 0);
  });

  if(!rules.length) {
    panel.innerHTML = '<div class="empty-state"><p>No fine rules found</p></div>';
    return;
  }

  panel.innerHTML = `<div class="table-wrap"><table>
    <thead><tr><th>Rule ID</th><th>Rule Name</th><th>Type</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>${rules.map(rule=>`<tr>
      <td class="mono">${rule.id}</td>
      <td><div class="td-primary">${escapeAttr(rule.name)}</div><div class="td-secondary">${rule.type === 'overdue' ? `${rule.graceDays} grace day(s)` : 'Default amount'}</div></td>
      <td><span class="tag tag-reserved">${fineRuleTypeLabel(rule.type)}</span></td>
      <td class="bold">₱${Number(rule.amount || 0).toLocaleString('en-PH')}</td>
      <td>${fineRuleStatusTag(rule)}</td>
      <td class="td-actions">
        <button class="btn btn-ghost btn-sm btn-icon" onclick="openFineRuleModal('${rule.id}')" title="Edit rule" aria-label="Edit rule">${transactionActionIcon('edit')}</button>
        ${rule.isActive ? `<button class="btn btn-danger btn-sm btn-icon" onclick="disableFineRule('${rule.id}')" title="Disable rule" aria-label="Disable rule">${transactionActionIcon('loss')}</button>` : '<button class="btn btn-ghost btn-sm" disabled>Disabled</button>'}
      </td>
    </tr>`).join('')}</tbody>
  </table></div>`;
}

function openFineRuleModal(ruleId = '') {
  const rule = ruleId ? (apiState.fineRules || []).find(item=>normalizeId(item.id)===normalizeId(ruleId)) : null;
  document.getElementById('fine-rule-modal-title').textContent = rule ? 'Edit Fine Rule' : 'Add Fine Rule';
  document.getElementById('fine-rule-id').value = rule?.id || '';
  document.getElementById('fine-rule-name').value = rule?.name || '';
  document.getElementById('fine-rule-type').value = rule?.type || 'overdue';
  document.getElementById('fine-rule-amount').value = rule ? String(rule.amount ?? 0) : '';
  document.getElementById('fine-rule-grace-days').value = String(rule?.graceDays ?? 0);
  document.getElementById('fine-rule-status').value = rule && !rule.isActive ? '0' : '1';
  openModal('modal-fine-rule');
}

async function saveFineRule() {
  const id = document.getElementById('fine-rule-id').value;
  const name = document.getElementById('fine-rule-name').value.trim();
  const type = document.getElementById('fine-rule-type').value;
  const amount = parseFloat(document.getElementById('fine-rule-amount').value);
  const graceDays = parseInt(document.getElementById('fine-rule-grace-days').value, 10) || 0;
  const isActive = document.getElementById('fine-rule-status').value === '1';

  if(!name) {
    showToast('Rule name is required.', 'error');
    return;
  }
  if(!Number.isFinite(amount) || amount < 0) {
    showToast('Enter a valid fine rule amount.', 'error');
    return;
  }
  if(graceDays < 0) {
    showToast('Grace days must be zero or greater.', 'error');
    return;
  }

  try {
    await apiRequest(API_ENDPOINTS.fineRules, {
      action: id ? 'update' : 'create',
      id,
      name,
      type,
      amount,
      grace_days: graceDays,
      is_active: isActive ? 1 : 0,
    });
    await refreshState();
    closeModal('modal-fine-rule');
    renderRelatedViews(renderFinesAdmin);
    showToast(id ? 'Fine rule updated.' : 'Fine rule added.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function disableFineRule(ruleId) {
  if(!confirm('Disable this fine rule? Existing fines will remain unchanged.')) return;
  try {
    await apiRequest(API_ENDPOINTS.fineRules, {action:'disable', id:ruleId});
    await refreshState();
    renderRelatedViews(renderFinesAdmin);
    showToast('Fine rule disabled.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function clearFineAdminFilters() {
  clearFineAdminFiltersStateOnly();
  renderFinesAdmin();
}

function clearFineAdminFiltersStateOnly() {
  fineAdminFilters.status = 'all';
  fineAdminFilters.type = 'all';
  fineAdminFilters.userQuery = '';
  fineAdminFilters.bookQuery = '';
}

function renderFinesAdmin() {
  renderFineRulesAdmin();
  const activeControlId = document.activeElement?.id;
  const books=getBooks(),users=getUsers();
  const allFines = [...getFines()].sort((a,b)=>b.date-a.date);
  fineAdminFilters.status = document.getElementById('fine-status-filter')?.value ?? fineAdminFilters.status;
  fineAdminFilters.type = document.getElementById('fine-type-filter')?.value ?? fineAdminFilters.type;
  fineAdminFilters.userQuery = document.getElementById('fine-user-filter')?.value?.trim() ?? fineAdminFilters.userQuery;
  fineAdminFilters.bookQuery = document.getElementById('fine-book-filter')?.value?.trim() ?? fineAdminFilters.bookQuery;
  const fines = allFines.filter(f => {
    const user=users.find(u=>u.id===f.userId);
    const book=books.find(b=>b.id===f.bookId);
    const status = fineStatusValue(f);
    const type = fineTypeValue(f);
    if(fineAdminFilters.status !== 'all' && status !== fineAdminFilters.status) return false;
    if(fineAdminFilters.type !== 'all' && type !== fineAdminFilters.type) return false;
    if(!matchesSearch(fineAdminFilters.userQuery.toLowerCase(), [getFullName(user), user?.username, user?.email])) return false;
    if(!matchesSearch(fineAdminFilters.bookQuery.toLowerCase(), [book?.title, book?.author])) return false;
    return true;
  });
  resetPaginationOnFilterChange('adminFines', JSON.stringify(fineAdminFilters));
  const pagedFines = paginateItems(fines, paginationState.adminFines || 1);
  paginationState.adminFines = pagedFines.currentPage;
  const totalUnpaid = allFines.filter(f=>fineIsUnpaid(f)).reduce((s,f)=>s+f.amount,0);
  const totalPaid = allFines.filter(f=>f.paid).reduce((s,f)=>s+f.amount,0);
  const pendingCount = allFines.filter(f=>fineIsUnpaid(f)).length;
  const paidCount = allFines.filter(f=>f.paid).length;
  document.getElementById('fine-stats').innerHTML=`
    <div class="stat-card rust"><div class="stat-label">Total Pending Fines</div><div class="stat-value">${pendingCount}</div></div>
    <div class="stat-card sage"><div class="stat-label">Paid Fines</div><div class="stat-value">${paidCount}</div></div>
    <div class="stat-card"><div class="stat-label">Total Collected</div><div class="stat-value">₱${totalPaid.toLocaleString('en-PH')}</div></div>
    <div class="stat-card slate"><div class="stat-label">Total Outstanding</div><div class="stat-value">₱${totalUnpaid.toLocaleString('en-PH')}</div></div>`;
  const tbody = document.getElementById('fines-table');
  const filterPanel = document.getElementById('fine-filter-panel');
  if(filterPanel) {
    filterPanel.innerHTML = `<div class="filter-control-row">
      <select class="form-select" id="fine-status-filter" onchange="renderFinesAdmin()">
        <option value="all" ${fineAdminFilters.status==='all'?'selected':''}>All Statuses</option>
        <option value="unpaid" ${fineAdminFilters.status==='unpaid'?'selected':''}>Unpaid</option>
        <option value="paid" ${fineAdminFilters.status==='paid'?'selected':''}>Paid</option>
        <option value="waived" ${fineAdminFilters.status==='waived'?'selected':''}>Waived</option>
      </select>
      <select class="form-select" id="fine-type-filter" onchange="renderFinesAdmin()">
        <option value="all" ${fineAdminFilters.type==='all'?'selected':''}>All Types</option>
        <option value="overdue" ${fineAdminFilters.type==='overdue'?'selected':''}>Overdue</option>
        <option value="lost" ${fineAdminFilters.type==='lost'?'selected':''}>Lost</option>
        <option value="damaged" ${fineAdminFilters.type==='damaged'?'selected':''}>Damaged</option>
        <option value="replacement" ${fineAdminFilters.type==='replacement'?'selected':''}>Replacement</option>
        <option value="manual" ${fineAdminFilters.type==='manual'?'selected':''}>Manual</option>
      </select>
      <input class="form-input" id="fine-user-filter" value="${escapeAttr(fineAdminFilters.userQuery)}" placeholder="Filter by user" oninput="renderFinesAdmin()">
      <input class="form-input" id="fine-book-filter" value="${escapeAttr(fineAdminFilters.bookQuery)}" placeholder="Filter by book" oninput="renderFinesAdmin()">
      <button class="btn btn-ghost btn-sm" onclick="clearFineAdminFilters()">${transactionActionIcon('loss')} Clear Filters</button>
    </div>`;
  }
  if(!fines.length){
    tbody.innerHTML='<tr><td colspan="9"><div class="empty-state"><p>No fines match your filters.</p></div></td></tr>';
    renderPaginationMount('adminFines', tbody.closest('.table-wrap'), '');
    return;
  }
  tbody.innerHTML=pagedFines.items.map(f=>{
    const user=users.find(u=>u.id===f.userId);
    const book=books.find(b=>b.id===f.bookId);
    const status = fineStatusValue(f);
    return `<tr>
      <td class="mono">${f.id}</td>
      <td><div class="td-primary">${user?.name||'?'}</div><div class="td-secondary">${user?.email||user?.username||''}</div></td>
      <td><div class="td-primary">${book?.title||'?'}</div><div class="td-secondary">${book?.author||''}</div></td>
      <td><span class="tag tag-reserved">${fineTypeLabel(f)}</span></td>
      <td class="${fineIsUnpaid(f)?'text-rust bold':'text-muted'}">₱${Number(f.amount || 0).toLocaleString('en-PH')}</td>
      <td>${f.paid ? `<span class="tag tag-available">Yes</span>` : '<span class="tag tag-neutral">No</span>'}</td>
      <td>${fineStatusTag(f)}</td>
      <td class="text-muted mono" style="font-size:.75rem">${dateStr(f.date)}</td>
      <td>${fineIsUnpaid(f)?`<button class="btn btn-sage btn-sm" onclick="payFine('${f.id}')">Mark Paid</button> <button class="btn btn-ghost btn-sm" onclick="waiveFine('${f.id}')">Waive</button> <button class="btn btn-ghost btn-sm" onclick="adjustFine('${f.id}')">Adjust</button>`:''}</td>
    </tr>`;
  }).join('');
  renderPaginationMount('adminFines', tbody.closest('.table-wrap'), renderPaginationControls({
    key:'adminFines',
    totalItems:pagedFines.totalItems,
    currentPage:pagedFines.currentPage,
    onPageChange:'renderFinesAdmin',
  }));
  if(activeControlId?.startsWith('fine-')) {
    const input = document.getElementById(activeControlId);
    input?.focus();
    if(input?.setSelectionRange) input.setSelectionRange(input.value.length, input.value.length);
  }
}

function payFine(fineId) {
  const fines = getFines();
  const idx = fines.findIndex(f=>f.id===fineId);
  fines[idx].paid=true;
  fines[idx].paidDate=Date.now();
  showToast('Fine marked as paid','success');
  renderFinesAdmin();
  renderAdminDashboard();
  buildSidebar();
}

// ══════════════════════════════════════════════
// NOTIFICATIONS
// ══════════════════════════════════════════════

function renderNotifications() {
  const notes = getMyNotifications();
  const el = document.getElementById('notifications-list');
  // Mark MY notifications as read
  const allNotes = getNotifications().map(function(n) {
    const mine = n.recipientId==='admin' || n.recipientId==='both';
    return mine ? Object.assign({},n,{read:true}) : n;
  });
  buildSidebar();
  if(!notes.length){ el.innerHTML='<div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg><p>No notifications.</p></div>'; return; }
  el.innerHTML = notes.map(function(n){ return '<div class="card mb-2" style="border-left:4px solid '+(n.type==='overdue'?'var(--rust)':n.type==='info'?'var(--gold)':'var(--sage)')+'"><div class="card-body" style="padding:14px 16px"><div style="display:flex;align-items:flex-start;gap:12px"><div style="flex:1"><p style="font-size:.875rem">'+n.message+'</p><p class="text-muted" style="font-size:.75rem;margin-top:4px">'+dateStr(n.date)+'</p></div>'+(!n.read?'<span class="notif-dot"></span>':'')+'</div></div></div>'; }).join('');
}

function renderStudentNotifications() {
  const notes = getMyNotifications();
  const el = document.getElementById('student-notifications-list');
  if(!el) return;
  // Mark MY notifications as read
  const uid_me = currentUser.id;
  const allNotes = getNotifications().map(function(n) {
    const mine = n.recipientId===uid_me || n.recipientId==='both';
    return mine ? Object.assign({},n,{read:true}) : n;
  });
  buildSidebar();
  if(!notes.length){ el.innerHTML='<div class="empty-state"><p>No notifications.</p></div>'; return; }
  el.innerHTML = notes.map(function(n){ return '<div class="card mb-2" style="border-left:4px solid '+(n.type==='overdue'?'var(--rust)':'var(--gold)')+'"><div class="card-body" style="padding:14px 16px"><p style="font-size:.875rem">'+n.message+'</p><p class="text-muted" style="font-size:.75rem;margin-top:4px">'+dateStr(n.date)+'</p></div></div>'; }).join('');
}

// ══════════════════════════════════════════════
// SETTINGS
// ══════════════════════════════════════════════

function renderSettings() {
  document.getElementById('settings-content').innerHTML = buildSettingsHTML();
}
function renderStudentSettings() {
  const el = document.getElementById('student-settings-content');
  if(el) el.innerHTML = buildStudentProfileHTML();
}

function buildStudentProfileHTML() {
  const users = getUsers();
  const u = users.find(x=>x.id===currentUser.id) || currentUser;
  currentUser = u;
  const currentUserId = normalizeId(u.id);
  const currentMemberId = normalizeId(u.memberId);
  const belongsToCurrentUser = item => item.userId === currentUserId || (currentMemberId && item.memberId === currentMemberId);
  const returnedLoans = getTransactions().filter(tx=>belongsToCurrentUser(tx) && (tx.returned || String(tx.status || '').toLowerCase()==='returned'));
  const memberSince = u.createdAt ? dateStr(u.createdAt) : 'Not available';
  const fullName = getFullName(u);
  const phone = u.phone || u.phoneNumber || '';
  const dob = u.dateOfBirth || u.dob || '';
  return `
    <div class="member-profile-layout">
      <div class="card member-profile-summary">
        <div class="card-body">
          <img class="member-profile-avatar" id="settings-avatar-preview" src="${escapeAttr(u.avatar||`https://api.dicebear.com/7.x/personas/svg?seed=${u.id}`)}" alt="Profile image">
          <div class="member-profile-name">${escapeAttr(fullName)}</div>
          <div class="member-profile-email">${escapeAttr(u.email || '')}</div>
          <label class="btn btn-ghost btn-sm mt-3" style="cursor:pointer;display:inline-flex;align-items:center;gap:8px">
            Edit Profile Image
            <input type="file" accept="image/*" style="display:none" onchange="handleAvatarUpload(event)">
          </label>
          <div class="mt-2 text-muted" style="font-size:.75rem" id="avatar-upload-status">No file chosen</div>
        </div>
      </div>

      <div class="card member-profile-card">
        <div class="card-header">
          <span class="card-title">Profile Information</span>
          <button class="btn btn-ghost btn-sm" onclick="enableStudentProfileEdit()">Edit Profile</button>
        </div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label class="form-label">First Name</label><input class="form-input student-profile-editable" id="settings-firstname" value="${escapeAttr(u.firstName||'')}" disabled></div>
            <div class="form-group"><label class="form-label">Last Name</label><input class="form-input student-profile-editable" id="settings-lastname" value="${escapeAttr(u.lastName||'')}" disabled></div>
            <div class="form-group"><label class="form-label">Full Name</label><input class="form-input" value="${escapeAttr(fullName)}" disabled></div>
            <div class="form-group"><label class="form-label">Email</label><input class="form-input student-profile-editable" id="settings-email" value="${escapeAttr(u.email||'')}" type="email" disabled></div>
            <div class="form-group"><label class="form-label">Phone</label><input class="form-input student-profile-editable" id="settings-phone" value="${escapeAttr(phone)}" placeholder="Not available" disabled></div>
            <div class="form-group"><label class="form-label">DOB</label><input class="form-input student-profile-editable" id="settings-dob" value="${escapeAttr(dob)}" type="date" disabled></div>
          </div>
          <button class="btn btn-primary" id="student-profile-save" onclick="saveProfileInfo()" disabled>Save Profile</button>
        </div>
      </div>

      <div class="stats-grid member-reading-stats">
        <div class="stat-card sage"><div class="stat-label">Books Read</div><div class="stat-value">${returnedLoans.length}</div><div class="stat-sub">Returned loans</div></div>
        <div class="stat-card slate"><div class="stat-label">Current Streak</div><div class="stat-value" style="font-size:1.5rem">Not tracked</div><div class="stat-sub">No streak data available</div></div>
        <div class="stat-card"><div class="stat-label">Member Since</div><div class="stat-value" style="font-size:1.5rem">${escapeAttr(memberSince)}</div><div class="stat-sub">Account created date</div></div>
      </div>

      <div class="card member-security-card">
        <div class="card-header"><span class="card-title">Security</span></div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group"><label class="form-label">Username</label><input class="form-input" id="settings-username" value="${escapeAttr(u.username || '')}" disabled><div class="field-error" id="settings-username-error" aria-live="polite"></div></div>
            <div class="form-group"><label class="form-label">New Password</label><input class="form-input" id="settings-password" type="password" placeholder="Leave blank to keep current"><div class="field-error" id="settings-password-error" aria-live="polite"></div></div>
            <div class="form-group"><label class="form-label">Confirm Password</label><input class="form-input" id="settings-confirm" type="password" placeholder="Confirm new password"><div class="field-error" id="settings-confirm-error" aria-live="polite"></div></div>
          </div>
          <button class="btn btn-primary" onclick="saveAccountSecurity()">Update Password</button>
        </div>
      </div>
    </div>`;
}

function enableStudentProfileEdit() {
  document.querySelectorAll('.student-profile-editable').forEach(input=>input.disabled = false);
  const save = document.getElementById('student-profile-save');
  if(save) save.disabled = false;
  document.getElementById('settings-firstname')?.focus();
}

const MEMBER_SETTINGS_OPTIONS = [
  {id:'transaction-alerts', key:'transaction_alerts', label:'Transaction Alerts', description:'Show in-app notices for loans, reservations, and borrow requests.', checked:true},
  {id:'due-reminders', key:'due_reminders', label:'Due Reminders', description:'Show in-app reminders before loans are due.', checked:true},
  {id:'overdue-alerts', key:'overdue_alerts', label:'Overdue Alerts', description:'Show in-app notices when borrowed books are overdue.', checked:true},
  {id:'fines-payment-notices', key:'fines_payment_notices', label:'Fines & Payment Notices', description:'Show in-app notices for fines and payment updates.', checked:true},
  {id:'new-arrivals', key:'new_arrivals', label:'New Arrivals', description:'Show in-app notices for newly added books when available.', checked:false},
  {id:'recommendations', key:'recommendations', label:'Recommendations', description:'Show in-app notices for suggested available books when available.', checked:true},
  {id:'marketing-emails', key:'marketing_emails', label:'Marketing Emails', description:'Email delivery is unavailable in this installation.', checked:false, channel:'email'},
];

function renderStudentPreferences() {
  const el = document.getElementById('student-preferences-content');
  if(!el) return;
  el.innerHTML = notificationPreferencesHTML();
}

function notificationPreferencesHTML() {
  const preferences = getPreferences();
  const emailAvailable = normalizeBoolean(apiState.settings?.email_enabled ?? false);
  return `
    <div class="card member-settings-card">
      <div class="card-header">
        <span class="card-title">Notification Preferences</span>
        <span class="tag tag-available">Saved</span>
      </div>
      <div class="card-body">
        <div class="member-settings-list">
          ${MEMBER_SETTINGS_OPTIONS.map(option=>{
            const unavailable = option.channel === 'email' && !emailAvailable;
            return `
            <label class="member-setting-row" for="member-setting-${option.id}">
              <div>
                <div class="member-setting-title">${option.label} ${unavailable ? '<span class="tag tag-neutral">Unavailable</span>' : ''}</div>
                <div class="member-setting-desc">${option.description}</div>
              </div>
              <span class="toggle-switch">
                <input id="member-setting-${option.id}" data-key="${option.key}" type="checkbox" ${(preferences[option.key] ?? option.checked)?'checked':''} ${unavailable?'disabled':''} onchange="handleMemberSettingToggle(this)">
                <span></span>
              </span>
            </label>
          `}).join('')}
          <div class="member-setting-row" style="cursor:default">
            <div>
              <div class="member-setting-title">SMS Notifications <span class="tag tag-neutral">Unavailable</span></div>
              <div class="member-setting-desc">SMS delivery is not configured for this system.</div>
            </div>
          </div>
        </div>
      </div>
    </div>`;
}

async function handleMemberSettingToggle(input) {
  const key = input.dataset.key;
  if(!key || input.disabled) return;
  const previous = !input.checked;
  input.disabled = true;
  try {
    const data = await apiRequest(API_ENDPOINTS.preferences, {action:'update', preferences:{[key]: input.checked}});
    apiState.preferences = normalizePreferences(data.preferences);
    showToast('Preference saved.', 'success');
  } catch (error) {
    input.checked = previous;
    showToast(error.message, 'error');
  } finally {
    input.disabled = false;
  }
}

function buildSettingsHTML() {
  const users = getUsers();
  const u = users.find(x=>x.id===currentUser.id) || currentUser;
  currentUser = u; // sync in-memory with persisted data
  return `
    <div class="settings-section">
      <h3>Profile Picture</h3>
      <div class="avatar-editor">
        <img class="avatar-preview" id="settings-avatar-preview" src="${u.avatar||`https://api.dicebear.com/7.x/personas/svg?seed=${u.id}`}" alt="avatar">
        <div style="flex:1">
          <p class="text-muted mb-2" style="font-size:.82rem">Upload a photo from your device. JPG, PNG or GIF — max 2MB.</p>
          <label class="btn btn-ghost btn-sm" style="cursor:pointer;display:inline-flex;align-items:center;gap:8px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Choose Photo
            <input type="file" accept="image/*" style="display:none" onchange="handleAvatarUpload(event)">
          </label>
          <div class="mt-2 text-muted" style="font-size:.75rem" id="avatar-upload-status">No file chosen</div>
        </div>
      </div>
    </div>
    <div class="settings-section">
      <h3>Personal Information</h3>
      <div class="form-grid">
        <div class="form-group"><label class="form-label">First Name</label><input class="form-input" id="settings-firstname" value="${u.firstName||''}"></div>
        <div class="form-group"><label class="form-label">Last Name</label><input class="form-input" id="settings-lastname" value="${u.lastName||''}"></div>
        <div class="form-group"><label class="form-label">Email</label><input class="form-input" id="settings-email" value="${u.email||''}" type="email"></div>
      </div>
      <button class="btn btn-primary" onclick="saveProfileInfo()">Save Profile</button>
    </div>
    ${notificationPreferencesHTML()}
    <div class="settings-section">
      <h3>Account Security</h3>
      <div class="form-grid">
        <div class="form-group"><label class="form-label">Username</label><input class="form-input" id="settings-username" value="${u.username}"><div class="field-error" id="settings-username-error" aria-live="polite"></div></div>
        <div class="form-group"><label class="form-label">New Password</label><input class="form-input" id="settings-password" type="password" placeholder="Leave blank to keep current"><div class="field-error" id="settings-password-error" aria-live="polite"></div></div>
        <div class="form-group"><label class="form-label">Confirm Password</label><input class="form-input" id="settings-confirm" type="password" placeholder="Confirm new password"><div class="field-error" id="settings-confirm-error" aria-live="polite"></div></div>
      </div>
      <button class="btn btn-primary" onclick="saveAccountSecurity()">Update Security</button>
    </div>`;
}

function handleAvatarUpload(event) {
  const file = event.target.files[0];
  if(!file) return;
  if(file.size > 2*1024*1024){ showToast('File too large — max 2MB','error'); return; }
  const statusEl = document.getElementById('avatar-upload-status');
  if(statusEl) statusEl.textContent = `Loading "${file.name}"…`;
  const reader = new FileReader();
  reader.onload = function(e) {
    const dataUrl = e.target.result;
    // Preview immediately
    const preview = document.getElementById('settings-avatar-preview');
    if(preview) preview.src = dataUrl;
    if(statusEl) statusEl.textContent = `\u2713 "${file.name}" uploaded successfully`;
    // Save to user record.
    updateCurrentUser({avatar: dataUrl});
    updateSidebarUser();
    showToast('Profile picture updated!','success');
  };
  reader.onerror = ()=>{ showToast('Failed to read file','error'); };
  reader.readAsDataURL(file);
}

function saveProfileInfo() {
  const firstName = document.getElementById('settings-firstname').value.trim();
  const lastName = document.getElementById('settings-lastname').value.trim();
  const email = document.getElementById('settings-email').value.trim();
  if(!firstName||!lastName){ showToast('First name and last name cannot be empty','error'); return; }
  updateCurrentUser({firstName,lastName,email});
  updateSidebarUser();
  // Refresh whichever settings page is active
  if(currentUser.role==='admin') renderSettings();
  else renderStudentSettings();
  showToast('Profile updated!','success');
}

function saveAccountSecurity() {
  const username = document.getElementById('settings-username').value.trim();
  const pw = document.getElementById('settings-password').value;
  const confirm = document.getElementById('settings-confirm').value;
  if(!username){ showToast('Username cannot be empty','error'); return; }
  const users = getUsers();
  const exists = users.find(u=>u.username===username&&u.id!==currentUser.id);
  if(exists){ showToast('Username already taken','error'); return; }
  if(pw&&pw!==confirm){ showToast('Passwords do not match','error'); return; }
  const updates = {username};
  if(pw) updates.password = pw;
  updateCurrentUser(updates);
  showToast('Account updated!','success');
}

function updateCurrentUser(updates) {
  const users = getUsers();
  const idx = users.findIndex(u=>u.id===currentUser.id);
  users[idx] = {...users[idx],...updates};
  currentUser = users[idx];
}

// ══════════════════════════════════════════════
// STUDENT — DASHBOARD
// ══════════════════════════════════════════════

function activeLoansForBook(bookId) {
  const id = normalizeId(bookId);
  return getTransactions().filter(t=>t.bookId===id&&!t.returned&&!t.lost);
}

function currentUserHasActiveLoanForBook(bookId) {
  return activeLoansForBook(bookId).some(t=>t.userId===currentUser?.id);
}

function setStudentDashboardTab(tab) {
  if(window._studentDashboardTab !== tab) paginationState[`studentDashboard-${tab}`] = 1;
  window._studentDashboardTab = tab;
  renderStudentDashboard();
}

function renderStudentDashboard() {
  const page = document.getElementById('page-student-dashboard');
  if(!page || !currentUser) return;

  const currentUserId = normalizeId(currentUser.id);
  const currentMemberId = normalizeId(currentUser.memberId);
  const belongsToCurrentUser = item => item.userId === currentUserId || (currentMemberId && item.memberId === currentMemberId);
  const books = getBooks();
  const myLoans = getTransactions().filter(belongsToCurrentUser);
  const activeLoans = myLoans.filter(t=>!t.returned&&!t.lost && String(t.status || 'borrowed').toLowerCase() === 'borrowed')
    .sort((a,b)=>new Date(a.dueDate)-new Date(b.dueDate));
  const returnedLoans = myLoans.filter(t=>t.returned || String(t.status || '').toLowerCase() === 'returned')
    .sort((a,b)=>(b.returnedDate || b.created || 0) - (a.returnedDate || a.created || 0));
  const overdueLoans = activeLoans.filter(t=>t.dueDate && new Date(t.dueDate) < Date.now());
  const myReservations = apiState.reservations.filter(belongsToCurrentUser)
    .sort((a,b)=>(b.date || 0) - (a.date || 0));
  const activeReservations = myReservations.filter(reservationIsOpen);
  const myFines = getFines().filter(belongsToCurrentUser);
  const unpaidFines = myFines.filter(f=>fineIsUnpaid(f) || String(f.status || '').toLowerCase() === 'pending');
  const fineBalance = unpaidFines.reduce((sum,f)=>sum + Number(f.amount || 0), 0);
  const recommendationBooks = books
    .filter(b=>getAvailableCopies(b.id) > 0 && !activeLoans.some(t=>t.bookId===b.id))
    .slice(0, 6);
  const activeTab = ['borrowed','reservations','history','recommendations'].includes(window._studentDashboardTab)
    ? window._studentDashboardTab
    : 'borrowed';

  const icon = {
    book: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>',
    stack: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19V5a2 2 0 012-2h12"/><path d="M8 3v18"/><path d="M20 7v14H6a2 2 0 110-4h14"/></svg>',
    fine: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h6M7 16h4"/></svg>',
    clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    bookmark: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>',
  };

  const statusTag = tx => {
    const dl = daysLeft(tx.dueDate);
    return tx.dueDate && new Date(tx.dueDate) < Date.now()
      ? `<span class="tag tag-overdue">${Math.abs(dl)}d overdue</span>`
      : `<span class="tag tag-borrowed">${dl}d left</span>`;
  };
  const bookTitle = book => escapeAttr(book?.title || 'Unknown title');
  const bookAuthor = book => escapeAttr(book?.author || 'Unknown Author');
  const displayDate = value => value ? dateStr(value) : 'No date';
  const compactBookRow = (book, meta, right = '') => `
    <div class="member-dashboard-row">
      <div>
        <div class="member-dashboard-row-title">${bookTitle(book)}</div>
        <div class="member-dashboard-row-meta">${bookAuthor(book)}${meta ? ` · ${meta}` : ''}</div>
      </div>
      ${right ? `<div class="member-dashboard-row-action">${right}</div>` : ''}
    </div>`;
  const dashboardListHtml = (key, items, emptyHtml, renderItem) => {
    const paged = paginateItems(items, paginationState[key] || 1);
    paginationState[key] = paged.currentPage;
    if(!items.length) return emptyHtml;
    return paged.items.map(renderItem).join('') + renderPaginationControls({
      key,
      totalItems:paged.totalItems,
      currentPage:paged.currentPage,
      onPageChange:'renderStudentDashboard',
    });
  };

  const borrowedHtml = dashboardListHtml(
    'studentDashboard-borrowed',
    activeLoans,
    '<div class="empty-state"><p>No current borrowed books</p></div>',
    tx=>{
        const book = books.find(b=>b.id===tx.bookId);
        return compactBookRow(book, `Due ${displayDate(tx.dueDate)}`, statusTag(tx));
      }
  );

  const reservationsHtml = dashboardListHtml(
    'studentDashboard-reservations',
    myReservations,
    '<div class="empty-state"><p>No reservations yet</p></div>',
    r=>{
        const book = books.find(b=>b.id===r.bookId);
        const available = getAvailableCopies(r.bookId) > 0;
        const tag = available ? '<span class="tag tag-available">Available</span>' : `<span class="tag tag-reserved">${escapeAttr(r.status || 'Active')}</span>`;
        return compactBookRow(book, `Reserved ${displayDate(r.date)}`, tag);
      }
  );

  const historyHtml = dashboardListHtml(
    'studentDashboard-history',
    returnedLoans,
    '<div class="empty-state"><p>No reading history yet</p></div>',
    tx=>{
        const book = books.find(b=>b.id===tx.bookId);
        return compactBookRow(book, `Returned ${displayDate(tx.returnedDate || tx.created)}`, '<span class="tag tag-available">Completed</span>');
      }
  );

  const recommendationsHtml = dashboardListHtml(
    'studentDashboard-recommendations',
    recommendationBooks,
    '<div class="empty-state"><p>No recommendations available</p></div>',
    book=>compactBookRow(book, `${getAvailableCopies(book.id)} available`, '<span class="tag tag-available">Suggested</span>')
  );

  const tabContent = {
    borrowed: borrowedHtml,
    reservations: reservationsHtml,
    history: historyHtml,
    recommendations: recommendationsHtml,
  };

  page.innerHTML = `
    <div class="page-header member-dashboard-header">
      <div>
        <div class="page-title">Dashboard</div>
        <div class="page-subtitle">Track your reading journey and manage your library</div>
      </div>
      <div class="page-actions">
        <button class="admin-header-icon-btn" type="button" aria-label="Notifications" onclick="navigateTo('student-notifications')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        </button>
        <button class="admin-header-icon-btn" type="button" aria-label="Profile" onclick="navigateTo('student-settings')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 10-16 0"/><circle cx="12" cy="7" r="4"/></svg>
        </button>
      </div>
    </div>


    <div class="stats-grid member-dashboard-stats">
      <div class="stat-card"><div class="member-stat-icon">${icon.book}</div><div class="stat-value">${activeLoans.length}</div><div class="stat-label">Books Borrowed</div><div class="stat-sub">Current active borrowed books</div></div>
      <div class="stat-card sage"><div class="member-stat-icon">${icon.stack}</div><div class="stat-value">${myLoans.length}</div><div class="stat-label">Total Borrowed</div><div class="stat-sub">All historical borrowed books</div></div>
      <div class="stat-card rust"><div class="member-stat-icon">${icon.fine}</div><div class="stat-value">₱${fineBalance.toLocaleString('en-PH')}</div><div class="stat-label">My Fines</div><div class="stat-sub">${unpaidFines.length} unpaid or pending fine${unpaidFines.length === 1 ? '' : 's'}</div></div>
      <div class="stat-card slate"><div class="member-stat-icon">${icon.clock}</div><div class="stat-value">${overdueLoans.length}</div><div class="stat-label">Overdue</div><div class="stat-sub">Active loans past due date</div></div>
      <div class="stat-card"><div class="member-stat-icon">${icon.bookmark}</div><div class="stat-value">${activeReservations.length}</div><div class="stat-label">Reservations</div><div class="stat-sub">Current active reservations</div></div>
      <div class="stat-card sage"><div class="member-stat-icon">${icon.check}</div><div class="stat-value">${returnedLoans.length}</div><div class="stat-label">Books Read</div><div class="stat-sub">Returned or completed loans</div></div>
    </div>

    <div class="card member-dashboard-tabs-card">
      <div class="member-dashboard-tabs" role="tablist" aria-label="Dashboard sections">
        <button class="${activeTab==='borrowed'?'active':''}" type="button" onclick="setStudentDashboardTab('borrowed')">Current Borrowed</button>
        <button class="${activeTab==='reservations'?'active':''}" type="button" onclick="setStudentDashboardTab('reservations')">Reservations</button>
        <button class="${activeTab==='history'?'active':''}" type="button" onclick="setStudentDashboardTab('history')">Reading History</button>
        <button class="${activeTab==='recommendations'?'active':''}" type="button" onclick="setStudentDashboardTab('recommendations')">Recommendations</button>
      </div>
      <div class="member-dashboard-tab-panel">
        ${tabContent[activeTab]}
      </div>
    </div>`;
}

function viewStudentBookDetail(id) {
  const b = getBooks().find(x=>x.id===id);
  if(!b) return;
  const avail = getAvailableCopies(id);
  const myTx = getTransactions().find(t=>t.bookId===id&&t.userId===currentUser.id&&!t.returned&&!t.lost);
  const myRes = getReservations().find(r=>r.bookId===id&&r.userId===currentUser.id);
  const coverImg = b.cover?`<img src="${b.cover}" style="width:100%;max-height:200px;object-fit:cover;border-radius:6px;margin-bottom:16px" onerror="this.style.display='none'">` : '';
  let actionHtml='';
  const hasReplacementFine = getFines().some(f=>f.userId===currentUser.id&&fineIsUnpaid(f)&&f.isReplacementFee);
  if(hasReplacementFine) actionHtml='<button class="btn btn-danger" disabled>⛔ Borrowing Suspended — Settle Fine First</button>';
  else if(myTx) actionHtml='<button class="btn btn-ghost" disabled>Already Borrowed</button>';
  else if(avail>0) actionHtml=`<button class="btn btn-gold" onclick="closeModal('modal-book-detail');openStudentBorrow('${id}')">Borrow This Book</button>`;
  else if(myRes) actionHtml=`<button class="btn btn-ghost" onclick="cancelReservationByBook('${id}');closeModal('modal-book-detail')">Cancel Reservation</button>`;
  else actionHtml=`<button class="btn btn-primary" onclick="reserveBook('${id}');closeModal('modal-book-detail')">Reserve Book</button>`;
  document.getElementById('book-detail-content').innerHTML = `${coverImg}
    <div style="font-family:'Playfair Display',serif;font-size:1.5rem;margin-bottom:4px">${b.title}</div>
    <div class="text-muted mb-2">${b.author} ${b.year?'· '+b.year:''}</div>
    ${b.subject?`<span class="tag" style="background:var(--cream);color:var(--slate);margin-bottom:12px">${b.subject}</span>`:''}
    <p class="text-muted mb-3" style="font-size:.875rem;line-height:1.7">${b.desc||''}</p>
    <div style="font-size:.8rem;color:var(--muted);margin-bottom:16px">
      <div>Publisher: ${b.publisher||'N/A'} · ISBN: <span class="mono">${b.isbn||'N/A'}</span></div>
      <div>Rack: <strong>${b.rack||'N/A'}</strong> · ${avail} cop${avail===1?'y':'ies'} available</div>
      <div>Daily late fee: ₱${b.lateFeePerDay||20}/day · Replacement value: ₱${b.baseFine||300}</div>
    </div>
    ${actionHtml}`;
  openModal('modal-book-detail');
}

function updateBorrowDaysDisplay() {
  const picker = document.getElementById('student-return-date');
  const display = document.getElementById('borrow-days-display');
  if(!picker.value){ display.textContent=''; return; }
  const days = Math.ceil((new Date(picker.value) - new Date().setHours(0,0,0,0)) / (1000*60*60*24));
  display.textContent = `That's ${days} day${days===1?'':'s'} from today.`;
}

// ══════════════════════════════════════════════
// STUDENT — MY FINES
// ══════════════════════════════════════════════

function renderMyFines() {
  const currentUserId = normalizeId(currentUser?.id);
  const currentMemberId = normalizeId(currentUser?.memberId);
  const belongsToCurrentUser = item => item.userId === currentUserId || (currentMemberId && item.memberId === currentMemberId);
  const allFines = getFines().filter(belongsToCurrentUser);
  const books = getBooks();
  const statsEl = document.getElementById('my-fines-stats');
  const filtersEl = document.getElementById('my-fines-filters');
  const el = document.getElementById('my-fines-content');
  if(!statsEl || !filtersEl || !el) return;

  const fineType = fine => String(fine.fineType || fine.fine_type || fine.reason || 'Fine').trim() || 'Fine';
  const fineStatus = fine => fine.waived || fine.status === 'waived' ? 'waived' : fineIsUnpaid(fine) ? 'unpaid' : 'paid';
  const pendingFines = allFines.filter(f=>fineStatus(f)==='unpaid');
  const paidFines = allFines.filter(f=>fineStatus(f)==='paid');
  const totalOutstanding = pendingFines.reduce((sum,f)=>sum + Number(f.amount || 0), 0);
  const totalPaid = paidFines.reduce((sum,f)=>sum + Number(f.amount || 0), 0);
  const selectedStatus = document.getElementById('my-fines-status-filter')?.value || 'all';
  const selectedType = document.getElementById('my-fines-type-filter')?.value || 'all';
  const q = searchValue('my-fines-search');
  const typeOptions = [...new Set(allFines.map(fineType))].sort((a,b)=>a.localeCompare(b));

  statsEl.innerHTML = `
    <div class="stat-card rust"><div class="stat-label">Pending Fines</div><div class="stat-value">${pendingFines.length}</div><div class="stat-sub">Unpaid fine records</div></div>
    <div class="stat-card sage"><div class="stat-label">Paid Fines</div><div class="stat-value">${paidFines.length}</div><div class="stat-sub">Settled fine records</div></div>
    <div class="stat-card"><div class="stat-label">Total Outstanding</div><div class="stat-value">₱${totalOutstanding.toLocaleString('en-PH')}</div><div class="stat-sub">Unpaid balance</div></div>
    <div class="stat-card slate"><div class="stat-label">Total Paid</div><div class="stat-value">₱${totalPaid.toLocaleString('en-PH')}</div><div class="stat-sub">Settled amount</div></div>`;

  filtersEl.innerHTML = `
    <div class="filter-control-row member-fines-filters">
      <select class="form-select" id="my-fines-status-filter" onchange="renderMyFines()">
        <option value="all" ${selectedStatus==='all'?'selected':''}>All Statuses</option>
        <option value="unpaid" ${selectedStatus==='unpaid'?'selected':''}>Pending</option>
        <option value="paid" ${selectedStatus==='paid'?'selected':''}>Paid</option>
        <option value="waived" ${selectedStatus==='waived'?'selected':''}>Waived</option>
      </select>
      <select class="form-select" id="my-fines-type-filter" onchange="renderMyFines()">
        <option value="all" ${selectedType==='all'?'selected':''}>All Types</option>
        ${typeOptions.map(type=>`<option value="${escapeAttr(type)}" ${selectedType===type?'selected':''}>${escapeAttr(type)}</option>`).join('')}
      </select>
      <button class="btn btn-ghost btn-sm" onclick="clearMyFinesFilters()">Clear</button>
    </div>`;

  const fines = allFines.filter(fine=>{
    const book = books.find(b=>b.id===fine.bookId);
    if(selectedStatus !== 'all' && fineStatus(fine) !== selectedStatus) return false;
    if(selectedType !== 'all' && fineType(fine) !== selectedType) return false;
    return matchesSearch(q, [fine.id, book?.title, fineType(fine), fine.reason, fine.status, dateStr(fine.date)]);
  }).sort((a,b)=>(b.date || 0) - (a.date || 0));
  resetPaginationOnFilterChange('myFines', JSON.stringify({selectedStatus, selectedType, q}));
  const pagedFines = paginateItems(fines, paginationState.myFines || 1);
  paginationState.myFines = pagedFines.currentPage;

  if(!fines.length) {
    el.innerHTML = '<div class="empty-state"><p>No fines</p></div>';
    renderPaginationMount('myFines', el.closest('.page'), '');
    return;
  }

  el.innerHTML = `<div class="card"><div class="card-body" style="padding:0"><div class="table-wrap"><table>
    <thead><tr><th>Fine ID</th><th>Book</th><th>Type</th><th>Amount</th><th>Status</th><th>Created Date</th><th>Actions</th></tr></thead>
    <tbody>${pagedFines.items.map(f=>{
      const book = books.find(b=>b.id===f.bookId);
      const isProcessing = pendingFinePaymentClicks.has(f.id);
      const action = fineStatus(f) === 'unpaid' ? `<button class="btn btn-sage btn-sm" data-pay-fine-id="${f.id}" onclick="payFineOnline('${f.id}', this)" ${isProcessing?'disabled':''}>${isProcessing?'Processing...':'Pay'}</button>` : '';
      return `<tr>
        <td class="mono text-muted" style="font-size:.78rem">${escapeAttr(f.id)}</td>
        <td><strong>${escapeAttr(book?.title || 'Unknown book')}</strong></td>
        <td>${escapeAttr(fineType(f))}</td>
        <td class="${fineStatus(f)==='unpaid'?'text-rust bold':'text-muted'}">₱${Number(f.amount || 0).toLocaleString('en-PH')}</td>
        <td>${fineStatusTag(f)}</td>
        <td class="text-muted mono" style="font-size:.75rem">${f.date ? dateStr(f.date) : 'No date'}</td>
        <td>${action}</td>
      </tr>`;
    }).join('')}</tbody>
  </table></div>${renderPaginationControls({key:'myFines', totalItems:pagedFines.totalItems, currentPage:pagedFines.currentPage, onPageChange:'renderMyFines'})}</div></div>`;
}

function clearMyFinesFilters() {
  const search = document.getElementById('my-fines-search');
  const status = document.getElementById('my-fines-status-filter');
  const type = document.getElementById('my-fines-type-filter');
  if(search) search.value = '';
  if(status) status.value = 'all';
  if(type) type.value = 'all';
  paginationState.myFines = 1;
  renderMyFines();
}

// ══════════════════════════════════════════════
// KEYBOARD SHORTCUTS
// ══════════════════════════════════════════════

document.addEventListener('keydown', e=>{
  if(e.key==='Escape') {
    document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(m=>m.classList.add('hidden'));
  }
});

// Click outside modal to close
document.querySelectorAll('.modal-overlay').forEach(overlay=>{
  overlay.addEventListener('click', e=>{
    if(e.target===overlay) overlay.classList.add('hidden');
  });
});

// Reset book modal when opening fresh
function openModal(id){
  if(id==='modal-add-book') {
    const editing = document.getElementById('edit-book-id').value;
    if(!editing) {
      document.getElementById('book-modal-title').textContent='Add New Book';
      ['book-title','book-author','book-isbn','book-subject','book-publisher','book-year','book-copies','book-late-fee','book-fine','book-rack','book-cover-url','book-desc'].forEach(f=>{ const el=document.getElementById(f); if(el) el.value=''; });
      const fi=document.getElementById('book-cover-file'); if(fi) fi.value='';
      const ce=document.getElementById('cover-validation-error'); if(ce) ce.style.display='none';
      document.getElementById('book-copies').value='1';
      document.getElementById('book-late-fee').value='20';
      document.getElementById('book-fine').value='300';
    }
  }
  document.getElementById(id).classList.remove('hidden');
}

// Clear edit-book-id when the add-book modal closes so next open is fresh
document.getElementById('modal-add-book').addEventListener('click', e=>{
  if(e.target===document.getElementById('modal-add-book')) document.getElementById('edit-book-id').value='';
});
function closeModal(id){
  document.getElementById(id).classList.add('hidden');
  if(id==='modal-add-book') document.getElementById('edit-book-id').value='';
}

// ══════════════════════════════════════════════
// BACKEND API INTEGRATION
// Source of truth: PHP/MySQL JSON API.
// ══════════════════════════════════════════════

const API_ENDPOINTS = {
  auth: 'api/auth.php',
  state: 'api/state.php',
  books: 'api/books.php',
  members: 'api/members.php',
  categories: 'api/categories.php',
  authors: 'api/authors.php',
  publishers: 'api/publishers.php',
  loanRequests: 'api/loan_requests.php',
  loans: 'api/loans.php',
  reservations: 'api/reservations.php',
  fines: 'api/fines.php',
  fineRules: 'api/fine_rules.php',
  payments: 'api/payments.php',
  notifications: 'api/notifications.php',
  preferences: 'api/preferences.php',
  reports: 'api/reports.php',
};

let apiState = {
  books: [],
  users: [],
  transactions: [],
  fines: [],
  reservations: [],
  requests: [],
  notifications: [],
  fineRules: [],
  preferences: {},
  settings: {},
  categories: [],
  authors: [],
  publishers: [],
  reports: null,
};

const pendingFinePaymentClicks = new Set();
const PAYMENT_RETURN_FINE_KEY = 'quadbyte_lms_pending_payment_fine';
const paginationState = {};
const paginationFilterState = {};
const DEFAULT_PAGE_SIZE = 5;
let csrfToken = '';

function getTotalPages(totalItems, pageSize = DEFAULT_PAGE_SIZE) {
  return Math.max(1, Math.ceil(Number(totalItems || 0) / pageSize));
}

function paginateItems(items, currentPage, pageSize = DEFAULT_PAGE_SIZE) {
  const totalItems = items.length;
  const totalPages = getTotalPages(totalItems, pageSize);
  const page = Math.min(Math.max(parseInt(currentPage, 10) || 1, 1), totalPages);
  const start = (page - 1) * pageSize;

  return {
    items: totalItems > pageSize ? items.slice(start, start + pageSize) : items,
    currentPage: page,
    totalPages,
    totalItems,
  };
}

function renderPaginationControls({key, totalItems, currentPage, pageSize = DEFAULT_PAGE_SIZE, onPageChange = 'renderVisiblePage'}) {
  if(totalItems <= pageSize) return '';
  const totalPages = getTotalPages(totalItems, pageSize);
  const page = Math.min(Math.max(parseInt(currentPage, 10) || 1, 1), totalPages);
  const buttons = Array.from({length: totalPages}, (_, index) => {
    const pageNumber = index + 1;
    return `<button class="pagination-btn ${pageNumber===page?'active':''}" type="button" onclick="setPaginationPage('${key}', ${pageNumber}, '${onPageChange}')" ${pageNumber===page?'aria-current="page"':''}>${pageNumber}</button>`;
  }).join('');

  return `<div class="pagination-controls" aria-label="Pagination">${buttons}</div>`;
}

function setPaginationPage(key, page, renderFunctionName = 'renderVisiblePage') {
  paginationState[key] = page;
  const renderer = window[renderFunctionName];
  if(typeof renderer === 'function') renderer();
  else renderVisiblePage();
}

function resetPaginationOnFilterChange(key, signature) {
  if(paginationFilterState[key] !== signature) {
    paginationFilterState[key] = signature;
    paginationState[key] = 1;
  }
}

function renderPaginationMount(key, anchor, controlsHtml) {
  document.querySelector(`[data-pagination-mount="${key}"]`)?.remove();
  if(!anchor || !controlsHtml) return;
  anchor.insertAdjacentHTML('afterend', `<div data-pagination-mount="${key}">${controlsHtml}</div>`);
}

async function apiRequest(endpoint, payload = {}) {
  const headers = {'Content-Type': 'application/json'};
  if(csrfToken) headers['X-CSRF-Token'] = csrfToken;

  const response = await fetch(endpoint, {
    method: 'POST',
    headers,
    credentials: 'same-origin',
    body: JSON.stringify(payload),
  });

  let json;
  try {
    json = await response.json();
  } catch (error) {
    throw new Error('Server returned an invalid JSON response.');
  }

  if (!response.ok || !json.success) {
    const error = new Error(json.message || 'Request failed.');
    error.errors = json.errors || {};
    throw error;
  }

  if (json.data?.csrf_token) csrfToken = json.data.csrf_token;

  return json.data || {};
}

function normalizeId(value) {
  return value === null || value === undefined ? null : String(value);
}

function normalizeBoolean(value) {
  return value === true || value === 1 || value === '1' || value === 'true';
}

function normalizeUser(user) {
  if (!user) return null;
  const firstName = user.firstName || user.first_name || '';
  const lastName = user.lastName || user.last_name || '';
  const username = user.username || '';
  const rawRoleSlug = user.roleSlug || user.role_slug || user.role || 'member';
  const roleSlug = rawRoleSlug === 'staff' ? 'member' : rawRoleSlug;
  return {
    ...user,
    id: normalizeId(user.id),
    memberId: normalizeId(user.memberId || user.member_id),
    username,
    firstName,
    lastName,
    name: user.name || `${firstName} ${lastName}`.trim() || username,
    role: roleSlug === 'admin' ? 'admin' : 'student',
    roleSlug,
    status: user.status || user.member_status || 'active',
    avatar: user.avatar || user.avatar_url || `https://api.dicebear.com/7.x/personas/svg?seed=${encodeURIComponent(username || user.id || 'user')}`,
    phone: user.phone || user.phone_number || '',
    dateOfBirth: user.dateOfBirth || user.date_of_birth || user.dob || '',
    dob: user.dob || user.dateOfBirth || user.date_of_birth || '',
    booksOut: Number(user.booksOut || user.books_out || 0),
    fineBalance: Number(user.fineBalance || user.fine_balance || 0),
  };
}

function normalizeBook(book) {
  return {
    ...book,
    id: normalizeId(book.id),
    categoryId: normalizeId(book.categoryId || book.category_id),
    publisherId: normalizeId(book.publisherId || book.publisher_id),
    title: book.title || '',
    author: book.author || book.authors || 'Unknown Author',
    isbn: book.isbn || '',
    subject: book.subject || book.category_name || '',
    publisher: book.publisher || book.publisher_name || '',
    year: Number(book.year || book.publication_year || 0),
    copies: Number(book.copies || book.total_copies || 0),
    availableCopies: Number(book.availableCopies || book.available_copies || 0),
    borrowedCopies: Number(book.borrowedCopies || book.borrowed_copies || 0),
    lostCopies: Number(book.lostCopies || book.lost_copies || 0),
    lateFeePerDay: Number(book.lateFeePerDay || book.late_fee_per_day || 20),
    baseFine: Number(book.baseFine || book.replacement_value || book.price || 500),
    rack: book.rack || book.rack_number || '',
    cover: book.cover || book.cover_url || '',
    desc: book.desc || book.description || '',
  };
}

function normalizeTransaction(tx) {
  const status = tx.status || 'borrowed';
  return {
    ...tx,
    id: normalizeId(tx.id),
    bookId: normalizeId(tx.bookId || tx.book_id),
    copyId: normalizeId(tx.copyId || tx.copy_id),
    userId: normalizeId(tx.userId || tx.user_id),
    memberId: normalizeId(tx.memberId || tx.member_id),
    requestId: normalizeId(tx.requestId || tx.loan_request_id),
    created: Number(tx.created || 0),
    dueDate: tx.dueDate || tx.due_date || '',
    returnedDate: Number(tx.returnedDate || 0),
    returned: normalizeBoolean(tx.returned) || status === 'returned',
    lost: normalizeBoolean(tx.lost) || status === 'lost' || status === 'damaged',
    lostOrDamaged: normalizeBoolean(tx.lostOrDamaged) || status === 'lost' || status === 'damaged',
    renewed: Number(tx.renewed || tx.renew_count || 0),
    status,
  };
}

function normalizeFine(fine) {
  const status = fine.status || (normalizeBoolean(fine.paid) ? 'paid' : 'unpaid');
  return {
    ...fine,
    id: normalizeId(fine.id),
    userId: normalizeId(fine.userId || fine.user_id),
    memberId: normalizeId(fine.memberId || fine.member_id),
    bookId: normalizeId(fine.bookId || fine.book_id),
    txId: normalizeId(fine.txId || fine.loan_transaction_id),
    amount: Number(fine.amount || 0),
    date: Number(fine.date || 0),
    paidDate: Number(fine.paidDate || 0),
    status,
    paid: status === 'paid',
    waived: status === 'waived',
    isReplacementFee: normalizeBoolean(fine.isReplacementFee),
  };
}

function normalizeFineRule(rule) {
  const type = rule.type || rule.fineType || rule.fine_type || 'manual';
  return {
    ...rule,
    id: normalizeId(rule.id),
    bookId: normalizeId(rule.bookId || rule.book_id),
    name: rule.name || '',
    type,
    fineType: type,
    amount: Number(rule.amount ?? (type === 'overdue' ? rule.amountPerDay : rule.defaultAmount) ?? 0),
    amountPerDay: rule.amountPerDay !== undefined ? Number(rule.amountPerDay) : (rule.amount_per_day !== undefined ? Number(rule.amount_per_day) : null),
    graceDays: Number(rule.graceDays ?? rule.grace_days ?? 0),
    defaultAmount: rule.defaultAmount !== undefined ? Number(rule.defaultAmount) : (rule.default_amount !== undefined ? Number(rule.default_amount) : null),
    useBookPrice: normalizeBoolean(rule.useBookPrice ?? rule.use_book_price),
    isActive: normalizeBoolean(rule.isActive ?? rule.is_active ?? rule.status === 'active'),
    status: normalizeBoolean(rule.isActive ?? rule.is_active ?? rule.status === 'active') ? 'active' : 'disabled',
  };
}

function normalizeRequest(request) {
  return {
    ...request,
    id: normalizeId(request.id),
    bookId: normalizeId(request.bookId || request.book_id),
    userId: normalizeId(request.userId || request.user_id),
    memberId: normalizeId(request.memberId || request.member_id),
    created: Number(request.created || 0),
    handledDate: Number(request.handledDate || 0),
    dueDate: request.dueDate || request.requested_due_date || '',
    status: request.status || 'pending',
  };
}

function normalizeReservation(reservation) {
  return {
    ...reservation,
    id: normalizeId(reservation.id),
    bookId: normalizeId(reservation.bookId || reservation.book_id),
    userId: normalizeId(reservation.userId || reservation.user_id),
    memberId: normalizeId(reservation.memberId || reservation.member_id),
    date: Number(reservation.date || 0),
    status: reservation.status || 'active',
    position: Number(reservation.position || reservation.queue_position || 1),
    readyAt: Number(reservation.readyAt || reservation.ready_at || 0),
    fulfilledAt: Number(reservation.fulfilledAt || reservation.fulfilled_at || 0),
    cancelledAt: Number(reservation.cancelledAt || reservation.cancelled_at || 0),
    expiredAt: Number(reservation.expiredAt || reservation.expired_at || 0),
    expiresAt: Number(reservation.expiresAt || reservation.expires_at || 0),
  };
}

function normalizeNotification(note) {
  const relatedType = note.relatedEntityType || note.related_entity_type || note.referenceType || note.reference_type || (note.loan_transaction_id || note.txId ? 'loan' : '');
  const relatedId = normalizeId(note.relatedEntityId || note.related_entity_id || note.referenceId || note.reference_id || note.loan_transaction_id || note.txId);
  return {
    ...note,
    id: normalizeId(note.id),
    recipientId: normalizeId(note.recipientId || note.user_id || note.target_role) || 'both',
    txId: normalizeId(note.txId || note.loan_transaction_id),
    message: note.message || '',
    title: note.title || '',
    type: note.type || 'info',
    date: Number(note.date || 0),
    read: normalizeBoolean(note.read ?? note.is_read),
    deletedAt: note.deletedAt || note.deleted_at || '',
    targetRole: note.targetRole || note.target_role || '',
    isShared: normalizeBoolean(note.isShared ?? note.is_shared ?? !normalizeId(note.recipientId || note.user_id)),
    isStaffOnly: normalizeBoolean(note.isStaffOnly ?? note.is_staff_only ?? (String(note.actionType || note.action_type || '').includes('staff_only') || relatedType === 'staff')),
    relatedEntityType: relatedType,
    relatedEntityId: relatedId,
    actionType: note.actionType || note.action_type || '',
    referenceId: relatedId,
    referenceType: relatedType,
    createdAt: note.createdAt || note.created_at || '',
  };
}

function normalizePreferences(preferences = {}) {
  return {
    transaction_alerts: normalizeBoolean(preferences.transaction_alerts ?? preferences.transactionAlerts ?? preferences.book_reminders ?? true),
    due_reminders: normalizeBoolean(preferences.due_reminders ?? preferences.dueReminders ?? preferences.due_date_alerts ?? true),
    overdue_alerts: normalizeBoolean(preferences.overdue_alerts ?? preferences.overdueAlerts ?? true),
    fines_payment_notices: normalizeBoolean(preferences.fines_payment_notices ?? preferences.finesPaymentNotices ?? true),
    email_notifications: normalizeBoolean(preferences.email_notifications ?? preferences.emailNotifications ?? true),
    book_reminders: normalizeBoolean(preferences.book_reminders ?? preferences.bookReminders ?? true),
    due_date_alerts: normalizeBoolean(preferences.due_date_alerts ?? preferences.dueDateAlerts ?? true),
    new_arrivals: normalizeBoolean(preferences.new_arrivals ?? preferences.newArrivals ?? false),
    recommendations: normalizeBoolean(preferences.recommendations ?? true),
    marketing_emails: normalizeBoolean(preferences.marketing_emails ?? preferences.marketingEmails ?? false),
  };
}

function applyState(data) {
  apiState.books = (data.books || []).map(normalizeBook);
  apiState.users = (data.users || []).map(normalizeUser).filter(Boolean);
  apiState.transactions = (data.transactions || []).map(normalizeTransaction);
  apiState.fines = (data.fines || []).map(normalizeFine);
  apiState.reservations = (data.reservations || []).map(normalizeReservation);
  apiState.requests = (data.requests || []).map(normalizeRequest);
  apiState.notifications = (data.notifications || []).map(normalizeNotification);
  apiState.fineRules = (data.fine_rules || data.fineRules || []).map(normalizeFineRule);
  apiState.preferences = normalizePreferences(data.preferences || apiState.preferences);
  apiState.settings = data.settings || apiState.settings || {};
  apiState.categories = data.categories || [];
  apiState.authors = data.authors || [];
  apiState.publishers = data.publishers || [];
  apiState.reports = data.reports || apiState.reports;
  if (data.csrf_token) csrfToken = data.csrf_token;
  if (data.currentUser) currentUser = normalizeUser(data.currentUser);
  updateReferenceDropdowns();
}

async function refreshState() {
  const data = await apiRequest(API_ENDPOINTS.state);
  applyState(data);
  return apiState;
}

function stateCounts() {
  return {
    books: apiState.books.length,
    users: apiState.users.length,
    transactions: apiState.transactions.length,
    fines: apiState.fines.length,
    reservations: apiState.reservations.length,
    requests: apiState.requests.length,
    notifications: apiState.notifications.length,
  };
}

function getBooks(){ return apiState.books; }
function getUsers(){ return apiState.users; }
function getTransactions(){ return apiState.transactions; }
function getFines(){ return apiState.fines; }
function getReservations(){ return apiState.reservations.filter(reservationIsOpen); }
function getRequests(){ return apiState.requests; }
function getNotifications(){ return apiState.notifications; }
function getPreferences(){ return normalizePreferences(apiState.preferences); }

function fineIsUnpaid(fine) {
  return (fine.status || (fine.paid ? 'paid' : 'unpaid')) === 'unpaid';
}

function fineStatusTag(fine) {
  if (fine.status === 'waived') return '<span class="tag tag-available">Waived</span>';
  return fine.paid ? '<span class="tag tag-available">Paid</span>' : '<span class="tag tag-overdue">Unpaid</span>';
}

function updateReferenceDropdowns() {
  const categoryOptions = '<option value="">All Genres</option>' + apiState.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
  ['book-category-filter', 'student-category-filter'].forEach(id => {
    const select = document.getElementById(id);
    if (!select) return;
    const selected = select.value;
    select.innerHTML = categoryOptions;
    if ([...select.options].some(option => option.value === selected)) select.value = selected;
  });
  const authorOptions = (apiState.authors || []).map(author => `<option value="${escapeAttr(author.name || '')}"></option>`).join('');
  const publisherOptions = (apiState.publishers || []).map(publisher => `<option value="${escapeAttr(publisher.name || '')}"></option>`).join('');
  const authorList = document.getElementById('book-author-options');
  const publisherList = document.getElementById('book-publisher-options');
  if(authorList) authorList.innerHTML = authorOptions;
  if(publisherList) publisherList.innerHTML = publisherOptions;
}

function renderVisiblePage() {
  const active = document.querySelector('.page.active');
  if (!active?.id?.startsWith('page-')) {
    buildSidebar();
    updateAdminHeader();
    return;
  }
  const page = active.id.replace('page-', '');
  const renders = {
    'dashboard': renderAdminDashboard,
    'books': renderAdminBooks,
    'genres': renderAdminGenres,
    'authors': renderAdminAuthors,
    'publishers': renderAdminPublishers,
    'borrow-requests': renderBorrowRequests,
    'reservations': renderAdminReservations,
    'members': renderMembers,
    'transactions': renderTransactions,
    'fines': renderFinesAdmin,
    'reports': renderReports,
    'notifications': renderNotifications,
    'settings': renderSettings,
    'student-dashboard': renderStudentDashboard,
    'catalog': renderStudentCatalog,
    'my-loans': renderMyLoans,
    'my-reservations': renderMyReservations,
    'my-fines': renderMyFines,
    'student-notifications': renderStudentNotifications,
    'student-settings': renderStudentSettings,
    'student-preferences': renderStudentPreferences,
  };
  if (renders[page]) renders[page]();
  buildSidebar();
  updateAdminHeader(page);
}

function renderRelatedViews(...renderers) {
  renderers.forEach(renderer => {
    if (typeof renderer === 'function') renderer();
  });
  renderVisiblePage();
}

function getAvailableCopies(bookId) {
  const book = getBooks().find(b => b.id === normalizeId(bookId));
  if (!book) return 0;
  if (book.availableCopies !== undefined) return Number(book.availableCopies);
  const activeTx = getTransactions().filter(t => t.bookId === normalizeId(bookId) && !t.returned && !t.lost);
  return Math.max(0, Number(book.copies || 0) - activeTx.length);
}

function getBadgeCount(type) {
  if(type==='borrow-requests') return getRequests().filter(r=>r.status==='pending').length;
  if(type==='reservations') return apiState.reservations.filter(reservationIsOpen).length;
  if(type==='fines') return getFines().filter(f=>fineIsUnpaid(f)).length;
  if(type==='my-fines') return getFines().filter(f=>f.userId===currentUser?.id&&fineIsUnpaid(f)).length;
  if(type==='my-reservations') {
    const currentUserId = normalizeId(currentUser?.id);
    const currentMemberId = normalizeId(currentUser?.memberId);
    return apiState.reservations.filter(r=>
      (r.userId === currentUserId || (currentMemberId && r.memberId === currentMemberId)) &&
      reservationIsOpen(r)
    ).length;
  }
  if(type==='notifications') return getMyNotifications().filter(n=>!n.read).length;
  return 0;
}

async function initStore() {
  try {
    const session = await apiRequest(API_ENDPOINTS.auth, {action:'check'});
    if (!session.authenticated || !session.user) return;
    currentUser = normalizeUser(session.user);
    await refreshState();
    showLoggedInShell();
    navigateTo(defaultPageForCurrentUser());
  } catch (error) {
    // No active session; stay on login screen.
  }
}

function showLoggedInShell() {
  const loginScreen = document.getElementById('login-screen');
  loginScreen.style.opacity = '0';
  loginScreen.style.transition = 'opacity .3s ease';
  setTimeout(()=>loginScreen.classList.add('hidden'), 300);
  document.getElementById('app').classList.add('visible');
  buildSidebar();
  updateSidebarUser();
  updateAdminHeader();
}

function showLoggedOutShell() {
  currentUser = null;
  document.getElementById('app').classList.remove('visible');
  updateAdminHeader();
  const loginScreen = document.getElementById('login-screen');
  loginScreen.classList.remove('hidden');
  loginScreen.style.opacity = '1';
  document.getElementById('login-username').value = '';
  document.getElementById('login-password').value = '';
  resetPasswordField('login-password');
  clearLoginErrors();
  showPanel('login');
}

async function doLogin() {
  return withPendingAction('doLogin', async () => {
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;
    const remember = Boolean(document.getElementById('login-remember')?.checked);
    clearLoginErrors();
    let hasError = false;
    if(!username){
      setFieldError('login-username', 'Username is required');
      hasError = true;
    }
    if(!password){
      setFieldError('login-password', 'Password is required');
      hasError = true;
    }
    if(hasError) return;

    try {
      const data = await apiRequest(API_ENDPOINTS.auth, {action:'login', username, password, remember});
      currentUser = normalizeUser(data.user);
      await refreshState();
      showLoggedInShell();
      if(currentUser.role === 'admin') await checkOverdueBooks();
      navigateTo(defaultPageForCurrentUser());
      showToast(`Welcome back, ${currentUser.firstName || currentUser.username}!`,'success');
    } catch (error) {
      showLoginFormError(error.message);
    }
  });
}

async function doLogout() {
  try {
    await apiRequest(API_ENDPOINTS.auth, {action:'logout'});
  } catch (error) {
    // Local logout can continue even if the server session is already gone.
  }
  apiState = {books:[],users:[],transactions:[],fines:[],fineRules:[],reservations:[],requests:[],notifications:[],preferences:{},settings:{},categories:[],authors:[],publishers:[],reports:null};
  showLoggedOutShell();
}

async function doSignup() {
  return withPendingAction('doSignup', async () => {
    const firstName = document.getElementById('signup-firstname').value.trim();
    const lastName = document.getElementById('signup-lastname').value.trim();
    const email = document.getElementById('signup-email').value.trim();
    const username = document.getElementById('signup-username').value.trim();
    const password = document.getElementById('signup-password').value;
    const confirmPw = document.getElementById('signup-confirm-password').value;

    clearSignupErrors();
    let hasError = false;
    const firstNameError = signupNameError('First Name', firstName);
    const lastNameError = signupNameError('Last Name', lastName);
    const usernameError = signupUsernameError(username);
    const passwordError = signupPasswordError(password);
    if(firstNameError){ setFieldError('signup-firstname', firstNameError); hasError = true; }
    if(lastNameError){ setFieldError('signup-lastname', lastNameError); hasError = true; }
    if(!email){
      setFieldError('signup-email', 'Email is required');
      hasError = true;
    } else if(!isValidEmail(email)){
      setFieldError('signup-email', 'Enter a valid email address');
      hasError = true;
    }
    if(usernameError){ setFieldError('signup-username', usernameError); hasError = true; }
    if(passwordError){ setFieldError('signup-password', passwordError); hasError = true; }
    if(!confirmPw){
      setFieldError('signup-confirm-password', 'Confirm Password is required');
      hasError = true;
    } else if(password && confirmPw !== password){
      setFieldError('signup-confirm-password', 'Passwords do not match');
      hasError = true;
    }
    if(hasError) return;

    try {
      await apiRequest(API_ENDPOINTS.auth, {
        action:'signup',
        first_name:firstName,
        last_name:lastName,
        email,
        username,
        password,
        member_type:'student',
        avatar:`https://api.dicebear.com/7.x/personas/svg?seed=${encodeURIComponent(username)}`,
      });
      showToast(`Account created, ${firstName}. Please wait for admin approval before signing in.`, 'success');
      document.getElementById('login-username').value = username;
      document.getElementById('login-password').value = '';
      showPanel('login');
    } catch (error) {
      showSignupFormError(error.message);
    }
  });
}

const IMAGE_UPLOAD_DEFAULTS = {
  maxWidth: 900,
  maxHeight: 900,
  quality: 0.76,
  mimeType: 'image/jpeg',
};

function readImageAsDataUrl(file, options = {}) {
  const config = {...IMAGE_UPLOAD_DEFAULTS, ...options};
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = e => {
      const dataUrl = String(e.target.result || '');
      if (!file.type.startsWith('image/') || file.type === 'image/svg+xml') {
        resolve(dataUrl);
        return;
      }

      const image = new Image();
      image.onload = () => {
        const scale = Math.min(1, config.maxWidth / image.width, config.maxHeight / image.height);
        if (scale >= 1 && dataUrl.length <= 120000) {
          resolve(dataUrl);
          return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(image.width * scale));
        canvas.height = Math.max(1, Math.round(image.height * scale));
        const context = canvas.getContext('2d');
        if (!context) {
          resolve(dataUrl);
          return;
        }
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        resolve(canvas.toDataURL(config.mimeType, config.quality));
      };
      image.onerror = () => resolve(dataUrl);
      image.src = dataUrl;
    };
    reader.onerror = () => reject(new Error('Failed to read image file.'));
    reader.readAsDataURL(file);
  });
}

function bookPayloadFromForm(id, cover) {
  return {
    action: id ? 'update' : 'create',
    id,
    title: document.getElementById('book-title').value.trim(),
    author: document.getElementById('book-author').value.trim(),
    isbn: document.getElementById('book-isbn').value.trim(),
    subject: document.getElementById('book-subject').value.trim(),
    publisher: document.getElementById('book-publisher').value.trim(),
    year: parseInt(document.getElementById('book-year').value) || new Date().getFullYear(),
    copies: parseInt(document.getElementById('book-copies').value) || 1,
    lateFeePerDay: parseFloat(document.getElementById('book-late-fee').value) || 20,
    baseFine: parseFloat(document.getElementById('book-fine').value) || 500,
    rack: document.getElementById('book-rack').value.trim(),
    cover,
    desc: document.getElementById('book-desc').value.trim(),
  };
}

async function saveBook() {
  return withPendingAction('saveBook', async () => {
    const id = document.getElementById('edit-book-id').value;
    const title = document.getElementById('book-title').value.trim();
    const author = document.getElementById('book-author').value.trim();
    if(!title || !author){ showToast('Title and author are required','error'); return; }

    const existing = id ? getBooks().find(b=>b.id===id) : null;
    const fileInput = document.getElementById('book-cover-file');
    const urlInput = document.getElementById('book-cover-url');
    let cover = urlInput ? urlInput.value.trim() : '';

    try {
      if(fileInput?.files?.length) cover = await readImageAsDataUrl(fileInput.files[0], {maxWidth: 720, maxHeight: 960, quality: 0.72});
      if(!cover) cover = existing?.cover || DEFAULT_COVERS[Math.floor(Math.random()*DEFAULT_COVERS.length)];

      await apiRequest(API_ENDPOINTS.books, bookPayloadFromForm(id, cover));
      await refreshState();
      closeModal('modal-add-book');
      updateReferenceDropdowns();
      renderAdminBooks();
      buildSidebar();
      showToast(id ? 'Book updated successfully' : 'Book added to catalog', 'success');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

function bookMatchesSearchAndFilters(book, query, filter) {
  const q = (query || '').toLowerCase();
  const matchesText = !q ||
    book.title.toLowerCase().includes(q) ||
    book.author.toLowerCase().includes(q) ||
    (book.subject || '').toLowerCase().includes(q) ||
    (book.isbn || '').toLowerCase().includes(q);
  if(!matchesText) return false;

  const categoryId = document.getElementById('book-category-filter')?.value || document.getElementById('student-category-filter')?.value || '';
  const year = document.getElementById('book-year-filter')?.value || document.getElementById('student-year-filter')?.value || '';
  if(categoryId && normalizeId(book.categoryId) !== normalizeId(categoryId)) return false;
  if(year && Number(book.year) !== Number(year)) return false;

  if(filter === 'available') return getAvailableCopies(book.id) > 0;
  if(filter === 'borrowed') return getTransactions().some(t=>t.bookId===book.id&&!t.returned&&!t.lost);
  if(filter === 'lost') return Number(book.lostCopies || 0) > 0 || getTransactions().some(t=>t.bookId===book.id&&t.lost);
  return true;
}

const adminBookFilters = {
  searchQuery: '',
  selectedGenre: '',
  availabilityFilter: 'all',
  sortOption: 'newest',
};

function getAdminBookFilterValue(id, fallback) {
  return document.getElementById(id)?.value ?? fallback;
}

function syncAdminBookFiltersFromControls() {
  adminBookFilters.searchQuery = getAdminBookFilterValue('admin-books-search', adminBookFilters.searchQuery).trim();
  adminBookFilters.selectedGenre = getAdminBookFilterValue('admin-books-genre-filter', adminBookFilters.selectedGenre);
  adminBookFilters.availabilityFilter = getAdminBookFilterValue('admin-books-availability-filter', adminBookFilters.availabilityFilter);
  adminBookFilters.sortOption = getAdminBookFilterValue('admin-books-sort', adminBookFilters.sortOption);
}

function getFilteredBooks() {
  const query = adminBookFilters.searchQuery.toLowerCase();
  const genre = normalizeId(adminBookFilters.selectedGenre);
  const availability = adminBookFilters.availabilityFilter;
  const sorted = getBooks().filter(book => {
    const matchesSearch = !query ||
      normalizeId(book.id) === query ||
      (book.title || '').toLowerCase().includes(query) ||
      (book.author || '').toLowerCase().includes(query) ||
      (book.isbn || '').toLowerCase().includes(query);
    if(!matchesSearch) return false;
    if(genre && normalizeId(book.categoryId) !== genre) return false;
    const available = getAvailableCopies(book.id);
    if(availability === 'in-stock' && available <= 0) return false;
    if(availability === 'out-of-stock' && available > 0) return false;
    return true;
  });

  return sorted.sort((a, b) => {
    const aOrder = Number(a.id) || 0;
    const bOrder = Number(b.id) || 0;
    if(adminBookFilters.sortOption === 'oldest') return aOrder - bOrder;
    if(adminBookFilters.sortOption === 'alphabetical') return (a.title || '').localeCompare(b.title || '');
    return bOrder - aOrder;
  });
}

function clearAdminBookFilters() {
  adminBookFilters.searchQuery = '';
  adminBookFilters.selectedGenre = '';
  adminBookFilters.availabilityFilter = 'all';
  adminBookFilters.sortOption = 'newest';
  renderAdminBooks();
}

async function deleteBook(bookId) {
  if(!confirm('Delete this book from the catalog?')) return;
  try {
    await apiRequest(API_ENDPOINTS.books, {action:'delete', id:bookId});
    await refreshState();
    renderAdminBooks();
    buildSidebar();
    showToast('Book deleted successfully','success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function renderAdminBooks() {
  const activeControlId = document.activeElement?.id;
  const selectionStart = document.activeElement?.selectionStart;
  const selectionEnd = document.activeElement?.selectionEnd;
  syncAdminBookFiltersFromControls();
  const books = getFilteredBooks();
  resetPaginationOnFilterChange('adminBooks', JSON.stringify(adminBookFilters));
  const pagedBooks = paginateItems(books, paginationState.adminBooks || 1);
  paginationState.adminBooks = pagedBooks.currentPage;
  const grid = document.getElementById('admin-books-grid');
  if(!grid) return;
  const genreOptions = apiState.categories.map(category =>
    `<option value="${category.id}" ${normalizeId(adminBookFilters.selectedGenre)===normalizeId(category.id)?'selected':''}>${category.name}</option>`
  ).join('');
  const rows = pagedBooks.items.map(book => {
    const available = getAvailableCopies(book.id);
    const genre = book.subject || apiState.categories.find(category=>normalizeId(category.id)===normalizeId(book.categoryId))?.name || '-';
    const cover = book.cover
      ? `<img src="${book.cover}" alt="${book.title}" style="width:42px;height:58px;object-fit:cover;border-radius:var(--radius);background:var(--cream)" onerror="this.style.display='none'">`
      : `<div style="width:42px;height:58px;border-radius:var(--radius);background:var(--cream);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.7rem">No cover</div>`;
    return `<tr>
      <td>${cover}</td>
      <td><strong>${book.title || '-'}</strong></td>
      <td>${book.author || '-'}</td>
      <td class="mono text-muted" style="font-size:.78rem">${book.isbn || '-'}</td>
      <td><span class="tag tag-reserved">${genre}</span></td>
      <td>${book.copies}</td>
      <td class="${available>0?'text-sage':'text-rust bold'}">${available}</td>
      <td>${book.year || '-'}</td>
      <td class="td-actions">
        <button class="btn btn-ghost btn-sm" onclick="openEditBook('${book.id}')">Edit</button>
        <button class="btn btn-danger btn-sm" onclick="deleteBook('${book.id}')">Delete</button>
      </td>
    </tr>`;
  }).join('');

  grid.innerHTML = `
    <div class="card" style="grid-column:1/-1">
      <div class="card-body">
        <div class="filter-control-row">
          <input class="form-input" id="admin-books-search" value="${escapeAttr(adminBookFilters.searchQuery)}" placeholder="Search title, author, ISBN" oninput="renderAdminBooks()">
          <select class="form-select" id="admin-books-genre-filter" onchange="renderAdminBooks()">
            <option value="">All Genres</option>
            ${genreOptions}
          </select>
          <select class="form-select" id="admin-books-availability-filter" onchange="renderAdminBooks()">
            <option value="all" ${adminBookFilters.availabilityFilter==='all'?'selected':''}>All</option>
            <option value="in-stock" ${adminBookFilters.availabilityFilter==='in-stock'?'selected':''}>In Stock</option>
            <option value="out-of-stock" ${adminBookFilters.availabilityFilter==='out-of-stock'?'selected':''}>Out of Stock</option>
          </select>
          <select class="form-select" id="admin-books-sort" onchange="renderAdminBooks()">
            <option value="newest" ${adminBookFilters.sortOption==='newest'?'selected':''}>Newest First</option>
            <option value="oldest" ${adminBookFilters.sortOption==='oldest'?'selected':''}>Oldest First</option>
            <option value="alphabetical" ${adminBookFilters.sortOption==='alphabetical'?'selected':''}>Alphabetical</option>
          </select>
          <button class="btn btn-ghost btn-sm" onclick="clearAdminBookFilters()">${transactionActionIcon('loss')} Clear Filters</button>
        </div>
      </div>
    </div>
    <div class="card" style="grid-column:1/-1">
      <div class="card-body" style="padding:0">
        ${books.length ? `<div class="table-wrap"><table>
          <thead><tr><th>Cover</th><th>Title</th><th>Author</th><th>ISBN</th><th>Genre</th><th>Total Copies</th><th>Available</th><th>Year</th><th>Actions</th></tr></thead>
          <tbody>${rows}</tbody>
        </table></div>${renderPaginationControls({key:'adminBooks', totalItems:pagedBooks.totalItems, currentPage:pagedBooks.currentPage, onPageChange:'renderAdminBooks'})}` : '<div class="empty-state"><p>No results found</p></div>'}
      </div>
    </div>`;
  if(activeControlId?.startsWith('admin-books-')) {
    const activeControl = document.getElementById(activeControlId);
    activeControl?.focus();
    if(typeof selectionStart === 'number' && typeof selectionEnd === 'number' && activeControl?.setSelectionRange) {
      activeControl.setSelectionRange(selectionStart, selectionEnd);
    }
  }
}

function renderStudentCatalog() {
  const activeControlId = document.activeElement?.id;
  const selectionStart = document.activeElement?.selectionStart;
  const selectionEnd = document.activeElement?.selectionEnd;
  const q = document.getElementById('student-book-search')?.value || '';
  const genre = normalizeId(document.getElementById('student-category-filter')?.value || '');
  const availability = document.getElementById('student-availability-filter')?.value || window._studentFilter || 'all';
  const sort = document.getElementById('student-sort-filter')?.value || 'title';
  const currentUserId = normalizeId(currentUser?.id);
  const currentMemberId = normalizeId(currentUser?.memberId);
  const belongsToCurrentUser = item => item.userId === currentUserId || (currentMemberId && item.memberId === currentMemberId);
  const myReservations = getReservations().filter(belongsToCurrentUser).map(r=>r.bookId);
  const books = getBooks().filter(b=>{
    const query = q.toLowerCase();
    const matchesText = !query ||
      normalizeId(b.id) === query ||
      (b.title || '').toLowerCase().includes(query) ||
      (b.author || '').toLowerCase().includes(query) ||
      (b.isbn || '').toLowerCase().includes(query);
    if(!matchesText) return false;
    if(genre && normalizeId(b.categoryId) !== genre) return false;
    const avail = getAvailableCopies(b.id);
    if(availability === 'available') return avail > 0;
    if(availability === 'unavailable') return avail <= 0;
    if(availability === 'reserved') return myReservations.includes(b.id);
    return true;
  }).sort((a,b)=>{
    if(sort === 'author') return (a.author || '').localeCompare(b.author || '');
    if(sort === 'newest') return (Number(b.year) || 0) - (Number(a.year) || 0);
    if(sort === 'available') return getAvailableCopies(b.id) - getAvailableCopies(a.id);
    return (a.title || '').localeCompare(b.title || '');
  });
  const grid = document.getElementById('student-catalog-grid');
  if(!grid) return;
  if(!books.length){ grid.innerHTML='<div class="empty-state"><p>No results found</p></div>'; return; }

  const hasUnpaidFine = getFines().some(f=>belongsToCurrentUser(f)&&fineIsUnpaid(f));
  grid.innerHTML = books.map(b=>{
    const avail = getAvailableCopies(b.id);
    const borrowedByMe = currentUserHasActiveLoanForBook(b.id);
    const isReserved = myReservations.includes(b.id);
    const genreName = b.subject || apiState.categories.find(category=>normalizeId(category.id)===normalizeId(b.categoryId))?.name || 'Uncategorized';
    const coverImg = b.cover ? `<img class="book-cover" src="${escapeAttr(b.cover)}" alt="${escapeAttr(b.title)}" onerror="this.style.display='none'">` : `<div class="book-cover-placeholder">${escapeAttr(b.title || 'Book')}</div>`;
    const tag = borrowedByMe ? '<span class="tag tag-borrowed">Borrowed by you</span>' : avail>0 ? `<span class="tag tag-available">${avail} available</span>` : '<span class="tag tag-lost">Unavailable</span>';
    let actionBtn = '';
    if(borrowedByMe) actionBtn='<button class="btn btn-ghost btn-sm w-full" disabled>Already Borrowed</button>';
    else if(hasUnpaidFine) actionBtn='<button class="btn btn-danger btn-sm w-full" disabled title="Settle your fines first">Borrowing Suspended</button>';
    else if(avail>0) actionBtn=`<button class="btn btn-gold btn-sm w-full" onclick="openStudentBorrow('${b.id}')">Borrow</button>`;
    else if(isReserved) actionBtn=`<button class="btn btn-ghost btn-sm w-full" onclick="cancelReservationByBook('${b.id}')">Cancel Reserve</button>`;
    else actionBtn=`<button class="btn btn-ghost btn-sm w-full" onclick="reserveBook('${b.id}')">Reserve</button>`;
    return `<div class="book-card member-book-card">
      <div onclick="viewStudentBookDetail('${b.id}')" style="cursor:pointer">${coverImg}</div>
      <div class="book-info">
        <div class="book-title">${escapeAttr(b.title || 'Untitled')}</div>
        <div class="book-author">${escapeAttr(b.author || 'Unknown Author')}</div>
        <div class="book-meta">${escapeAttr(genreName)}${b.isbn ? ` · ISBN ${escapeAttr(b.isbn)}` : ''}</div>
        <div class="book-meta">${tag}</div>
      </div>
      <div class="book-actions">
        <button class="btn btn-ghost btn-sm w-full" onclick="viewStudentBookDetail('${b.id}')">View Book</button>
        ${actionBtn}
      </div>
    </div>`;
  }).join('');
  if(activeControlId?.startsWith('student-')) {
    const activeControl = document.getElementById(activeControlId);
    activeControl?.focus();
    if(typeof selectionStart === 'number' && typeof selectionEnd === 'number' && activeControl?.setSelectionRange) {
      activeControl.setSelectionRange(selectionStart, selectionEnd);
    }
  }
}

async function issueBook() {
  return withPendingAction('issueBook', async () => {
    const bookId = document.getElementById('issue-book-id').value;
    const memberId = document.getElementById('issue-member').value;
    try {
      await apiRequest(API_ENDPOINTS.loans, {action:'issue', book_id:bookId, member_id:memberId});
      await refreshState();
      closeModal('modal-issue-book');
      renderRelatedViews(renderAdminBooks, renderTransactions, renderMembers, renderAdminDashboard);
      showToast('Book issued successfully.','success');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function approveBorrowRequest(reqId) {
  return withPendingAction(`approveBorrowRequest:${reqId}`, async () => {
    try {
      await apiRequest(API_ENDPOINTS.loanRequests, {action:'approve', request_id:reqId});
      await refreshState();
      renderRelatedViews(renderBorrowRequests, renderTransactions, renderAdminBooks, renderMembers, renderAdminDashboard);
      showToast('Request approved. Book issued.','success');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function denyBorrowRequest(reqId) {
  return withPendingAction(`denyBorrowRequest:${reqId}`, async () => {
    try {
      await apiRequest(API_ENDPOINTS.loanRequests, {action:'reject', request_id:reqId});
      await refreshState();
      renderRelatedViews(renderBorrowRequests, renderAdminDashboard);
      showToast('Request rejected.','info');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function cancelBorrowRequest(reqId) {
  return withPendingAction(`cancelBorrowRequest:${reqId}`, async () => {
    try {
      await apiRequest(API_ENDPOINTS.loanRequests, {action:'cancel', request_id:reqId});
      await refreshState();
      renderRelatedViews(renderMyLoans, renderStudentDashboard, renderStudentCatalog);
      showToast('Borrow request cancelled.','info');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function confirmReturn(button = null) {
  const txId = normalizeId(document.getElementById('return-tx-id').value);
  if(!txId) { showToast('No valid transaction selected.', 'error'); return; }
  return withPendingAction(`confirmReturn:${txId}`, async () => {
    const originalText = button?.textContent || '';
    if(button) {
      button.disabled = true;
      button.textContent = 'Returning...';
    }
    try {
      const data = await apiRequest(API_ENDPOINTS.loans, {action:'return', transaction_id:txId});
      await refreshState();
      closeModal('modal-return');
      const amount = Number(data.fine_amount || 0);
      renderRelatedViews(renderTransactions, renderAdminBooks, renderFinesAdmin, renderMembers, renderAdminDashboard, renderMyLoans, renderStudentDashboard);
      navigateTo(currentUser.role==='admin'?'transactions':'my-loans');
      showToast(amount > 0 ? `Returned with ₱${amount} late fine applied.` : 'Book returned successfully!', amount > 0 ? 'info' : 'success');
    } catch (error) {
      showToast(error.message, 'error');
    } finally {
      if(button) {
        button.disabled = false;
        button.textContent = originalText || 'Confirm Return';
      }
    }
  });
}

async function markLost(button = null) {
  const txId = normalizeId(document.getElementById('lost-transaction').value);
  const fineAmt = parseFloat(document.getElementById('lost-fine-amount').value) || 500;
  const type = document.getElementById('lost-type')?.value === 'damaged' ? 'damaged' : 'lost';
  if(!txId){ showToast('No active loan selected.', 'error'); return; }
  return withPendingAction(`markLost:${txId}`, async () => {
    const originalText = button?.textContent || '';
    if(button) {
      button.disabled = true;
      button.textContent = type === 'damaged' ? 'Marking Damaged...' : 'Marking Lost...';
    }
    try {
      const activeTx = getTransactions().find(tx=>tx.id===txId && !tx.returned && !tx.lost);
      if(!activeTx) {
        await refreshState();
        const freshTx = getTransactions().find(tx=>tx.id===txId && !tx.returned && !tx.lost);
        if(!freshTx) {
          showToast('Active loan not found.', 'error');
          closeModal('modal-mark-lost');
          renderRelatedViews(renderAdminBooks, renderTransactions, renderMembers, renderAdminDashboard);
          return;
        }
      }
      await apiRequest(API_ENDPOINTS.loans, {action:'mark_lost', transaction_id:txId, amount:fineAmt, type});
      await refreshState();
      closeModal('modal-mark-lost');
      renderRelatedViews(renderAdminBooks, renderTransactions, renderFinesAdmin, renderMembers, renderAdminDashboard, renderMyLoans, renderStudentDashboard);
      showToast(`Book marked as ${type === 'damaged' ? 'damaged' : 'lost'}. ₱${fineAmt} replacement fee applied.`, 'error');
    } catch (error) {
      showToast(error.message, 'error');
    } finally {
      if(button) {
        button.disabled = false;
        button.textContent = originalText || 'Confirm';
      }
    }
  });
}

async function payFine(fineId) {
  const fineBeforePay = getFines().find(f=>f.id===normalizeId(fineId));
  try {
    await apiRequest(API_ENDPOINTS.fines, {action:'pay', fine_id:fineId});
    await refreshState();
    if(currentUser.role==='admin') {
      renderRelatedViews(renderFinesAdmin, renderAdminDashboard, renderMembers);
      if(fineBeforePay && !document.getElementById('modal-member-detail')?.classList.contains('hidden')) {
        viewMemberDetail(fineBeforePay.userId);
      }
    } else {
      renderMyFines();
      renderStudentDashboard();
      renderVisiblePage();
    }
    showToast('Fine marked as paid','success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function payFineOnline(fineId, button) {
  const normalizedFineId = normalizeId(fineId);
  if (pendingFinePaymentClicks.has(normalizedFineId)) return;
  pendingFinePaymentClicks.add(normalizedFineId);

  const originalText = button ? button.textContent : '';
  if (button) {
    button.disabled = true;
    button.textContent = 'Processing...';
  }

  try {
    sessionStorage.setItem(PAYMENT_RETURN_FINE_KEY, normalizedFineId);
    const data = await apiRequest(API_ENDPOINTS.payments, {fine_id:fineId});
    if (!data.checkout_url) {
      throw new Error('Payment checkout URL was not returned.');
    }
    window.location.href = data.checkout_url;
  } catch (error) {
    sessionStorage.removeItem(PAYMENT_RETURN_FINE_KEY);
    pendingFinePaymentClicks.delete(normalizedFineId);
    if (button) {
      button.disabled = false;
      button.textContent = originalText || 'Pay';
    }
    showToast(error.message, 'error');
  }
}

async function waiveFine(fineId) {
  const reason = prompt('Reason for waiving this fine:', 'Admin waiver');
  if (reason === null) return;
  try {
    await apiRequest(API_ENDPOINTS.fines, {action:'waive', fine_id:fineId, reason});
    await refreshState();
    renderRelatedViews(renderFinesAdmin, renderAdminDashboard, renderMembers);
    showToast('Fine waived.','success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function adjustFine(fineId) {
  const fine = getFines().find(f=>f.id===normalizeId(fineId));
  const amount = prompt('New fine amount:', fine ? String(fine.amount) : '');
  if (amount === null) return;
  const parsed = parseFloat(amount);
  if (!Number.isFinite(parsed) || parsed < 0) {
    showToast('Enter a valid fine amount.','error');
    return;
  }
  try {
    await apiRequest(API_ENDPOINTS.fines, {action:'adjust', fine_id:fineId, amount:parsed});
    await refreshState();
    renderRelatedViews(renderFinesAdmin, renderAdminDashboard, renderMembers);
    showToast('Fine amount adjusted.','success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function hasBorrowingBlock() {
  const active = getTransactions().filter(t=>t.userId===currentUser.id&&!t.returned&&!t.lost);
  const overdue = active.some(t=>new Date(t.dueDate) < Date.now());
  const unpaidFine = getFines().some(f=>f.userId===currentUser.id&&fineIsUnpaid(f));
  return active.length >= 5 || overdue || unpaidFine;
}

function openStudentBorrow(bookId) {
  if(currentUserHasActiveLoanForBook(bookId)) {
    showToast('You already have an active loan for this book.','error');
    return;
  }
  if(hasBorrowingBlock()) {
    showToast('Borrowing is blocked until active limits, overdue books, and unpaid fines are cleared.','error');
    return;
  }
  const book = getBooks().find(b=>b.id===bookId);
  const activeLoanCount = getTransactions().filter(t=>t.userId===currentUser.id&&!t.returned&&!t.lost).length;
  document.getElementById('student-borrow-book-id').value=bookId;
  document.getElementById('student-borrow-info').innerHTML=`
    <div class="card"><div class="card-body">
      <strong>${book?.title}</strong> by ${book?.author}<br>
      <span class="text-muted" style="font-size:.82rem">Late fee if overdue: <strong>₱${book?.lateFeePerDay||20}/day</strong> &nbsp;·&nbsp; Replacement value: <strong>₱${book?.baseFine||500}</strong></span><br>
      <span class="text-muted" style="font-size:.82rem">Active loans: ${activeLoanCount}/5</span>
    </div></div>`;
  const today = new Date();
  const minDate = new Date(today); minDate.setDate(today.getDate()+1);
  const maxDate = new Date(today); maxDate.setDate(today.getDate()+14);
  const toISO = d => d.toISOString().split('T')[0];
  const picker = document.getElementById('student-return-date');
  picker.min = toISO(minDate);
  picker.max = toISO(maxDate);
  picker.value = toISO(maxDate);
  updateBorrowDaysDisplay();
  picker.oninput = updateBorrowDaysDisplay;
  openModal('modal-student-borrow');
}

async function studentBorrow() {
  return withPendingAction('studentBorrow', async () => {
    const bookId = document.getElementById('student-borrow-book-id').value;
    const dueDate = document.getElementById('student-return-date').value;
    if(currentUserHasActiveLoanForBook(bookId)) {
      showToast('You already have an active loan for this book.','error');
      closeModal('modal-student-borrow');
      return;
    }
    if(!dueDate){ showToast('Please select a return date','error'); return; }
    try {
      await apiRequest(API_ENDPOINTS.loanRequests, {action:'create', book_id:bookId, due_date:dueDate});
      await refreshState();
      closeModal('modal-student-borrow');
      renderRelatedViews(renderStudentCatalog, renderStudentDashboard, renderMyLoans);
      showToast('Borrow request submitted! Awaiting approval.','success');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function reserveBook(bookId) {
  return withPendingAction(`reserveBook:${bookId}`, async () => {
    const book = getBooks().find(b=>b.id===normalizeId(bookId));
    if(!book || getAvailableCopies(book.id) > 0 || currentUserHasActiveLoanForBook(book.id)) {
      showToast('Reserve is only available when no copies are available and you do not already have this book.','error');
      return;
    }
    try {
      await apiRequest(API_ENDPOINTS.reservations, {action:'create', book_id:bookId});
      await refreshState();
      renderRelatedViews(renderStudentCatalog, renderStudentDashboard, renderMyLoans, renderMyReservations);
      showToast('Book reserved! You will be notified when available.','success');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function cancelReservation(resId) {
  return withPendingAction(`cancelReservation:${resId}`, async () => {
    try {
      await apiRequest(API_ENDPOINTS.reservations, {action:'cancel', reservation_id:resId});
      await refreshState();
      renderRelatedViews(renderStudentDashboard, renderStudentCatalog, renderMyLoans, renderMyReservations);
      showToast('Reservation cancelled','info');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function cancelReservationByBook(bookId) {
  return withPendingAction(`cancelReservationByBook:${bookId}`, async () => {
    try {
      await apiRequest(API_ENDPOINTS.reservations, {action:'cancel', book_id:bookId});
      await refreshState();
      renderRelatedViews(renderStudentCatalog, renderStudentDashboard, renderMyLoans, renderMyReservations);
      showToast('Reservation cancelled','info');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function renewLoan(txId) {
  return withPendingAction(`renewLoan:${txId}`, async () => {
    try {
      await apiRequest(API_ENDPOINTS.loans, {action:'renew', transaction_id:txId});
      await refreshState();
      renderRelatedViews(renderMyLoans, renderStudentDashboard);
      showToast('Loan renewed successfully!','success');
    } catch (error) {
      showToast(error.message, 'error');
    }
  });
}

async function checkOverdueBooks() {
  if(currentUser?.role !== 'admin') return;
  try {
    await apiRequest(API_ENDPOINTS.loans, {action:'check_overdues'});
    await refreshState();
  } catch (error) {
    // Non-blocking background check.
  }
}

function addNotification() {
  // Notifications are now created by backend services.
}

function shouldShowNotificationErrorToast(error) {
  const appVisible = document.getElementById('app')?.classList.contains('visible');
  const loginVisible = !document.getElementById('login-screen')?.classList.contains('hidden');
  return Boolean(currentUser && appVisible && !loginVisible && error?.message !== 'Authentication required.');
}

async function clearAllNotifications() {
  if(!currentUser) return;

  try {
    await apiRequest(API_ENDPOINTS.notifications, {action:'mark_all_read'});
    await refreshState();
    renderRelatedViews(currentUser.role==='admin' ? renderNotifications : renderStudentNotifications);
  } catch (error) {
    if(shouldShowNotificationErrorToast(error)) showToast(error.message, 'error');
  }
}

function canManageDirectNotification(note) {
  return !note.isShared && normalizeId(note.recipientId) === normalizeId(currentUser?.id);
}

async function clearUserNotifications() {
  if(!currentUser) return;

  try {
    await apiRequest(API_ENDPOINTS.notifications, {action:'clear_all'});
    await refreshState();
    renderVisiblePage();
    buildSidebar();
    showToast('Your direct notifications were cleared.', 'success');
  } catch (error) {
    if(shouldShowNotificationErrorToast(error)) showToast(error.message, 'error');
  }
}

async function deleteNotification(notificationId) {
  try {
    await apiRequest(API_ENDPOINTS.notifications, {action:'delete', id:notificationId});
    await refreshState();
    renderVisiblePage();
    buildSidebar();
    showToast('Notification deleted.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function renderNotifications() {
  const el = document.getElementById('notifications-list');
  if(!el) return;
  renderNotificationList(el, getMyNotifications(), false);
}

function renderStudentNotifications() {
  const el = document.getElementById('student-notifications-list');
  if(!el) return;
  renderNotificationList(el, getMyNotifications(), true);
}

function notificationControlId(compact, suffix) {
  return `${compact ? 'student-' : ''}notification-${suffix}`;
}

function notificationFilterControls(notes, compact, selected) {
  const types = [...new Set(notes.map(n=>String(n.type || 'info')).filter(Boolean))].sort((a,b)=>a.localeCompare(b));
  return `<div class="card mb-3 notification-filter-card"><div class="card-body">
    <div class="filter-control-row">
      <select class="form-select" id="${notificationControlId(compact, 'type-filter')}" onchange="${compact ? 'renderStudentNotifications' : 'renderNotifications'}()">
        <option value="all" ${selected.type==='all'?'selected':''}>All Types</option>
        ${types.map(type=>`<option value="${escapeAttr(type)}" ${selected.type===type?'selected':''}>${escapeAttr(type.charAt(0).toUpperCase() + type.slice(1))}</option>`).join('')}
      </select>
      <select class="form-select" id="${notificationControlId(compact, 'read-filter')}" onchange="${compact ? 'renderStudentNotifications' : 'renderNotifications'}()">
        <option value="all" ${selected.read==='all'?'selected':''}>All Statuses</option>
        <option value="unread" ${selected.read==='unread'?'selected':''}>Unread</option>
        <option value="read" ${selected.read==='read'?'selected':''}>Read</option>
      </select>
      <select class="form-select" id="${notificationControlId(compact, 'sort')}" onchange="${compact ? 'renderStudentNotifications' : 'renderNotifications'}()">
        <option value="newest" ${selected.sort==='newest'?'selected':''}>Newest First</option>
        <option value="oldest" ${selected.sort==='oldest'?'selected':''}>Oldest First</option>
      </select>
      <button class="btn btn-ghost btn-sm" type="button" onclick="clearUserNotifications()" title="Clears your private notifications only. Shared role notices are retained.">Clear</button>
    </div>
  </div></div>`;
}

function renderNotificationList(el, notes, compact) {
  const q = searchValue(compact ? 'student-notification-search' : 'notification-search');
  const selected = {
    type: document.getElementById(notificationControlId(compact, 'type-filter'))?.value || 'all',
    read: document.getElementById(notificationControlId(compact, 'read-filter'))?.value || 'all',
    sort: document.getElementById(notificationControlId(compact, 'sort'))?.value || 'newest',
  };
  const controlsHtml = notificationFilterControls(notes, compact, selected);
  const pageKey = compact ? 'studentNotifications' : 'notifications';
  resetPaginationOnFilterChange(pageKey, JSON.stringify({q, ...selected}));
  notes = notes.filter(n => matchesSearch(q, [
    n.title,
    n.message,
    n.type,
    dateStr(n.date),
  ]));
  notes = notes.filter(n => {
    if(selected.type !== 'all' && String(n.type || 'info') !== selected.type) return false;
    if(selected.read === 'read' && !n.read) return false;
    if(selected.read === 'unread' && n.read) return false;
    return true;
  }).sort((a,b) => selected.sort === 'oldest' ? (a.date || 0) - (b.date || 0) : (b.date || 0) - (a.date || 0));
  const pagedNotes = paginateItems(notes, paginationState[pageKey] || 1);
  paginationState[pageKey] = pagedNotes.currentPage;
  if(!notes.length){
    el.innerHTML = controlsHtml + (compact
      ? '<div class="empty-state"><p>No notifications found.</p></div>'
      : '<div class="empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg><p>No notifications found.</p></div>');
    return;
  }

  el.innerHTML = controlsHtml + pagedNotes.items.map(function(n){
    const border = n.type === 'overdue' ? 'var(--rust)' : compact ? 'var(--gold)' : (n.type === 'info' ? 'var(--gold)' : 'var(--sage)');
    const unread = !n.read ? '<span class="notif-dot"></span>' : '';
    const direct = canManageDirectNotification(n);
    const markReadButton = !n.read && direct ? `<button class="btn btn-ghost btn-sm" type="button" onclick="event.stopPropagation();markNotificationRead('${n.id}')">Mark as Read</button>` : '';
    const markUnreadButton = n.read && direct ? `<button class="btn btn-ghost btn-sm" type="button" onclick="event.stopPropagation();markNotificationUnread('${n.id}')">Mark Unread</button>` : '';
    const deleteButton = direct
      ? `<button class="btn btn-danger btn-sm" type="button" onclick="event.stopPropagation();deleteNotification('${n.id}')">Delete</button>`
      : `<button class="btn btn-ghost btn-sm" type="button" disabled title="Shared role notices cannot be deleted without per-user receipts.">Shared</button>`;
    return `<div class="card mb-2 notification-inbox-item" onclick="openNotificationDetail('${n.id}')" style="border-left:4px solid ${border};cursor:pointer">
      <div class="card-body" style="padding:14px 16px">
        <div class="notification-inbox-row">
          <div class="notification-inbox-main">
            <div class="notification-inbox-title">${unread}${n.title ? escapeAttr(n.title) : 'Notification'} ${notificationTypeTag(n)}</div>
            <p class="notification-inbox-message">${escapeAttr(n.message || '')}</p>
            <p class="text-muted" style="font-size:.75rem;margin-top:4px">${dateStr(n.date)}</p>
          </div>
          <div class="notification-inbox-actions">
            <button class="btn btn-primary btn-sm" type="button" onclick="event.stopPropagation();openNotificationRelated('${n.id}')">${n.read ? 'View' : 'View / Read'}</button>
            ${markReadButton}
            ${markUnreadButton}
            ${deleteButton}
          </div>
        </div>
      </div>
    </div>`;
  }).join('') + renderPaginationControls({
    key: pageKey,
    totalItems: pagedNotes.totalItems,
    currentPage: pagedNotes.currentPage,
    onPageChange: compact ? 'renderStudentNotifications' : 'renderNotifications',
  });
}

async function openNotificationRelated(notificationId) {
  let note = getNotifications().find(n=>n.id===normalizeId(notificationId));
  if(!note) return;

  if(!note.read) {
    await markNotificationRead(notificationId, false);
    note = getNotifications().find(n=>n.id===normalizeId(notificationId)) || note;
  }

  const target = notificationRelatedTarget(note);
  if(!target.page) {
    openNotificationDetail(notificationId);
    return;
  }
  if(!target.exists) {
    showToast('The related record is no longer available.', 'warning');
    renderVisiblePage();
    return;
  }

  closeModal('modal-notification-detail');
  prepareRelatedRecordFocus(target);
  navigateTo(target.page);
  openRelatedRecord(target);
}

async function openNotificationDetail(notificationId) {
  let note = getNotifications().find(n=>n.id===normalizeId(notificationId));
  if(!note) return;

  if(!note.read) {
    await markNotificationRead(notificationId, false);
    note = getNotifications().find(n=>n.id===normalizeId(notificationId)) || note;
  }

  const detail = notificationDetailContext(note);
  const quickActions = notificationQuickActions(note);
  const el = document.getElementById('notification-detail-content');
  if(!el) return;
  el.innerHTML = `
    <div class="modal-title">${note.title || 'Notification'}</div>
    <div class="mb-2">${notificationTypeTag(note)}</div>
    <p style="font-size:.9rem;line-height:1.6">${note.message}</p>
    <div class="card mt-3"><div class="card-body" style="padding:14px 16px">
      <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.08em">Related</div>
      <div style="font-weight:700;margin-top:4px">${detail.related}</div>
      <div class="text-muted" style="font-size:.8rem;margin-top:6px">Date: ${dateTimeStr(note.date)}</div>
      <div class="text-muted" style="font-size:.8rem;margin-top:4px">Status: ${detail.status}</div>
    </div></div>
    ${quickActions}
    ${detail.page ? `<button class="btn btn-primary mt-3" onclick="openNotificationRelated('${note.id}')">Open Related Section</button>` : ''}
  `;
  openModal('modal-notification-detail');
  renderVisiblePage();
}

function notificationTypeTag(note) {
  const label = note.type ? note.type.charAt(0).toUpperCase() + note.type.slice(1) : 'Info';
  const cls = note.type === 'overdue' ? 'tag-overdue' : note.type === 'success' ? 'tag-available' : 'tag-reserved';
  return `<span class="tag ${cls}">${label}</span>${note.read ? ' <span class="tag tag-available">Read</span>' : ' <span class="tag tag-lost">Unread</span>'}`;
}

function notificationDetailContext(note) {
  const target = notificationRelatedTarget(note);
  const type = target.type;
  const relatedId = normalizeId(target.id);
  const tx = type === 'loan' ? getTransactions().find(t=>t.id===relatedId) : null;
  const reservation = type === 'reservation' ? getReservations().find(r=>r.id===relatedId) : null;
  const request = type === 'borrow_request' ? getRequests().find(r=>r.id===relatedId) : null;
  const relatedBook = type === 'book' ? getBooks().find(b=>b.id===relatedId) : null;
  const fineById = type === 'fine' ? getFines().find(f=>f.id===relatedId) : null;
  const book = tx ? getBooks().find(b=>b.id===tx.bookId) : null;
  const fine = fineById || (tx ? getFines().find(f=>f.txId===tx.id) : null);
  const lower = `${note.title} ${note.message} ${note.type}`.toLowerCase();

  if(type === 'fine' || fine || (!target.hasMetadata && lower.includes('fine'))) {
    return {
      related: fine ? `${book?.title || 'Book'} - ₱${fine.amount}` : 'Fine record',
      status: fine ? (fineStatusLabel(fine)) : 'Fine notice',
      page: target.page || (currentUser.role === 'admin' ? 'fines' : 'my-fines'),
    };
  }
  if(type === 'borrow_request' || (!target.hasMetadata && lower.includes('request'))) {
    const requestBook = request ? getBooks().find(b=>b.id===request.bookId) : null;
    return {
      related: requestBook?.title || book?.title || 'Borrow request',
      status: request ? request.status : lower.includes('approved') ? 'Approved' : lower.includes('rejected') ? 'Rejected' : 'Pending review',
      page: target.page,
    };
  }
  if(type === 'reservation' || (!target.hasMetadata && (lower.includes('reservation') || lower.includes('reserved')))) {
    const reservationBook = reservation ? getBooks().find(b=>b.id===reservation.bookId) : null;
    return {
      related: reservationBook?.title || book?.title || 'Reservation',
      status: reservation ? reservation.status : lower.includes('available') || lower.includes('ready') ? 'Ready' : 'Active',
      page: target.page || (currentUser.role === 'admin' ? 'reservations' : 'my-reservations'),
    };
  }
  if(type === 'book') {
    return {
      related: relatedBook?.title || 'Book record',
      status: relatedBook ? `${getAvailableCopies(relatedBook.id)} available` : 'Book notice',
      page: target.page,
    };
  }
  if(tx) {
    return {
      related: book?.title || 'Loan transaction',
      status: tx.lost ? 'Lost/Damaged' : tx.returned ? 'Returned' : new Date(tx.dueDate) < Date.now() ? 'Overdue' : 'Borrowed',
      page: currentUser.role === 'admin' ? 'transactions' : 'my-loans',
    };
  }
  return {
    related: 'General notice',
    status: note.read ? 'Read' : 'Unread',
    page: '',
  };
}

function notificationQuickActions(note) {
  const tx = renewableNotificationLoan(note);
  if(!tx) return '';

  return `<button class="btn btn-sage mt-3" onclick="renewLoanFromNotification('${note.id}', '${tx.id}')">Renew Loan</button>`;
}

function renewableNotificationLoan(note) {
  const target = notificationRelatedTarget(note);
  if(target.type !== 'loan' || !target.id || !target.exists) return null;

  const tx = getTransactions().find(t=>t.id===target.id);
  if(!tx) return null;
  if(tx.returned || tx.lost || String(tx.status || 'borrowed').toLowerCase() !== 'borrowed') return null;
  if(Number(tx.renewed || 0) >= 1) return null;
  if(tx.dueDate && new Date(tx.dueDate) < new Date().setHours(0, 0, 0, 0)) return null;

  const currentUserId = normalizeId(currentUser?.id);
  const currentMemberId = normalizeId(currentUser?.memberId);
  const ownLoan = tx.userId === currentUserId || (currentMemberId && tx.memberId === currentMemberId);
  if(currentUser?.role !== 'admin' && !ownLoan) return null;

  const blockedByReservation = getReservations().some(r =>
    r.bookId === tx.bookId &&
    r.memberId !== tx.memberId &&
    String(r.status || 'active').toLowerCase() === 'active'
  );
  if(blockedByReservation) return null;

  return tx;
}

async function renewLoanFromNotification(notificationId, txId) {
  try {
    const data = await apiRequest(API_ENDPOINTS.loans, {action:'renew', transaction_id:txId});
    await refreshState();
    buildSidebar();
    renderRelatedViews(renderNotifications, renderStudentNotifications, renderMyLoans, renderTransactions, renderStudentDashboard, renderAdminDashboard);
    const renewed = normalizeTransaction(data.loan || {});
    const dueDate = renewed?.dueDate ? ` New due date: ${dateStr(renewed.dueDate)}.` : '';
    closeModal('modal-notification-detail');
    showToast(`Loan renewed.${dueDate}`, 'success');
  } catch (error) {
    showToast(error.message, 'error');
    await openNotificationDetail(notificationId);
  }
}

function notificationRelatedTarget(note) {
  const type = String(note.relatedEntityType || note.referenceType || '').toLowerCase();
  const id = normalizeId(note.relatedEntityId || note.referenceId || note.txId);
  const hasMetadata = Boolean(type && id);
  const role = currentUser?.role === 'admin' ? 'admin' : 'member';
  const target = {type, id, page: '', exists: false, hasMetadata};

  if(!hasMetadata) return target;

  if(type === 'loan') {
    target.page = role === 'admin' ? 'transactions' : 'my-loans';
    target.exists = getTransactions().some(t=>t.id===id);
  } else if(type === 'fine') {
    target.page = role === 'admin' ? 'fines' : 'my-fines';
    target.exists = getFines().some(f=>f.id===id);
  } else if(type === 'reservation') {
    target.page = role === 'admin' ? 'reservations' : 'my-reservations';
    target.exists = getReservations().some(r=>r.id===id);
  } else if(type === 'borrow_request') {
    target.page = role === 'admin' ? 'borrow-requests' : 'student-dashboard';
    target.exists = getRequests().some(r=>r.id===id);
  } else if(type === 'book') {
    target.page = role === 'admin' ? 'books' : 'catalog';
    target.exists = getBooks().some(b=>b.id===id);
  }

  return target;
}

function prepareRelatedRecordFocus(target) {
  if(!target?.id) return;
  if(target.page === 'transactions') {
    const search = document.getElementById('transaction-search');
    if(search) search.value = target.id;
    paginationState.transactions = 1;
  } else if(target.page === 'my-loans') {
    const search = document.getElementById('my-loans-search');
    if(search) search.value = target.id;
    window._myLoansTab = 'all';
    paginationState.myLoans = 1;
  } else if(target.page === 'borrow-requests') {
    borrowRequestFilters.tab = 'history';
    borrowRequestFilters.status = 'all';
    borrowRequestFilters.userQuery = '';
    borrowRequestFilters.bookQuery = '';
    paginationState.borrowRequests = 1;
  } else if(target.page === 'student-dashboard' && target.type === 'borrow_request') {
    window._studentDashboardTab = 'history';
    paginationState['studentDashboard-history'] = 1;
  } else if(target.page === 'reservations') {
    adminReservationFilters.status = 'all';
    adminReservationFilters.activeOnly = false;
    adminReservationFilters.userId = '';
    adminReservationFilters.bookId = '';
    paginationState.adminReservations = 1;
  } else if(target.page === 'my-reservations') {
    window._myReservationsTab = 'all';
    paginationState.myReservations = 1;
  } else if(target.page === 'fines') {
    clearFineAdminFiltersStateOnly();
    const sorted = [...getFines()].sort((a,b)=>b.date-a.date);
    paginationState.adminFines = Math.max(1, Math.ceil((sorted.findIndex(f=>f.id===target.id) + 1) / DEFAULT_PAGE_SIZE));
  } else if(target.page === 'my-fines') {
    const search = document.getElementById('my-fines-search');
    if(search) search.value = target.id;
    paginationState.myFines = 1;
  } else if(target.page === 'books') {
    const search = document.getElementById('admin-books-search');
    if(search) search.value = target.id;
    adminBookFilters.searchQuery = target.id;
    adminBookFilters.selectedGenre = '';
    adminBookFilters.availabilityFilter = 'all';
    paginationState.adminBooks = 1;
  } else if(target.page === 'catalog') {
    const search = document.getElementById('student-book-search');
    if(search) search.value = target.id;
    paginationState.studentCatalog = 1;
  }
}

function openRelatedRecord(target) {
  if(target.type !== 'book') return;
  if(currentUser?.role === 'admin' && typeof viewBookDetail === 'function') {
    viewBookDetail(target.id);
  } else if(currentUser?.role !== 'admin' && typeof viewStudentBookDetail === 'function') {
    viewStudentBookDetail(target.id);
  }
}

function fineStatusLabel(fine) {
  if(fine.waived || fine.status === 'waived') return 'Waived';
  return fineIsUnpaid(fine) ? 'Unpaid' : 'Paid';
}

function dateTimeStr(d) {
  return new Date(d).toLocaleString('en-PH',{year:'numeric',month:'short',day:'numeric',hour:'numeric',minute:'2-digit'});
}

async function markNotificationRead(notificationId, rerender = true) {
  if(!currentUser) return;

  try {
    await apiRequest(API_ENDPOINTS.notifications, {action:'mark_single_read', id:notificationId});
    await refreshState();
    buildSidebar();
    if(rerender) renderVisiblePage();
  } catch (error) {
    if(shouldShowNotificationErrorToast(error)) showToast(error.message, 'error');
  }
}

async function markNotificationUnread(notificationId) {
  try {
    await apiRequest(API_ENDPOINTS.notifications, {action:'mark_single_unread', id:notificationId});
    await refreshState();
    buildSidebar();
    renderVisiblePage();
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function handleAvatarUpload(event) {
  const file = event.target.files[0];
  if(!file) return;
  if(file.size > 2*1024*1024){ showToast('File too large — max 2MB','error'); return; }
  const statusEl = document.getElementById('avatar-upload-status');
  if(statusEl) statusEl.textContent = `Loading "${file.name}"...`;
  try {
    const avatarUrl = await readImageAsDataUrl(file, {maxWidth: 256, maxHeight: 256, quality: 0.74});
    const data = await apiRequest(API_ENDPOINTS.auth, {action:'update_profile', avatar_url:avatarUrl});
    currentUser = normalizeUser(data.user);
    await refreshState();
    const preview = document.getElementById('settings-avatar-preview');
    if(preview) preview.src = avatarUrl;
    if(statusEl) statusEl.textContent = `"${file.name}" uploaded successfully`;
    updateSidebarUser();
    showToast('Profile picture updated!','success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function saveProfileInfo() {
  const firstName = document.getElementById('settings-firstname').value.trim();
  const lastName = document.getElementById('settings-lastname').value.trim();
  const email = document.getElementById('settings-email').value.trim();
  const phoneInput = document.getElementById('settings-phone');
  const dobInput = document.getElementById('settings-dob');
  const phone = phoneInput ? phoneInput.value.trim() : undefined;
  const dateOfBirth = dobInput ? dobInput.value : undefined;
  if(!firstName||!lastName){ showToast('First name and last name cannot be empty','error'); return; }
  try {
    const payload = {action:'update_profile', first_name:firstName, last_name:lastName, email};
    if(phone !== undefined) payload.phone = phone;
    if(dateOfBirth !== undefined) payload.date_of_birth = dateOfBirth;
    const data = await apiRequest(API_ENDPOINTS.auth, payload);
    currentUser = normalizeUser(data.user);
    await refreshState();
    updateSidebarUser();
    if(currentUser.role==='admin') renderSettings();
    else renderStudentSettings();
    showToast('Profile updated!','success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function saveAccountSecurity() {
  const username = document.getElementById('settings-username').value.trim();
  const password = document.getElementById('settings-password').value;
  const confirm = document.getElementById('settings-confirm').value;
  ['settings-username', 'settings-password', 'settings-confirm'].forEach(clearFieldError);
  if(!username){
    setFieldError('settings-username', 'Username cannot be empty');
    showToast('Username cannot be empty','error');
    return;
  }
  if(password && password !== confirm){
    setFieldError('settings-confirm', 'Passwords do not match');
    showToast('Passwords do not match','error');
    return;
  }
  try {
    const data = await apiRequest(API_ENDPOINTS.auth, {action:'update_security', username, password});
    currentUser = normalizeUser(data.user);
    await refreshState();
    updateSidebarUser();
    document.getElementById('settings-password').value = '';
    document.getElementById('settings-confirm').value = '';
    showToast('Account updated!','success');
  } catch (error) {
    if(error.errors?.username) setFieldError('settings-username', error.errors.username);
    if(error.errors?.password) setFieldError('settings-password', error.errors.password);
    showToast(error.message, 'error');
  }
}

function updateCurrentUser(updates) {
  currentUser = {...currentUser, ...updates};
  const idx = apiState.users.findIndex(u=>u.id===currentUser.id);
  if(idx >= 0) apiState.users[idx] = {...apiState.users[idx], ...updates};
}

function setMyLoansTab(tab) {
  window._myLoansTab = tab;
  renderMyLoans();
}

function renderMyLoans() {
  const q = searchValue('my-loans-search');
  const currentUserId = normalizeId(currentUser?.id);
  const currentMemberId = normalizeId(currentUser?.memberId);
  const belongsToCurrentUser = item => item.userId === currentUserId || (currentMemberId && item.memberId === currentMemberId);
  const activeTab = ['all','active','overdue','returned','lost','damaged'].includes(window._myLoansTab) ? window._myLoansTab : 'all';
  document.querySelectorAll('[data-loan-tab]').forEach(btn=>btn.classList.toggle('active', btn.dataset.loanTab === activeTab));
  const txs = getTransactions().filter(belongsToCurrentUser);
  const books = getBooks();
  const grid = document.getElementById('my-loans-content');
  if(!grid) return;

  const loanStatus = tx => {
    const status = String(tx.status || '').toLowerCase();
    if(status === 'damaged') return 'damaged';
    if(status === 'lost' || tx.lost) return 'lost';
    if(status === 'returned' || tx.returned) return 'returned';
    if(tx.dueDate && new Date(tx.dueDate) < Date.now()) return 'overdue';
    return 'active';
  };
  const displayDate = value => value ? dateStr(value) : 'No date';
  const txMatches = tx => {
    const book = books.find(b=>b.id===tx.bookId);
    const status = loanStatus(tx);
    return matchesSearch(q, [tx.id, book?.title, book?.author, book?.isbn, status, tx.status, displayDate(tx.created), displayDate(tx.dueDate)]);
  };

  const loans = txs.filter(tx=>{
    const status = loanStatus(tx);
    if(activeTab !== 'all' && status !== activeTab) return false;
    return txMatches(tx);
  }).sort((a,b)=>{
    const aStatus = loanStatus(a);
    const bStatus = loanStatus(b);
    if(aStatus === 'active' || aStatus === 'overdue') return new Date(a.dueDate) - new Date(b.dueDate);
    return (b.returnedDate || b.created || 0) - (a.returnedDate || a.created || 0);
  });
  resetPaginationOnFilterChange('myLoans', JSON.stringify({activeTab, q}));
  const pagedLoans = paginateItems(loans, paginationState.myLoans || 1);
  paginationState.myLoans = pagedLoans.currentPage;

  if(!loans.length) {
    grid.innerHTML = '<div class="empty-state"><p>No loans found</p></div>';
    return;
  }

  grid.innerHTML = pagedLoans.items.map(tx=>{
    const book = books.find(b=>b.id===tx.bookId);
    const status = loanStatus(tx);
    const dl = tx.dueDate ? daysLeft(tx.dueDate) : 0;
    const statusTag = status === 'overdue'
      ? `<span class="tag tag-overdue">${Math.abs(dl)}d overdue</span>`
      : status === 'active'
        ? `<span class="tag tag-borrowed">${dl}d left</span>`
        : status === 'returned'
          ? '<span class="tag tag-available">Returned</span>'
          : status === 'damaged'
            ? '<span class="tag tag-lost">Damaged</span>'
            : '<span class="tag tag-lost">Lost</span>';
    const cover = book?.cover
      ? `<img class="member-loan-cover" src="${escapeAttr(book.cover)}" alt="${escapeAttr(book.title || 'Book cover')}" onerror="this.style.display='none'">`
      : `<div class="member-loan-cover-placeholder">${escapeAttr(book?.title || 'Book')}</div>`;
    const hasReservation = getReservations().some(r=>r.bookId===tx.bookId&&r.userId!==currentUserId);
    const canReturn = status === 'active' || status === 'overdue';
    const canRenew = status === 'active' && Number(tx.renewed || 0) < 1 && !hasReservation;
    const returnButton = canReturn ? `<button class="btn btn-sage btn-sm" onclick="openReturnModal('${tx.id}')">Return Book</button>` : '';
    const renewButton = canRenew ? `<button class="btn btn-ghost btn-sm" onclick="renewLoan('${tx.id}')">Renew</button>` : '';
    return `<div class="card member-loan-card">
      <div class="member-loan-media">${cover}</div>
      <div class="member-loan-body">
        <div class="member-loan-title-row">
          <div>
            <div class="member-loan-title">${escapeAttr(book?.title || 'Unknown title')}</div>
            <div class="member-loan-author">${escapeAttr(book?.author || 'Unknown Author')}</div>
          </div>
          ${statusTag}
        </div>
        <div class="member-loan-meta">
          <div><span>ISBN</span><strong>${escapeAttr(book?.isbn || '-')}</strong></div>
          <div><span>Checkout date</span><strong>${displayDate(tx.created)}</strong></div>
          <div><span>Due date</span><strong>${displayDate(tx.dueDate)}</strong></div>
          <div><span>Renewals</span><strong>${Number(tx.renewed || 0)}/1</strong></div>
        </div>
        <div class="member-loan-actions">
          <button class="btn btn-ghost btn-sm" onclick="viewStudentBookDetail('${tx.bookId}')">View Book Details</button>
          ${returnButton}
          ${renewButton}
        </div>
      </div>
    </div>`;
  }).join('') + renderPaginationControls({
    key:'myLoans',
    totalItems:pagedLoans.totalItems,
    currentPage:pagedLoans.currentPage,
    onPageChange:'renderMyLoans',
  });
}

function setMyReservationsTab(tab) {
  window._myReservationsTab = tab;
  renderMyReservations();
}

function renderMyReservations() {
  const currentUserId = normalizeId(currentUser?.id);
  const currentMemberId = normalizeId(currentUser?.memberId);
  const belongsToCurrentUser = item => item.userId === currentUserId || (currentMemberId && item.memberId === currentMemberId);
  const activeTab = ['all','active','completed'].includes(window._myReservationsTab) ? window._myReservationsTab : 'all';
  const statsEl = document.getElementById('member-reservation-stats');
  const content = document.getElementById('member-reservations-content');
  if(!statsEl || !content) return;

  document.querySelectorAll('[data-reservation-tab]').forEach(btn=>btn.classList.toggle('active', btn.dataset.reservationTab === activeTab));

  const books = getBooks();
  const reservations = apiState.reservations
    .filter(belongsToCurrentUser)
    .sort((a,b)=>(b.date || 0) - (a.date || 0));
  const isActiveStatus = reservationIsOpen;
  const isCompletedStatus = reservation => ['completed','fulfilled','approved'].includes(String(reservation.status || '').toLowerCase());
  const activeReservations = reservations.filter(isActiveStatus);
  const readyReservations = reservations.filter(r=>String(r.status || '').toLowerCase() === 'ready_for_pickup');

  statsEl.innerHTML = `
    <div class="stat-card"><div class="stat-label">Total Reservations</div><div class="stat-value">${reservations.length}</div><div class="stat-sub">All reservation records</div></div>
    <div class="stat-card sage"><div class="stat-label">Active</div><div class="stat-value">${activeReservations.length}</div><div class="stat-sub">Active or pending status</div></div>
    <div class="stat-card slate"><div class="stat-label">Ready to Pick Up</div><div class="stat-value">${readyReservations.length}</div><div class="stat-sub">Active reservations with available copies</div></div>`;

  const filtered = reservations.filter(reservation=>{
    if(activeTab === 'active') return isActiveStatus(reservation);
    if(activeTab === 'completed') return isCompletedStatus(reservation);
    return true;
  });
  resetPaginationOnFilterChange('myReservations', activeTab);
  const pagedReservations = paginateItems(filtered, paginationState.myReservations || 1);
  paginationState.myReservations = pagedReservations.currentPage;

  if(!filtered.length) {
    content.innerHTML = '<div class="empty-state"><p>No reservations</p></div>';
    return;
  }

  const statusTag = status => {
    const raw = String(status || 'active');
    const normalized = raw.toLowerCase();
    const cls = normalized === 'cancelled' || normalized === 'expired'
      ? 'tag-lost'
      : normalized === 'ready_for_pickup' || normalized === 'completed' || normalized === 'fulfilled' || normalized === 'approved'
        ? 'tag-available'
        : 'tag-borrowed';
    const label = normalized === 'ready_for_pickup' ? 'Ready for Pickup'
      : normalized === 'completed' || normalized === 'fulfilled' || normalized === 'approved' ? 'Completed'
        : raw.charAt(0).toUpperCase() + raw.slice(1);
    return `<span class="tag ${cls}">${escapeAttr(label)}</span>`;
  };

  content.innerHTML = pagedReservations.items.map(reservation=>{
    const book = books.find(b=>b.id===reservation.bookId);
    const canCancel = isActiveStatus(reservation);
    const readyLine = reservation.status === 'ready_for_pickup'
      ? `<div class="member-reservation-date">Ready until ${reservation.expiresAt ? dateStr(reservation.expiresAt) : 'pickup deadline not set'}</div>`
      : '';
    const cover = book?.cover
      ? `<img class="member-reservation-cover" src="${escapeAttr(book.cover)}" alt="${escapeAttr(book.title || 'Book cover')}" onerror="this.style.display='none'">`
      : `<div class="member-reservation-cover-placeholder">${escapeAttr(book?.title || 'Book')}</div>`;
    return `<div class="card member-reservation-card">
      <div class="member-reservation-media">${cover}</div>
      <div class="member-reservation-body">
        <div class="member-reservation-title-row">
          <div>
            <div class="member-reservation-book-id">Book ID: ${escapeAttr(reservation.bookId || '-')}</div>
            <div class="member-reservation-title">${escapeAttr(book?.title || 'Unknown title')}</div>
            <div class="member-reservation-date">Reserved ${reservation.date ? dateStr(reservation.date) : 'No date'}</div>
            ${readyLine}
          </div>
          ${statusTag(reservation.status)}
        </div>
        <div class="member-reservation-actions">
          <button class="btn btn-ghost btn-sm" onclick="viewStudentBookDetail('${reservation.bookId}')">View Book</button>
          ${canCancel ? `<button class="btn btn-danger btn-sm" onclick="cancelReservation('${reservation.id}')">Cancel</button>` : ''}
        </div>
      </div>
    </div>`;
  }).join('') + renderPaginationControls({
    key:'myReservations',
    totalItems:pagedReservations.totalItems,
    currentPage:pagedReservations.currentPage,
    onPageChange:'renderMyReservations',
  });
}

async function loadReports(params = {}) {
  try {
    const reports = await apiRequest(API_ENDPOINTS.reports, {action:'all', ...params});
    apiState.reports = reports;
    return reports;
  } catch (error) {
    showToast(error.message, 'error');
    return null;
  }
}

function reportColumns(type) {
  if(type === 'fines') {
    return [
      ['id', 'Fine ID'],
      ['member', 'Member'],
      ['book', 'Book'],
      ['fine_type', 'Type'],
      ['amount', 'Amount'],
      ['status', 'Status'],
      ['assessed_at', 'Assessed'],
      ['paid_at', 'Paid'],
    ];
  }

  return [
    ['id', 'Loan ID'],
    ['member', 'Member'],
    ['title', 'Book'],
    ['isbn', 'ISBN'],
    ['borrowed_at', 'Issued'],
    ['due_date', 'Due'],
    ['status', 'Status'],
    ['days_overdue', 'Days Overdue'],
  ];
}

function reportMemberName(row) {
  return `${row.first_name || ''} ${row.last_name || ''}`.trim() || row.username || row.email || '-';
}

function reportCell(row, key) {
  if(key === 'member') return reportMemberName(row);
  if(key === 'book') return row.title || '-';
  if(key === 'amount') return Number(row.amount || 0).toFixed(2);
  return row[key] ?? '';
}

function renderReports() {
  const el = document.getElementById('page-reports');
  if(!el) return;

  const type = document.getElementById('report-type')?.value || reportsState.type;
  const status = document.getElementById('report-status')?.value || reportsState.status;
  const isFineReport = type === 'fines';
  const rows = reportsState.rows || [];
  const columns = reportColumns(type);
  const paidTotal = reportsState.totals?.paid_total ?? 0;
  const unpaidTotal = reportsState.totals?.unpaid_total ?? 0;
  resetPaginationOnFilterChange('reports', JSON.stringify({
    type: reportsState.type,
    from: reportsState.from,
    to: reportsState.to,
    user: reportsState.user,
    status: reportsState.status,
    total: rows.length,
  }));
  const pagedReport = paginateItems(rows, paginationState.reports || 1);
  paginationState.reports = pagedReport.currentPage;

  el.innerHTML = `
    <div class="page-header">
      <div><div class="page-title">Reports</div><div class="page-subtitle">Review issued books, overdue loans, and fine balances.</div></div>
      <div class="page-actions"><button class="btn btn-gold btn-sm" onclick="exportCurrentReportCsv()">Export CSV</button></div>
    </div>
    <div class="card mb-3"><div class="card-body">
      <div class="filter-control-row report-filter-row">
        <select class="form-select" id="report-type" onchange="scheduleReportLoad(0)">
          <option value="issued" ${type==='issued'?'selected':''}>Issued Books</option>
          <option value="overdue" ${type==='overdue'?'selected':''}>Overdue Books</option>
          <option value="fines" ${type==='fines'?'selected':''}>Fines</option>
        </select>
        <input class="form-input" id="report-from" type="date" value="${escapeAttr(reportsState.from)}" aria-label="Start date" onchange="scheduleReportLoad(0)">
        <input class="form-input" id="report-to" type="date" value="${escapeAttr(reportsState.to)}" aria-label="End date" onchange="scheduleReportLoad(0)">
        <div class="search-bar report-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input id="report-user" value="${escapeAttr(reportsState.user)}" placeholder="Search user ID, name, email" oninput="scheduleReportLoad()">
        </div>
        <select class="form-select" id="report-status" onchange="scheduleReportLoad(0)">
          ${isFineReport
            ? `<option value="all" ${status==='all'?'selected':''}>All Fine Statuses</option>
               <option value="paid" ${status==='paid'?'selected':''}>Paid</option>
               <option value="unpaid" ${status==='unpaid'?'selected':''}>Unpaid</option>
               <option value="collected" ${status==='collected'?'selected':''}>Collected</option>
               <option value="outstanding" ${status==='outstanding'?'selected':''}>Outstanding</option>`
            : `<option value="all" ${status==='all'?'selected':''}>All Loan Statuses</option>
               <option value="borrowed" ${status==='borrowed'?'selected':''}>Borrowed</option>
               <option value="returned" ${status==='returned'?'selected':''}>Returned</option>
               <option value="lost" ${status==='lost'?'selected':''}>Lost</option>
               <option value="damaged" ${status==='damaged'?'selected':''}>Damaged</option>`}
        </select>
        <button class="btn btn-ghost btn-sm" onclick="clearReportFilters()">Clear</button>
      </div>
    </div></div>
    ${isFineReport ? `<div class="stats-grid">
      <div class="stat-card sage"><div class="stat-label">Collected</div><div class="stat-value">₱${Number(paidTotal).toLocaleString('en-PH')}</div></div>
      <div class="stat-card rust"><div class="stat-label">Outstanding</div><div class="stat-value">₱${Number(unpaidTotal).toLocaleString('en-PH')}</div></div>
      <div class="stat-card slate"><div class="stat-label">Rows</div><div class="stat-value">${rows.length}</div></div>
    </div>` : ''}
    <div class="card"><div class="card-body" style="padding:0">
      ${rows.length ? `<div class="table-wrap"><table>
        <thead><tr>${columns.map(([,label])=>`<th>${label}</th>`).join('')}</tr></thead>
        <tbody>${pagedReport.items.map(row=>`<tr>${columns.map(([key])=>`<td>${escapeAttr(reportCell(row, key))}</td>`).join('')}</tr>`).join('')}</tbody>
      </table></div>${renderPaginationControls({key:'reports', totalItems:pagedReport.totalItems, currentPage:pagedReport.currentPage, onPageChange:'renderReports'})}` : '<div class="empty-state"><p>No report rows found</p></div>'}
    </div></div>`;

  if(!reportsState.loaded) {
    reportsState.loaded = true;
    setTimeout(loadReportTable, 0);
  }
}

function syncReportFilters() {
  const nextType = document.getElementById('report-type')?.value || reportsState.type;
  const typeChanged = nextType !== reportsState.type;
  reportsState.type = nextType;
  reportsState.from = document.getElementById('report-from')?.value || '';
  reportsState.to = document.getElementById('report-to')?.value || '';
  reportsState.user = document.getElementById('report-user')?.value?.trim() || '';
  reportsState.status = typeChanged ? 'all' : (document.getElementById('report-status')?.value || 'all');
  if(typeChanged) {
    const status = document.getElementById('report-status');
    if(status) status.value = 'all';
  }
}

function scheduleReportLoad(delay = 250) {
  syncReportFilters();
  paginationState.reports = 1;
  if(reportFilterTimer) clearTimeout(reportFilterTimer);
  reportFilterTimer = setTimeout(loadReportTable, delay);
}

function clearReportFilters() {
  reportsState.from = '';
  reportsState.to = '';
  reportsState.user = '';
  reportsState.status = 'all';
  paginationState.reports = 1;
  ['report-from', 'report-to', 'report-user'].forEach(id => {
    const input = document.getElementById(id);
    if(input) input.value = '';
  });
  const status = document.getElementById('report-status');
  if(status) status.value = 'all';
  loadReportTable();
}

async function loadReportTable() {
  syncReportFilters();
  if(reportFilterTimer) {
    clearTimeout(reportFilterTimer);
    reportFilterTimer = null;
  }
  const isFineReport = reportsState.type === 'fines';
  try {
    const data = await apiRequest(API_ENDPOINTS.reports, {
      action: isFineReport ? 'fine_report' : 'loan_report',
      report: reportsState.type,
      from: reportsState.from,
      to: reportsState.to,
      user: reportsState.user,
      status: reportsState.status,
    });
    reportsState.rows = data.rows || [];
    reportsState.totals = isFineReport ? data : null;
    reportsState.loaded = true;
    renderReports();
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function exportCurrentReportCsv() {
  const rows = reportsState.rows || [];
  if(!rows.length) {
    showToast('No report rows to export.', 'info');
    return;
  }

  const columns = reportColumns(reportsState.type);
  const escapeCsv = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
  const csv = [
    columns.map(([, label])=>escapeCsv(label)).join(','),
    ...rows.map(row=>columns.map(([key])=>escapeCsv(reportCell(row, key))).join(',')),
  ].join('\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8'});
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `schodex-${reportsState.type}-report.csv`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
