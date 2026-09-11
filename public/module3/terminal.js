/**
 * EcoAsset - Module 3: Maintenance Crew Terminal Logic
 * Complete Dynamic Workflow: 
 * - My Tasks (Accept / Decline dispatches)
 * - Open Pool (Prioritized queue & Self-Claim)
 * - In-Progress (Mark Done -> PRECOMPLETE, Drop Task -> DROPPED -10pts, Flag Invalid -> PRE_INVALID)
 */

let crewProfile = null;
let myTasksList = [];
let openPoolList = [];
let selectedPoolSkill = 'ALL';
let activeTab = 'my-tasks';
let activeModalTask = null;
let pendingConfirmation = null;

// Photo staging states for Field Lifecycle actions
let completePhotoDataUrl = null;
let flagPhotoDataUrl = null;

document.addEventListener('DOMContentLoaded', async () => {
  // 1. Guard check for crew or admin role
  if (typeof AuthGuard !== 'undefined') {
    const isAuthed = AuthGuard.requireAuth(['MAINTENANCE_CREW', 'ADMINISTRATOR']);
    if (!isAuthed) return;
  }

  // 2. Initialize terminal data
  await loadCrewTerminalData();
});

async function loadCrewTerminalData() {
  await fetchCrewProfile();
  await Promise.all([
    fetchMyTasks(),
    fetchOpenPool()
  ]);
}

/**
 * Fetch logged-in crew profile and base location
 */
async function fetchCrewProfile() {
  try {
    const response = await AuthGuard.fetchWithAuth('/api/index.php/api/v1/crew/profile');
    if (!response || !response.ok) throw new Error('Profile fetch failed');
    const res = await response.json();

    if (res.status === 'success' && res.data?.profile) {
      crewProfile = res.data.profile;

      // Render top header
      const nameEl = document.getElementById('crewNameDisplay');
      if (nameEl) nameEl.textContent = `👷 ${crewProfile.full_name || 'Technician'}`;

      const rawScore = parseFloat(crewProfile.performance_score) || 100.0;
      const stars = (rawScore / 20.0).toFixed(1);
      const scoreEl = document.getElementById('crewScoreDisplay');
      if (scoreEl) scoreEl.textContent = `★ ${stars} / 5.0 (${rawScore.toFixed(0)}%)`;

      // Update Duty Status badge
      updateDutyBadge();

      // Show matching skills banner in open pool
      if (crewProfile.skill_tags && crewProfile.skill_tags.length > 0) {
        const notice = document.getElementById('openPoolSkillNotice');
        const text = document.getElementById('openPoolSkillNoticeText');
        if (notice && text) {
          const skillLabels = crewProfile.skill_tags.map(s => formatSkill(s)).join(', ');
          text.innerHTML = `🎯 Filtered for your registered capabilities: <strong>${skillLabels}</strong>`;
          notice.style.display = 'block';
        }
      }
    }
  } catch (err) {
    console.error('Error loading crew profile:', err);
    showToast('⚠️ Could not load crew profile.', true);
  }
}

/**
 * Update Header Duty Availability Badge
 */
function updateDutyBadge() {
  const badge = document.getElementById('dutyBadge');
  if (!badge) return;

  const inProgressCount = myTasksList.filter(t => t.status === 'IN_PROGRESS' || t.report_status === 'IN_PROGRESS').length;
  const pendingCount = myTasksList.filter(t => t.status === 'PENDING_ACCEPTANCE' || t.report_status === 'PENDING_ACCEPTANCE').length;

  if (inProgressCount > 0) {
    badge.className = 'duty-status-badge busy';
    badge.innerHTML = `<span>🔴 Busy (${inProgressCount} on task)</span>`;
  } else if (pendingCount > 0) {
    badge.className = 'duty-status-badge pending';
    badge.innerHTML = `<span>🟡 Pending (${pendingCount} review)</span>`;
  } else {
    badge.className = 'duty-status-badge available';
    badge.innerHTML = '<span>🟢 Available</span>';
  }
}

/**
 * Fetch tasks assigned to logged-in crew
 */
async function fetchMyTasks() {
  try {
    const response = await AuthGuard.fetchWithAuth('/api/index.php/api/v1/tasks/my-tasks');
    if (!response || !response.ok) throw new Error('Failed to fetch assigned tasks');
    const res = await response.json();

    if (res.status === 'success' && res.data?.tasks) {
      myTasksList = res.data.tasks || [];
      renderMyTasksPane();
      renderInProgressPane();
      renderUnderReviewPane();
      renderCompletedPane();
      renderApplicationResultPane();
      updateTabBadges();
      updateDutyBadge();
    }
  } catch (err) {
    console.error('Error fetching assigned tasks:', err);
    showToast('⚠️ Could not load assigned tasks.', true);
  }
}

/**
 * Fetch open unassigned tasks from Open Pool
 */
async function fetchOpenPool(skill = selectedPoolSkill) {
  try {
    let url = '/api/index.php/api/v1/tasks/open-pool';
    if (skill && skill !== 'ALL') {
      url += `?skill=${encodeURIComponent(skill)}`;
    }

    const response = await AuthGuard.fetchWithAuth(url);
    if (!response || !response.ok) throw new Error('Failed to fetch open pool');
    const res = await response.json();

    if (res.status === 'success' && res.data?.tasks) {
      openPoolList = res.data.tasks || [];
      renderOpenPoolPane();
      updateTabBadges();
    }
  } catch (err) {
    console.error('Error fetching open pool:', err);
    showToast('⚠️ Could not load open pool.', true);
  }
}

/**
 * Update tab counter badges
 */
function updateTabBadges() {
  const pendingTasks = myTasksList.filter(t => (t.status === 'PENDING_ACCEPTANCE' || t.report_status === 'PENDING_ACCEPTANCE'));
  const inProgressTasks = myTasksList.filter(t => (t.status === 'IN_PROGRESS' || t.report_status === 'IN_PROGRESS'));
  const underReviewTasks = myTasksList.filter(t => (
    t.status === 'PRECOMPLETE' || t.report_status === 'PRECOMPLETE' ||
    t.status === 'PRE_INVALID' || t.report_status === 'PRE_INVALID'
  ));
  const completedTasks = myTasksList.filter(t => (t.status === 'COMPLETED' || t.report_status === 'COMPLETED'));

  const nowTs = new Date().getTime();
  const appResultTasks = myTasksList.filter(t => {
    const isInvalid = (t.status === 'INVALID_RESOLVED' || t.report_status === 'INVALID_RESOLVED');
    if (!isInvalid) return false;
    if (!t.completed_at) return true;
    const compTs = new Date(t.completed_at).getTime();
    return (nowTs - compTs) <= (7 * 24 * 3600 * 1000);
  });

  const myBadge = document.getElementById('myTasksBadge');
  const poolBadge = document.getElementById('openPoolBadge');
  const progBadge = document.getElementById('inProgressBadge');
  const reviewBadge = document.getElementById('underReviewBadge');
  const compBadge = document.getElementById('completedBadge');
  const appBadge = document.getElementById('appResultBadge');

  if (myBadge) myBadge.textContent = pendingTasks.length;
  if (poolBadge) poolBadge.textContent = openPoolList.length;
  if (progBadge) progBadge.textContent = inProgressTasks.length;
  if (reviewBadge) reviewBadge.textContent = underReviewTasks.length;
  if (compBadge) compBadge.textContent = completedTasks.length;
  if (appBadge) appBadge.textContent = appResultTasks.length;
}

/**
 * Switch Navigation Tab
 */
window.switchTerminalTab = function(tabName) {
  activeTab = tabName;
  document.querySelectorAll('.terminal-tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tabName);
  });
  document.querySelectorAll('.tab-view-pane').forEach(pane => {
    pane.classList.toggle('active', pane.id === `pane-${tabName}`);
  });
};

/**
 * Filter Open Pool by Trade Capability
 */
window.filterOpenPoolBySkill = function(skill) {
  selectedPoolSkill = skill;
  document.querySelectorAll('.skill-filter-chip').forEach(chip => {
    chip.classList.toggle('active', chip.dataset.skill === skill);
  });
  fetchOpenPool(skill);
};

/* =============================================================================
   PANE 1: RENDER MY TASKS (PENDING ACCEPTANCE)
   ============================================================================= */
function renderMyTasksPane() {
  const container = document.getElementById('myTasksListContainer');
  if (!container) return;

  const pendingTasks = myTasksList.filter(t => (t.status === 'PENDING_ACCEPTANCE' || t.report_status === 'PENDING_ACCEPTANCE'));

  if (pendingTasks.length === 0) {
    container.innerHTML = `
      <div class="empty-state-card">
        <span style="font-size: 36px;">🎉</span>
        <h4 style="font-family: var(--font-heading); font-size: 15px; color: #1e293b; margin-top: 6px;">No Pending Dispatches</h4>
        <p style="font-size: 12.5px; color: #64748b; margin-top: 4px;">
          You have no tasks awaiting acceptance. Browse the <strong>Open Pool</strong> to self-claim available jobs.
        </p>
      </div>
    `;
    return;
  }

  container.innerHTML = pendingTasks.map(task => {
    const distText = computeDistanceString(task.latitude, task.longitude);
    const pScore = parseFloat(task.priority_score) || 0.0;
    const catClass = getCategoryBadgeClass(task.category);

    return `
      <div class="task-card" onclick="openTaskDetailModal('${task.task_id}', 'my-tasks')">
        <div class="task-card-header">
          <div class="task-badges-cluster">
            <span class="badge badge-pscore">P ${pScore.toFixed(1)}</span>
            <span class="badge ${catClass}">${formatCategory(task.category)}</span>
            <span class="badge badge-aesthetic">${formatSkill(task.required_skill)}</span>
          </div>
          <div class="sla-badge live-sla-timer" data-sla="${task.sla_expires_at || ''}">
            <span>⏱️ ${formatRemainingSlaTime(task.sla_expires_at)}</span>
          </div>
        </div>

        <div>
          <h4 class="task-card-title">${escapeHtml(task.district || 'Incident Location')}</h4>
          <div class="task-card-location">
            <span class="task-card-distance">📍 ${distText}</span>
            <span>• Tap to inspect full work order</span>
          </div>
        </div>

        <p class="task-card-desc">
          ${escapeHtml(task.description || 'Facility damage reported by visitor.')}
        </p>

        <div class="task-card-footer" onclick="event.stopPropagation();">
          <button class="btn btn-primary" style="flex: 2;" onclick="promptConfirmAction('ACCEPT', '${task.task_id}')">
            <span>✓ Accept Task</span>
          </button>
          <button class="btn btn-secondary" style="flex: 1;" onclick="openDeclineModal('${task.task_id}')">
            <span>Decline</span>
          </button>
        </div>
      </div>
    `;
  }).join('');
}

/* =============================================================================
   PANE 2: RENDER OPEN MAINTENANCE POOL
   ============================================================================= */
function renderOpenPoolPane() {
  const container = document.getElementById('openPoolListContainer');
  if (!container) return;

  if (openPoolList.length === 0) {
    container.innerHTML = `
      <div class="empty-state-card">
        <span style="font-size: 36px;">📦</span>
        <h4 style="font-family: var(--font-heading); font-size: 15px; color: #1e293b; margin-top: 6px;">Open Pool Empty</h4>
        <p style="font-size: 12.5px; color: #64748b; margin-top: 4px;">
          There are currently no unallocated damage reports in this category.
        </p>
      </div>
    `;
    return;
  }

  const isBusy = myTasksList.some(t => (t.status === 'IN_PROGRESS' || t.report_status === 'IN_PROGRESS'));

  container.innerHTML = openPoolList.map(task => {
    const distText = computeDistanceString(task.latitude, task.longitude);
    const pScore = parseFloat(task.priority_score) || 0.0;
    const catClass = getCategoryBadgeClass(task.category);

    return `
      <div class="task-card" onclick="openTaskDetailModal('${task.task_id}', 'open-pool')">
        <div class="task-card-header">
          <div class="task-badges-cluster">
            <span class="badge badge-pscore">P ${pScore.toFixed(1)}</span>
            <span class="badge ${catClass}">${formatCategory(task.category)}</span>
            <span class="badge badge-aesthetic">${formatSkill(task.required_skill)}</span>
          </div>
          <span style="font-size: 11.5px; font-weight: 700; color: #0284c7;">Open for Claim</span>
        </div>

        <div>
          <h4 class="task-card-title">${escapeHtml(task.district || 'Incident Location')}</h4>
          <div class="task-card-location">
            <span class="task-card-distance">📍 ${distText}</span>
            <span>• Tap to inspect details</span>
          </div>
        </div>

        <p class="task-card-desc">
          ${escapeHtml(task.description || 'Facility defect available for maintenance takeover.')}
        </p>

        <button class="btn btn-primary btn-block" onclick="event.stopPropagation(); handleClaimButtonClick('${task.task_id}', ${isBusy})">
          <span>⚡ Claim Task (Lock & Start)</span>
        </button>
      </div>
    `;
  }).join('');
}

function handleClaimButtonClick(taskId, isBusy) {
  if (isBusy) {
    showToast('⚠️ Finish your current in-progress task before claiming another.', true);
    return;
  }
  promptConfirmAction('CLAIM', taskId);
}

/* =============================================================================
   PANE 3: RENDER IN PROGRESS (ACTIVE WORK ORDERS + REJECTION / RETURN ALERTS)
   ============================================================================= */
function renderInProgressPane() {
  const container = document.getElementById('inProgressListContainer');
  if (!container) return;

  const inProgressTasks = myTasksList.filter(t => (t.status === 'IN_PROGRESS' || t.report_status === 'IN_PROGRESS'));

  if (inProgressTasks.length === 0) {
    container.innerHTML = `
      <div class="empty-state-card">
        <span style="font-size: 36px;">🔧</span>
        <h4 style="font-family: var(--font-heading); font-size: 15px; color: #1e293b; margin-top: 6px;">No Active Work Order</h4>
        <p style="font-size: 12.5px; color: #64748b; margin-top: 4px;">
          Accept a dispatched task from "My Tasks" or self-claim an open job from the "Open Pool".
        </p>
      </div>
    `;
    return;
  }

  container.innerHTML = inProgressTasks.map(task => {
    const distText = computeDistanceString(task.latitude, task.longitude);
    const pScore = parseFloat(task.priority_score) || 0.0;
    const catClass = getCategoryBadgeClass(task.category);

    const returnReason = task.admin_return_reason || task.decline_reason;
    let returnAlertHtml = '';
    if (returnReason && returnReason.trim().length > 0) {
      returnAlertHtml = `
        <div class="rejection-notice-box" style="background:#fef2f2; border:2px solid #ef4444; border-radius:12px; padding:12px 14px; margin-bottom:12px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.12);">
          <div style="display:flex; align-items:center; gap:6px; font-weight:800; font-size:12.5px; color:#991b1b; margin-bottom:4px;">
            <span style="font-size:16px;">↩️</span>
            <span>RETURNED BY ADMIN FOR REWORK</span>
          </div>
          <div style="font-size:12.5px; color:#7f1d1d; line-height:1.45; background:#ffffff; padding:8px 10px; border-radius:8px; border-left:3px solid #dc2626;">
            <strong>Admin Remarks / Reason:</strong> ${escapeHtml(returnReason)}
          </div>
          <div style="font-size:11.5px; color:#b91c1c; margin-top:6px;">
            ⚠️ Please review the admin remarks, rectify the issue on-site, and resubmit completion proof or flag evidence.
          </div>
        </div>
      `;
    }

    return `
      <div class="task-card in-progress-card" onclick="openTaskDetailModal('${task.task_id}', 'in-progress')">
        ${returnAlertHtml}

        <div class="task-card-header">
          <div class="task-badges-cluster">
            <span class="badge badge-pscore">P ${pScore.toFixed(1)}</span>
            <span class="badge ${catClass}">${formatCategory(task.category)}</span>
            <span class="badge badge-resolved">In Progress</span>
          </div>
          <div class="sla-badge">
            <span>⏱️ Active Work Order</span>
          </div>
        </div>

        <div>
          <h4 class="task-card-title">${escapeHtml(task.district || 'Incident Location')}</h4>
          <div class="task-card-location">
            <span class="task-card-distance">📍 ${distText}</span>
            <span>• Tap card for full details</span>
          </div>
        </div>

        <p class="task-card-desc">${escapeHtml(task.description || '')}</p>

        <!-- Google Maps Launch Navigation Button -->
        <button class="btn btn-primary btn-block" style="background:#0284c7; margin-bottom: 8px;" onclick="event.stopPropagation(); launchNavigation(${task.latitude}, ${task.longitude})">
          <span>🚀 Launch Navigation in Google Maps</span>
        </button>

        <!-- 3 Field Execution Lifecycle Actions -->
        <label class="form-group-title" style="margin-top: 4px; margin-bottom: 6px;">Field Execution Actions</label>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;" onclick="event.stopPropagation();">
          <button class="btn btn-secondary" style="font-size:12px; padding:10px 4px; border:1px solid #10b981; color:#047857; background:#ecfdf5;" onclick="openCompleteModal('${task.task_id}')">
            <span>✅ Mark Done</span>
          </button>
          <button class="btn btn-secondary" style="font-size:12px; padding:10px 4px; border:1px solid #f59e0b; color:#b45309; background:#fffbeb;" onclick="openDropModal('${task.task_id}')">
            <span>⚠️ Drop Task</span>
          </button>
          <button class="btn btn-secondary" style="font-size:12px; padding:10px 4px; border:1px solid #ef4444; color:#b91c1c; background:#fef2f2;" onclick="openFlagInvalidModal('${task.task_id}')">
            <span>🚩 Flag Invalid</span>
          </button>
        </div>
      </div>
    `;
  }).join('');
}

/* =============================================================================
   PANE 4: RENDER UNDER REVIEW (PRECOMPLETE & PRE_INVALID AWAITING ADMIN REVIEW)
   ============================================================================= */
function renderUnderReviewPane() {
  const container = document.getElementById('underReviewListContainer');
  if (!container) return;

  const underReviewTasks = myTasksList.filter(t => (
    t.status === 'PRECOMPLETE' || t.report_status === 'PRECOMPLETE' ||
    t.status === 'PRE_INVALID' || t.report_status === 'PRE_INVALID'
  ));

  if (underReviewTasks.length === 0) {
    container.innerHTML = `
      <div class="empty-state-card">
        <span style="font-size: 36px;">⏳</span>
        <h4 style="font-family: var(--font-heading); font-size: 15px; color: #1e293b; margin-top: 6px;">No Submissions Under Review</h4>
        <p style="font-size: 12.5px; color: #64748b; margin-top: 4px;">
          Tasks you have submitted for completion validation or flagged as invalid will appear here while under review by municipal administration.
        </p>
      </div>
    `;
    return;
  }

  container.innerHTML = underReviewTasks.map(task => {
    const isPreComplete = (task.status === 'PRECOMPLETE' || task.report_status === 'PRECOMPLETE');
    const pScore = parseFloat(task.priority_score) || 0.0;
    const catClass = getCategoryBadgeClass(task.category);

    const originalPhoto = task.report_photo_url || task.photo_url || 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80';
    const proofPhoto = isPreComplete 
      ? (task.completion_photo_url || originalPhoto)
      : (task.flag_invalid_photo_url || originalPhoto);

    let badgeHtml = '';
    let statusNoticeHtml = '';
    let notesPreviewHtml = '';

    if (isPreComplete) {
      badgeHtml = `<span class="badge" style="background:#f3e8ff; color:#7e22ce; border:1px solid #d8b4fe;">⚖️ PRE-COMPLETE</span>`;
      statusNoticeHtml = `
        <div style="background:#faf5ff; border:1px solid #e9d5ff; padding:10px 12px; border-radius:10px; font-size:12px; color:#6b21a8; margin-top:8px;">
          ⏳ <strong>Awaiting Admin Review:</strong> Municipal administration will verify the before/after repair proof and award performance points (+5.0 pts).
        </div>
      `;
      notesPreviewHtml = `
        <div style="font-size:12.5px; color:#475569; background:#f8fafc; padding:8px 10px; border-radius:8px; margin-top:8px; border-left:3px solid #7e22ce;">
          <strong>Your Resolution Notes:</strong> ${escapeHtml(task.resolution_notes || 'Repairs completed on-site.')}
        </div>
      `;
    } else {
      badgeHtml = `<span class="badge" style="background:#fff1f2; color:#be123c; border:1px solid #fecdd3;">🚩 PRE-INVALID</span>`;
      statusNoticeHtml = `
        <div style="background:#fff1f2; border:1px solid #fecdd3; padding:10px 12px; border-radius:10px; font-size:12px; color:#9f1239; margin-top:8px;">
          🚩 <strong>Flag Under Investigation:</strong> Municipal administration is reviewing clear-site evidence to approve invalid closure.
        </div>
      `;
      notesPreviewHtml = `
        <div style="font-size:12.5px; color:#475569; background:#f8fafc; padding:8px 10px; border-radius:8px; margin-top:8px; border-left:3px solid #be123c;">
          <strong>Your Inspection Findings:</strong> ${escapeHtml(task.flag_invalid_notes || 'Clear site verified on-site.')}
        </div>
      `;
    }

    return `
      <div class="task-card under-review-card" onclick="openTaskDetailModal('${task.task_id}', 'under-review')">
        <div class="task-card-header">
          <div class="task-badges-cluster">
            <span class="badge badge-pscore">P ${pScore.toFixed(1)}</span>
            <span class="badge ${catClass}">${formatCategory(task.category)}</span>
            ${badgeHtml}
          </div>
          <span style="font-size: 11.5px; font-weight: 700; color: #7e22ce;">⏳ In Review</span>
        </div>

        <div>
          <h4 class="task-card-title">${escapeHtml(task.district || 'Incident Location')}</h4>
          <div class="task-card-location">
            <span>📍 ${escapeHtml(task.district || 'Incident Location')}</span>
            <span>• Tap to inspect proof</span>
          </div>
        </div>

        <p class="task-card-desc">${escapeHtml(task.description || '')}</p>

        <!-- Photo Comparison Preview -->
        <div class="proof-comparison-grid">
          <div class="proof-photo-item">
            <img src="${originalPhoto}" alt="Original Reported Damage">
            <div class="proof-photo-label">1. Reported Defect</div>
          </div>
          <div class="proof-photo-item">
            <img src="${proofPhoto}" alt="Submitted Field Proof">
            <div class="proof-photo-label">${isPreComplete ? '2. Your Repair Proof' : '2. Clear Site Proof'}</div>
          </div>
        </div>

        ${notesPreviewHtml}
        ${statusNoticeHtml}
      </div>
    `;
  }).join('');
}

/* =============================================================================
   PANE 5: RENDER COMPLETED (ADMIN APPROVED WORK ORDERS)
   ============================================================================= */
function renderCompletedPane() {
  const container = document.getElementById('completedListContainer');
  if (!container) return;

  const completedTasks = myTasksList.filter(t => (t.status === 'COMPLETED' || t.report_status === 'COMPLETED'));

  if (completedTasks.length === 0) {
    container.innerHTML = `
      <div class="empty-state-card">
        <span style="font-size: 36px;">✅</span>
        <h4 style="font-family: var(--font-heading); font-size: 15px; color: #1e293b; margin-top: 6px;">No Completed Tasks Yet</h4>
        <p style="font-size: 12.5px; color: #64748b; margin-top: 4px;">
          Work orders verified and approved by municipal administration will appear here with performance rewards.
        </p>
      </div>
    `;
    return;
  }

  container.innerHTML = completedTasks.map(task => {
    const pScore = parseFloat(task.priority_score) || 0.0;
    const catClass = getCategoryBadgeClass(task.category);
    const originalPhoto = task.report_photo_url || task.photo_url || 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80';
    const completionPhoto = task.completion_photo_url || originalPhoto;

    return `
      <div class="task-card completed-card" onclick="openTaskDetailModal('${task.task_id}', 'completed')">
        <div class="task-card-header">
          <div class="task-badges-cluster">
            <span class="badge badge-pscore">P ${pScore.toFixed(1)}</span>
            <span class="badge ${catClass}">${formatCategory(task.category)}</span>
            <span class="badge" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;">✅ COMPLETED</span>
            <span class="badge badge-pscore" style="background:#059669; color:#fff;">★ +5.0 PTS</span>
          </div>
          <span style="font-size: 11.5px; font-weight: 700; color: #047857;">Approved</span>
        </div>

        <div>
          <h4 class="task-card-title">${escapeHtml(task.district || 'Incident Location')}</h4>
          <div class="task-card-location">
            <span>📍 ${escapeHtml(task.district || 'Incident Location')}</span>
            <span>• Approved: ${formatDateTime(task.completed_at || task.created_at)}</span>
          </div>
        </div>

        <p class="task-card-desc">${escapeHtml(task.description || '')}</p>

        <!-- Before vs After Photo Proof -->
        <div class="proof-comparison-grid">
          <div class="proof-photo-item">
            <img src="${originalPhoto}" alt="Original Reported Defect">
            <div class="proof-photo-label">Before Repair</div>
          </div>
          <div class="proof-photo-item">
            <img src="${completionPhoto}" alt="Completed Repair Proof">
            <div class="proof-photo-label">After Repair (Verified)</div>
          </div>
        </div>

        <div style="font-size:12.5px; color:#1e293b; background:#ffffff; padding:10px 12px; border-radius:8px; border:1px solid #d1fae5; margin-top:8px;">
          <div style="font-weight:700; color:#047857; margin-bottom:2px;">🛠️ Resolution Details:</div>
          <div>${escapeHtml(task.resolution_notes || 'All repairs completed and verified by municipal administrator.')}</div>
        </div>
      </div>
    `;
  }).join('');
}

/* =============================================================================
   PANE 6: RENDER APPLICATION RESULT (INVALID RESOLVED - LAST 7 DAYS)
   ============================================================================= */
function renderApplicationResultPane() {
  const container = document.getElementById('appResultListContainer');
  if (!container) return;

  const nowTs = new Date().getTime();
  const appResultTasks = myTasksList.filter(t => {
    const isInvalid = (t.status === 'INVALID_RESOLVED' || t.report_status === 'INVALID_RESOLVED');
    if (!isInvalid) return false;
    if (!t.completed_at) return true;
    const compTs = new Date(t.completed_at).getTime();
    return (nowTs - compTs) <= (7 * 24 * 3600 * 1000);
  });

  if (appResultTasks.length === 0) {
    container.innerHTML = `
      <div class="empty-state-card">
        <span style="font-size: 36px;">📋</span>
        <h4 style="font-family: var(--font-heading); font-size: 15px; color: #1e293b; margin-top: 6px;">No Recent Application Results</h4>
        <p style="font-size: 12.5px; color: #64748b; margin-top: 4px;">
          Outcomes for invalid report clearance applications approved in the last 7 days will be displayed here.
        </p>
      </div>
    `;
    return;
  }

  container.innerHTML = appResultTasks.map(task => {
    const pScore = parseFloat(task.priority_score) || 0.0;
    const catClass = getCategoryBadgeClass(task.category);
    const originalPhoto = task.report_photo_url || task.photo_url || 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80';
    const clearSitePhoto = task.flag_invalid_photo_url || originalPhoto;

    return `
      <div class="task-card invalid-card" onclick="openTaskDetailModal('${task.task_id}', 'application-result')">
        <div class="task-card-header">
          <div class="task-badges-cluster">
            <span class="badge badge-pscore">P ${pScore.toFixed(1)}</span>
            <span class="badge ${catClass}">${formatCategory(task.category)}</span>
            <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;">⚪ INVALID RESOLVED</span>
            <span class="badge" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;">✓ Approved by Admin</span>
          </div>
          <span style="font-size: 11.5px; font-weight: 700; color: #64748b;">Resolved (7 Days)</span>
        </div>

        <div>
          <h4 class="task-card-title">${escapeHtml(task.district || 'Incident Location')}</h4>
          <div class="task-card-location">
            <span>📍 ${escapeHtml(task.district || 'Incident Location')}</span>
            <span>• Marked Invalid: ${formatDateTime(task.completed_at || task.created_at)}</span>
          </div>
        </div>

        <p class="task-card-desc">${escapeHtml(task.description || '')}</p>

        <!-- Reported Photo vs Clear Site Evidence -->
        <div class="proof-comparison-grid">
          <div class="proof-photo-item">
            <img src="${originalPhoto}" alt="Original Reported Defect">
            <div class="proof-photo-label">Reported Defect</div>
          </div>
          <div class="proof-photo-item">
            <img src="${clearSitePhoto}" alt="Clear Site Proof">
            <div class="proof-photo-label">Clear Site Proof (Approved)</div>
          </div>
        </div>

        <div style="font-size:12.5px; color:#1e293b; background:#ffffff; padding:10px 12px; border-radius:8px; border:1px solid #e2e8f0; margin-top:8px;">
          <div style="font-weight:700; color:#475569; margin-bottom:2px;">🔍 Inspection Findings:</div>
          <div>${escapeHtml(task.flag_invalid_notes || 'Site inspected and verified as no fault found.')}</div>
        </div>

        <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:10px 12px; border-radius:10px; font-size:12px; color:#166534; margin-top:8px;">
          ✅ <strong>Application Approved:</strong> Municipal administration verified your on-site evidence and marked this report as invalid (no penalty applied).
        </div>
      </div>
    `;
  }).join('');
}

/* =============================================================================
   TASK DETAIL DRAWER MODAL
   ============================================================================= */
window.openTaskDetailModal = function(taskId, sourceTab) {
  let task = myTasksList.find(t => t.task_id === taskId) || openPoolList.find(t => t.task_id === taskId);
  if (!task) return;

  activeModalTask = task;

  const originalPhoto = task.report_photo_url || task.photo_url || 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80';
  const taskStatus = task.status || task.report_status;

  const singleWrap = document.getElementById('modalSinglePhotoWrap');
  const dualWrap = document.getElementById('modalDualPhotoWrap');

  // Check if this is a dual-photo state (Pre-Complete, Pre-Invalid, Completed, Invalid Resolved)
  const isDualState = ['PRECOMPLETE', 'PRE_INVALID', 'COMPLETED', 'INVALID_RESOLVED'].includes(taskStatus);

  if (isDualState) {
    if (singleWrap) singleWrap.style.display = 'none';
    if (dualWrap) {
      dualWrap.style.display = 'block';

      const beforeImg = document.getElementById('modalDualBeforePhoto');
      const afterImg = document.getElementById('modalDualAfterPhoto');
      const beforeLbl = document.getElementById('modalDualBeforeLabel');
      const afterLbl = document.getElementById('modalDualAfterLabel');
      const statBadge = document.getElementById('modalDualStatusBadge');
      const catBadge = document.getElementById('modalDualCategoryBadge');
      const tradeBadge = document.getElementById('modalDualTradeBadge');

      if (catBadge) {
        catBadge.textContent = formatCategory(task.category);
        catBadge.className = `badge ${getCategoryBadgeClass(task.category)}`;
      }
      if (tradeBadge) tradeBadge.textContent = formatSkill(task.required_skill);

      if (taskStatus === 'PRECOMPLETE') {
        if (beforeImg) beforeImg.src = originalPhoto;
        if (afterImg) afterImg.src = task.completion_photo_url || originalPhoto;
        if (beforeLbl) beforeLbl.textContent = '1. Reported Defect';
        if (afterLbl) afterLbl.textContent = '2. Your Repair Proof';
        if (statBadge) {
          statBadge.textContent = '⚖️ Pre-Complete';
          statBadge.style.background = '#f3e8ff';
          statBadge.style.color = '#7e22ce';
        }
      } else if (taskStatus === 'COMPLETED') {
        if (beforeImg) beforeImg.src = originalPhoto;
        if (afterImg) afterImg.src = task.completion_photo_url || originalPhoto;
        if (beforeLbl) beforeLbl.textContent = 'Before Repair';
        if (afterLbl) afterLbl.textContent = 'After Repair (Verified)';
        if (statBadge) {
          statBadge.textContent = '✅ Completed (+5.0 pts)';
          statBadge.style.background = '#ecfdf5';
          statBadge.style.color = '#047857';
        }
      } else if (taskStatus === 'PRE_INVALID') {
        if (beforeImg) beforeImg.src = originalPhoto;
        if (afterImg) afterImg.src = task.flag_invalid_photo_url || originalPhoto;
        if (beforeLbl) beforeLbl.textContent = '1. Reported Incident';
        if (afterLbl) afterLbl.textContent = '2. Clear Site Proof';
        if (statBadge) {
          statBadge.textContent = '🚩 Pre-Invalid';
          statBadge.style.background = '#fff1f2';
          statBadge.style.color = '#be123c';
        }
      } else if (taskStatus === 'INVALID_RESOLVED') {
        if (beforeImg) beforeImg.src = originalPhoto;
        if (afterImg) afterImg.src = task.flag_invalid_photo_url || originalPhoto;
        if (beforeLbl) beforeLbl.textContent = 'Reported Defect';
        if (afterLbl) afterLbl.textContent = 'Clear Site Proof (Approved)';
        if (statBadge) {
          statBadge.textContent = '⚪ Invalid Resolved';
          statBadge.style.background = '#f1f5f9';
          statBadge.style.color = '#475569';
        }
      }
    }
  } else {
    if (dualWrap) dualWrap.style.display = 'none';
    if (singleWrap) {
      singleWrap.style.display = 'block';
      const photoEl = document.getElementById('modalTaskPhoto');
      if (photoEl) photoEl.src = originalPhoto;
    }
  }
  
  const titleEl = document.getElementById('modalTaskTitle');
  if (titleEl) titleEl.textContent = task.district || 'Damage Incident';

  const locEl = document.getElementById('modalTaskLocation');
  if (locEl) locEl.textContent = `📍 ${computeDistanceString(task.latitude, task.longitude)} (${parseFloat(task.latitude).toFixed(4)}, ${parseFloat(task.longitude).toFixed(4)})`;

  const descEl = document.getElementById('modalTaskDesc');
  if (descEl) {
    const returnReason = task.admin_return_reason || task.decline_reason;
    if (task.status === 'IN_PROGRESS' && returnReason && returnReason.trim().length > 0) {
      descEl.innerHTML = `
        <div style="background:#fef2f2; border:1.5px solid #f87171; border-radius:8px; padding:10px; margin-bottom:10px; color:#991b1b;">
          <strong>↩️ Returned by Admin for Rework:</strong><br>
          <span style="color:#7f1d1d;">${escapeHtml(returnReason)}</span>
        </div>
        <div>${escapeHtml(task.description || 'No description provided.')}</div>
      `;
    } else {
      descEl.textContent = task.description || 'No description provided.';
    }
  }

  const repEl = document.getElementById('modalTaskReporter');
  if (repEl) repEl.textContent = task.reporter_name || 'Citizen User';

  const upEl = document.getElementById('modalTaskUpvotes');
  if (upEl) upEl.textContent = task.upvote_count || 1;

  const pScoreEl = document.getElementById('modalTaskPScore');
  if (pScoreEl) pScoreEl.textContent = `P ${(parseFloat(task.priority_score) || 0).toFixed(1)}`;

  const slaEl = document.getElementById('modalTaskSla');
  if (slaEl) {
    if (task.status === 'COMPLETED') {
      slaEl.textContent = 'Completed';
      slaEl.style.color = '#059669';
    } else if (task.status === 'INVALID_RESOLVED') {
      slaEl.textContent = 'Resolved';
      slaEl.style.color = '#64748b';
    } else if (task.status === 'PRECOMPLETE' || task.status === 'PRE_INVALID') {
      slaEl.textContent = 'In Review';
      slaEl.style.color = '#7c3aed';
    } else if (task.status === 'PENDING_ACCEPTANCE' || task.report_status === 'PENDING_ACCEPTANCE') {
      const remaining = formatRemainingSlaTime(task.sla_expires_at);
      slaEl.textContent = remaining;
      slaEl.style.color = (remaining === 'SLA Expired') ? '#dc2626' : '#d97706';
    } else if (task.status === 'IN_PROGRESS' || task.report_status === 'IN_PROGRESS') {
      slaEl.textContent = 'In Progress';
      slaEl.style.color = '#0284c7';
    } else {
      slaEl.textContent = 'Open for Claim';
      slaEl.style.color = '#059669';
    }
  }

  const catBadge = document.getElementById('modalCategoryBadge');
  if (catBadge) {
    catBadge.textContent = formatCategory(task.category);
    catBadge.className = `badge ${getCategoryBadgeClass(task.category)}`;
  }

  const tradeBadge = document.getElementById('modalTradeBadge');
  if (tradeBadge) tradeBadge.textContent = formatSkill(task.required_skill);

  // Dynamic modal action footer based on source tab
  const footer = document.getElementById('modalActionFooter');
  const isBusy = myTasksList.some(t => (t.status === 'IN_PROGRESS' || t.report_status === 'IN_PROGRESS'));

  if (footer) {
    if (sourceTab === 'my-tasks') {
      footer.innerHTML = `
        <button class="btn btn-primary" style="flex:2;" onclick="closeTaskDetailModal(); promptConfirmAction('ACCEPT', '${task.task_id}')">
          <span>✓ Accept Task</span>
        </button>
        <button class="btn btn-secondary" style="flex:1;" onclick="closeTaskDetailModal(); openDeclineModal('${task.task_id}')">
          <span>Decline</span>
        </button>
      `;
    } else if (sourceTab === 'open-pool') {
      footer.innerHTML = `
        <button class="btn btn-primary btn-block" onclick="closeTaskDetailModal(); handleClaimButtonClick('${task.task_id}', ${isBusy})">
          <span>⚡ Claim Task (Lock & Start)</span>
        </button>
      `;
    } else if (sourceTab === 'in-progress') {
      footer.innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 8px; width: 100%;">
          <button class="btn btn-primary btn-block" style="background:#0284c7;" onclick="launchNavigation(${task.latitude}, ${task.longitude})">
            <span>🚀 Launch Google Maps</span>
          </button>
          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px;">
            <button class="btn btn-secondary" style="font-size:11.5px; border:1px solid #10b981; color:#047857;" onclick="closeTaskDetailModal(); openCompleteModal('${task.task_id}')">
              <span>✅ Done</span>
            </button>
            <button class="btn btn-secondary" style="font-size:11.5px; border:1px solid #f59e0b; color:#b45309;" onclick="closeTaskDetailModal(); openDropModal('${task.task_id}')">
              <span>⚠️ Drop</span>
            </button>
            <button class="btn btn-secondary" style="font-size:11.5px; border:1px solid #ef4444; color:#b91c1c;" onclick="closeTaskDetailModal(); openFlagInvalidModal('${task.task_id}')">
              <span>🚩 Flag</span>
            </button>
          </div>
        </div>
      `;
    } else {
      footer.innerHTML = `
        <button class="btn btn-secondary btn-block" onclick="closeTaskDetailModal()">
          <span>Close Details</span>
        </button>
      `;
    }
  }

  const modal = document.getElementById('taskDetailModal');
  const backdrop = document.getElementById('taskDetailBackdrop');
  if (modal) modal.classList.add('active', 'open');
  if (backdrop) backdrop.classList.add('active', 'open');
};

window.closeTaskDetailModal = function() {
  const modal = document.getElementById('taskDetailModal');
  const backdrop = document.getElementById('taskDetailBackdrop');
  if (modal) modal.classList.remove('active', 'open');
  if (backdrop) backdrop.classList.remove('active', 'open');
};

/* =============================================================================
   CONFIRMATION MODAL & ACTIONS SYSTEM
   ============================================================================= */
window.promptConfirmAction = function(action, taskId, extraData = null) {
  pendingConfirmation = { action, taskId, extraData };

  const title = document.getElementById('crewConfirmTitle');
  const desc = document.getElementById('crewConfirmDesc');

  if (action === 'ACCEPT') {
    title.textContent = 'Accept Work Order?';
    desc.textContent = 'You will be designated as the active technician. Your duty status will update to Busy.';
  } else if (action === 'CLAIM') {
    title.textContent = 'Claim Open Work Order?';
    desc.textContent = 'This task will be locked to your account and moved immediately to In Progress.';
  } else if (action === 'COMPLETE_SUBMIT') {
    title.textContent = 'Submit Completed Repairs?';
    desc.textContent = 'The work order will enter PRECOMPLETE status for municipal administrator validation.';
  } else if (action === 'DROP_SUBMIT') {
    title.textContent = '⚠️ Confirm Dropping Task?';
    desc.textContent = 'Dropping this task will deduct 10 points from your performance rating and mark status as DROPPED for administrative review.';
  } else if (action === 'FLAG_SUBMIT') {
    title.textContent = 'Flag as Invalid Report?';
    desc.textContent = 'The incident will enter PRE_INVALID status with your inspection findings for municipal administrator validation.';
  }

  const modal = document.getElementById('crewConfirmModal');
  if (modal) modal.classList.add('active', 'open');
};

window.closeCrewConfirmModal = function() {
  pendingConfirmation = null;
  const modal = document.getElementById('crewConfirmModal');
  if (modal) modal.classList.remove('active', 'open');
};

window.executeConfirmedAction = async function() {
  if (!pendingConfirmation) return;
  const { action, taskId, extraData } = pendingConfirmation;
  closeCrewConfirmModal();

  if (action === 'ACCEPT') {
    await executeAcceptTask(taskId);
  } else if (action === 'CLAIM') {
    await executeClaimTask(taskId);
  } else if (action === 'COMPLETE_SUBMIT') {
    await executeCompleteTask(taskId, extraData);
  } else if (action === 'DROP_SUBMIT') {
    await executeDropTask(taskId, extraData);
  } else if (action === 'FLAG_SUBMIT') {
    await executeFlagInvalid(taskId, extraData);
  }
};

async function executeAcceptTask(taskId) {
  try {
    const response = await AuthGuard.fetchWithAuth(`/api/index.php/api/v1/tasks/${taskId}/accept`, {
      method: 'POST'
    });
    const res = await response.json();

    if (res.status === 'success') {
      showToast('✓ Work order accepted! Switched to In Progress.');
      switchTerminalTab('in-progress');
      await loadCrewTerminalData();
    } else {
      showToast('❌ ' + (res.message || 'Failed to accept task.'), true);
    }
  } catch (err) {
    showToast('❌ Error accepting task: ' + err.message, true);
  }
}

async function executeClaimTask(taskId) {
  try {
    const response = await AuthGuard.fetchWithAuth(`/api/index.php/api/v1/tasks/${taskId}/claim`, {
      method: 'POST'
    });
    const res = await response.json();

    if (res.status === 'success') {
      showToast('⚡ Task successfully claimed! Switched to In Progress.');
      switchTerminalTab('in-progress');
      await loadCrewTerminalData();
    } else {
      showToast('⚠️ ' + (res.message || 'Could not claim task.'), true);
      await fetchOpenPool();
    }
  } catch (err) {
    showToast('❌ Error claiming task: ' + err.message, true);
  }
}

/* =============================================================================
   DECLINE TASK MODAL
   ============================================================================= */
let currentDeclineTaskId = null;

window.openDeclineModal = function(taskId) {
  currentDeclineTaskId = taskId;
  const sel = document.getElementById('declineReasonSelect');
  if (sel) sel.value = 'SPECIALIZED_EQUIPMENT';
  
  const container = document.getElementById('declineOtherReasonContainer');
  if (container) container.style.display = 'none';

  const input = document.getElementById('declineOtherReasonInput');
  if (input) input.value = '';

  const modal = document.getElementById('declineDrawerModal');
  const backdrop = document.getElementById('declineModalBackdrop');
  if (modal) modal.classList.add('active', 'open');
  if (backdrop) backdrop.classList.add('active', 'open');
};

window.closeDeclineModal = function() {
  currentDeclineTaskId = null;
  const modal = document.getElementById('declineDrawerModal');
  const backdrop = document.getElementById('declineModalBackdrop');
  if (modal) modal.classList.remove('active', 'open');
  if (backdrop) backdrop.classList.remove('active', 'open');
};

window.handleDeclineReasonChange = function(select) {
  const container = document.getElementById('declineOtherReasonContainer');
  if (container) container.style.display = select.value === 'OTHER' ? 'block' : 'none';
};

window.submitDeclineTask = async function() {
  if (!currentDeclineTaskId) return;

  const select = document.getElementById('declineReasonSelect');
  let reason = select.options[select.selectedIndex].text;
  if (select.value === 'OTHER') {
    const custom = document.getElementById('declineOtherReasonInput').value.trim();
    if (!custom) {
      showToast('⚠️ Please specify custom decline reason.', true);
      return;
    }
    reason = `Other: ${custom}`;
  }

  try {
    const response = await AuthGuard.fetchWithAuth(`/api/index.php/api/v1/tasks/${currentDeclineTaskId}/decline`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reason })
    });
    const res = await response.json();

    if (res.status === 'success') {
      closeDeclineModal();
      showToast('✓ Task declined and returned to Open Pool.');
      await loadCrewTerminalData();
    } else {
      showToast('❌ ' + (res.message || 'Failed to decline task.'), true);
    }
  } catch (err) {
    showToast('❌ Error declining task: ' + err.message, true);
  }
};

/* =============================================================================
   FIELD LIFECYCLE 1: COMPLETE TASK (AFTER-REPAIR PHOTO PROOF -> PRECOMPLETE)
   ============================================================================= */
let currentCompleteTaskId = null;

window.openCompleteModal = function(taskId) {
  currentCompleteTaskId = taskId;
  completePhotoDataUrl = null;
  
  const img = document.getElementById('completeCapturedImg');
  if (img) { img.style.display = 'none'; img.src = ''; }
  
  const ph = document.getElementById('completePlaceholder');
  if (ph) ph.style.display = 'flex';
  
  const notes = document.getElementById('completeNotes');
  if (notes) notes.value = '';

  const modal = document.getElementById('completeDrawerModal');
  const backdrop = document.getElementById('completeModalBackdrop');
  if (modal) modal.classList.add('active', 'open');
  if (backdrop) backdrop.classList.add('active', 'open');
};

window.handleCompleteFileSelected = function(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    completePhotoDataUrl = e.target.result;
    const img = document.getElementById('completeCapturedImg');
    const ph = document.getElementById('completePlaceholder');
    if (img) { img.src = completePhotoDataUrl; img.style.display = 'block'; }
    if (ph) ph.style.display = 'none';
    showToast('📷 After-repair photo loaded.');
  };
  reader.readAsDataURL(file);
};

window.handleCompletePhotoCapture = function() {
  const fileInput = document.getElementById('completeFileInput');
  if (fileInput) {
    fileInput.click();
  } else {
    // Fallback sample image
    completePhotoDataUrl = 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=600&q=80';
    const img = document.getElementById('completeCapturedImg');
    const ph = document.getElementById('completePlaceholder');
    if (img) { img.src = completePhotoDataUrl; img.style.display = 'block'; }
    if (ph) ph.style.display = 'none';
    showToast('📷 Live after-repair photo captured.');
  }
};

window.submitCompleteTask = function() {
  if (!currentCompleteTaskId) return;

  // If no custom file was picked, use default on-site proof
  if (!completePhotoDataUrl) {
    completePhotoDataUrl = 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=600&q=80';
  }

  const notesEl = document.getElementById('completeNotes');
  const notes = notesEl ? notesEl.value.trim() : '';

  if (!notes) {
    showToast('⚠️ Please enter resolution notes describing the repair.', true);
    return;
  }

  // Prompt confirmation dialog
  promptConfirmAction('COMPLETE_SUBMIT', currentCompleteTaskId, {
    photo_url: completePhotoDataUrl,
    notes: notes
  });
};

async function executeCompleteTask(taskId, data) {
  try {
    const response = await AuthGuard.fetchWithAuth(`/api/index.php/api/v1/tasks/${taskId}/complete`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const res = await response.json();

    if (res.status === 'success') {
      closeAllDrawers();
      showToast('✅ Work order submitted for administrator validation (PRECOMPLETE).');
      await loadCrewTerminalData();
    } else {
      showToast('❌ ' + (res.message || 'Failed to submit completion.'), true);
    }
  } catch (err) {
    showToast('❌ Error submitting completion: ' + err.message, true);
  }
}

/* =============================================================================
   FIELD LIFECYCLE 2: DROP TASK (-10 PENALTY -> DROPPED)
   ============================================================================= */
let currentDropTaskId = null;

window.openDropModal = function(taskId) {
  currentDropTaskId = taskId;
  const sel = document.getElementById('dropReasonSelect');
  if (sel) sel.value = 'HEAVY_MACHINERY';
  
  const container = document.getElementById('otherReasonContainer');
  if (container) container.style.display = 'none';
  
  const input = document.getElementById('otherReasonInput');
  if (input) input.value = '';

  const modal = document.getElementById('dropDrawerModal');
  const backdrop = document.getElementById('dropModalBackdrop');
  if (modal) modal.classList.add('active', 'open');
  if (backdrop) backdrop.classList.add('active', 'open');
};

window.handleDropReasonChange = function(select) {
  const container = document.getElementById('otherReasonContainer');
  if (container) container.style.display = select.value === 'OTHER' ? 'block' : 'none';
};

window.submitDropTask = function() {
  if (!currentDropTaskId) return;

  const select = document.getElementById('dropReasonSelect');
  let reason = select.options[select.selectedIndex].text;
  if (select.value === 'OTHER') {
    const custom = document.getElementById('otherReasonInput').value.trim();
    if (!custom) {
      showToast('⚠️ Please specify drop reason.', true);
      return;
    }
    reason = `Other: ${custom}`;
  }

  // Prompt confirmation dialog with -10 penalty notice
  promptConfirmAction('DROP_SUBMIT', currentDropTaskId, { reason });
};

async function executeDropTask(taskId, data) {
  try {
    const response = await AuthGuard.fetchWithAuth(`/api/index.php/api/v1/tasks/${taskId}/drop`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const res = await response.json();

    if (res.status === 'success') {
      closeAllDrawers();
      showToast('⚠️ Work order dropped (DROPPED). Team rating adjusted (-10 pts).');
      await loadCrewTerminalData();
    } else {
      showToast('❌ ' + (res.message || 'Failed to drop task.'), true);
    }
  } catch (err) {
    showToast('❌ Error dropping task: ' + err.message, true);
  }
}

/* =============================================================================
   FIELD LIFECYCLE 3: FLAG INVALID (PRE_INVALID STATUS)
   ============================================================================= */
let currentFlagTaskId = null;

window.openFlagInvalidModal = function(taskId) {
  currentFlagTaskId = taskId;
  flagPhotoDataUrl = null;

  const img = document.getElementById('flagCapturedImg');
  if (img) { img.style.display = 'none'; img.src = ''; }

  const ph = document.getElementById('flagPlaceholder');
  if (ph) ph.style.display = 'flex';

  const notes = document.getElementById('flagNotes');
  if (notes) notes.value = '';

  const modal = document.getElementById('flagDrawerModal');
  const backdrop = document.getElementById('flagModalBackdrop');
  if (modal) modal.classList.add('active', 'open');
  if (backdrop) backdrop.classList.add('active', 'open');
};

window.handleFlagFileSelected = function(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    flagPhotoDataUrl = e.target.result;
    const img = document.getElementById('flagCapturedImg');
    const ph = document.getElementById('flagPlaceholder');
    if (img) { img.src = flagPhotoDataUrl; img.style.display = 'block'; }
    if (ph) ph.style.display = 'none';
    showToast('📷 Clear-site proof photo loaded.');
  };
  reader.readAsDataURL(file);
};

window.handleFlagPhotoCapture = function() {
  const fileInput = document.getElementById('flagFileInput');
  if (fileInput) {
    fileInput.click();
  } else {
    flagPhotoDataUrl = 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80';
    const img = document.getElementById('flagCapturedImg');
    const ph = document.getElementById('flagPlaceholder');
    if (img) { img.src = flagPhotoDataUrl; img.style.display = 'block'; }
    if (ph) ph.style.display = 'none';
    showToast('📷 Clear-site proof photo captured.');
  }
};

window.submitFlagInvalid = function() {
  if (!currentFlagTaskId) return;

  if (!flagPhotoDataUrl) {
    flagPhotoDataUrl = 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80';
  }

  const notesEl = document.getElementById('flagNotes');
  const notes = notesEl ? notesEl.value.trim() : '';

  if (!notes) {
    showToast('⚠️ Inspection findings are required to flag invalid report.', true);
    return;
  }

  // Prompt confirmation dialog
  promptConfirmAction('FLAG_SUBMIT', currentFlagTaskId, {
    photo_url: flagPhotoDataUrl,
    notes: notes
  });
};

async function executeFlagInvalid(taskId, data) {
  try {
    const response = await AuthGuard.fetchWithAuth(`/api/index.php/api/v1/tasks/${taskId}/flag-invalid`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const res = await response.json();

    if (res.status === 'success') {
      closeAllDrawers();
      showToast('🚩 Incident flagged as invalid (PRE_INVALID). Awaiting administrator review.');
      await loadCrewTerminalData();
    } else {
      showToast('❌ ' + (res.message || 'Failed to flag task.'), true);
    }
  } catch (err) {
    showToast('❌ Error flagging task: ' + err.message, true);
  }
}

/* =============================================================================
   DRAWER / MODAL CLOSE HELPERS
   ============================================================================= */
window.closeAllDrawers = function() {
  document.querySelectorAll('.bottom-drawer').forEach(d => {
    d.classList.remove('open', 'active');
  });
  document.querySelectorAll('.drawer-backdrop').forEach(b => {
    b.classList.remove('open', 'active');
  });
};

window.launchNavigation = function(lat, lng) {
  window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`, '_blank');
};

/* =============================================================================
   UTILITY HELPERS
   ============================================================================= */
function computeDistanceString(lat, lng) {
  if (!crewProfile || !lat || !lng) return 'Nearby';
  const cLat = parseFloat(crewProfile.current_latitude) || 3.1578;
  const cLng = parseFloat(crewProfile.current_longitude) || 101.7119;
  
  const dLat = (lat - cLat) * Math.PI / 180;
  const dLon = (lng - cLng) * Math.PI / 180;
  const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(cLat * Math.PI / 180) * Math.cos(lat * Math.PI / 180) *
            Math.sin(dLon/2) * Math.sin(dLon/2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  const dKm = 6371.0 * c;

  return `${dKm.toFixed(1)} km away`;
}

function formatCategory(cat) {
  switch (cat) {
    case 'CRITICAL_SAFETY': return 'Critical Safety';
    case 'MAJOR_BLOCKAGE':
    case 'OPERATIONAL': return 'Major Blockage';
    case 'MINOR_DEFECT': return 'Minor Defect';
    case 'AESTHETIC': return 'Aesthetic';
    default: return cat || 'Facility Defect';
  }
}

function getCategoryBadgeClass(cat) {
  switch (cat) {
    case 'CRITICAL_SAFETY': return 'badge-critical';
    case 'MAJOR_BLOCKAGE':
    case 'OPERATIONAL': return 'badge-operational';
    default: return 'badge-aesthetic';
  }
}

function formatSkill(skill) {
  switch (skill) {
    case 'STRUCTURAL': return 'Structural';
    case 'ELECTRICAL': return 'Electrical';
    case 'SANITATION': return 'Sanitation';
    case 'GENERAL_FACILITY': return 'Facilities';
    case 'OTHER': return 'General / Other';
    default: return skill || 'General';
  }
}

function showToast(msg, isError = false) {
  const toast = document.getElementById('toastBanner');
  if (!toast) return;
  const msgEl = document.getElementById('toastMsg');
  if (msgEl) msgEl.textContent = msg;
  toast.style.background = isError ? '#ef4444' : '#059669';
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3500);
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatDateTime(dateStr) {
  if (!dateStr) return '--';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  } catch (e) {
    return dateStr;
  }
}

/* =============================================================================
   REAL-TIME SLA TIMER TICKER FOR PENDING WORK ORDERS
   ============================================================================= */
function parseTimestamp(tsStr) {
  if (!tsStr) return null;
  let formatted = String(tsStr).replace(' ', 'T');
  let d = new Date(formatted);
  if (isNaN(d.getTime())) {
    d = new Date(tsStr);
  }
  return isNaN(d.getTime()) ? null : d;
}

function formatRemainingSlaTime(slaExpiresAt) {
  if (!slaExpiresAt) return '24h SLA';

  const d = parseTimestamp(slaExpiresAt);
  if (!d) return '24h SLA';

  const now = Date.now();
  const diffSec = Math.floor((d.getTime() - now) / 1000);

  if (diffSec <= 0) {
    return 'SLA Expired';
  }

  const hours = Math.floor(diffSec / 3600);
  const mins = Math.floor((diffSec % 3600) / 60);
  const secs = diffSec % 60;

  if (hours > 0) {
    return `${hours}h ${String(mins).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s left`;
  } else {
    return `${mins}m ${String(secs).padStart(2, '0')}s left`;
  }
}

function updateAllLiveSlaTimers() {
  // 1. Update all task card badges in DOM
  document.querySelectorAll('.live-sla-timer').forEach(el => {
    const slaExpires = el.getAttribute('data-sla');
    if (slaExpires) {
      const formatted = formatRemainingSlaTime(slaExpires);
      const span = el.querySelector('span') || el;
      span.textContent = `⏱️ ${formatted}`;
      if (formatted === 'SLA Expired') {
        el.style.color = '#dc2626';
        el.style.borderColor = '#fca5a5';
        el.style.background = '#fef2f2';
      } else {
        el.style.color = '#d97706';
        el.style.borderColor = '#fde68a';
        el.style.background = '#fef3c7';
      }
    }
  });

  // 2. Update task detail modal timer if active task is pending acceptance
  if (activeModalTask && (activeModalTask.status === 'PENDING_ACCEPTANCE' || activeModalTask.report_status === 'PENDING_ACCEPTANCE')) {
    const modalSlaEl = document.getElementById('modalTaskSla');
    if (modalSlaEl && activeModalTask.sla_expires_at) {
      const formatted = formatRemainingSlaTime(activeModalTask.sla_expires_at);
      modalSlaEl.textContent = formatted;
      if (formatted === 'SLA Expired') {
        modalSlaEl.style.color = '#dc2626';
      } else {
        modalSlaEl.style.color = '#d97706';
      }
    }
  }
}

// Start global 1-second interval ticker for live SLA countdowns
setInterval(updateAllLiveSlaTimers, 1000);


