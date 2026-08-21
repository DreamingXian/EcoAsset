/**
 * EcoAsset - Module 3: Maintenance Crew Terminal
 * Multi-Task Roster, Detail Modal, Pre-Complete Admin Workflow & Drop/Flag Penalties
 */

document.addEventListener('DOMContentLoaded', () => {
  let isAvailable = true;
  let activeTab = 'my-tasks';
  let activeModalTask = null;
  let activeActionTaskId = null;
  let afterPhotoCaptured = false;
  let clearPhotoCaptured = false;

  // Mock Active Crew Tasks Database
  const mockTasks = {
    'RPT-2401': {
      id: 'RPT-2401',
      category: 'CRITICAL_SAFETY',
      categoryLabel: 'Critical Safety',
      trade: 'Structural',
      title: 'Broken handrail on pedestrian bridge',
      address: 'Persiaran KLCC, Bukit Bintang',
      description: 'Severe structural fracture on metal railing posing high falling hazard for tourists.',
      lat: 3.1579,
      lng: 101.7120,
      distance: '0.8 km away',
      upvotes: 47,
      pScore: 9.2,
      slaRemaining: '2h 14m SLA',
      reporter: 'Ahmad Faizal',
      photoUrl: 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80',
      adminComment: null
    },
    'RPT-2389': {
      id: 'RPT-2389',
      category: 'OPERATIONAL',
      categoryLabel: 'Operational',
      trade: 'Electrical',
      title: 'Exposed street lighting wiring',
      address: 'Jalan Ampang, KLCC',
      description: 'Open electrical junction box with dangling live wires near tourist walking trail.',
      lat: 3.1595,
      lng: 101.7180,
      distance: '1.2 km away',
      upvotes: 31,
      pScore: 8.5,
      slaRemaining: '4h 50m SLA',
      reporter: 'Noraini Mohd',
      photoUrl: 'https://images.unsplash.com/photo-1555680202-c86f0e12f086?auto=format&fit=crop&w=600&q=80',
      adminComment: '⚠️ Admin Rejection Notice: Previous photo was out of focus. Please capture clear wiring junction box after repair.'
    },
    'RPT-2340': {
      id: 'RPT-2340',
      category: 'OPERATIONAL',
      categoryLabel: 'Operational',
      trade: 'Structural',
      title: 'Blocked drainage pipe causing pathway flooding',
      address: 'Jalan Pahang, Titiwangsa',
      description: 'Damaged storm drainage grate causing pathway blockage during rain.',
      lat: 3.1690,
      lng: 101.6990,
      distance: '5.1 km away',
      upvotes: 38,
      pScore: 8.9,
      slaRemaining: '24h SLA (Unassigned)',
      reporter: 'Johnathan Doe',
      photoUrl: 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=600&q=80',
      adminComment: null
    }
  };

  // 1. Tab Switching
  window.switchTerminalTab = function(tabName) {
    activeTab = tabName;
    document.querySelectorAll('.terminal-tab-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.tab === tabName);
    });
    document.querySelectorAll('.tab-view-pane').forEach(pane => {
      pane.classList.toggle('active', pane.id === `pane-${tabName}`);
    });
  };

  // 2. Open Task Detail Modal (Supports My Tasks, Open Pool, and In Progress)
  window.openTaskDetailModal = function(taskId, sourceTab) {
    const task = mockTasks[taskId];
    if (!task) return;
    activeModalTask = task;
    activeActionTaskId = taskId;

    document.getElementById('modalTaskPhoto').src = task.photoUrl;
    document.getElementById('modalTaskTitle').textContent = task.title;
    document.getElementById('modalTaskLocation').textContent = task.address;
    document.getElementById('modalTaskDesc').textContent = task.description;
    document.getElementById('modalTaskReporter').textContent = task.reporter;
    document.getElementById('modalTaskUpvotes').textContent = task.upvotes;
    document.getElementById('modalTaskPScore').textContent = `P ${task.pScore}`;
    document.getElementById('modalTaskSla').textContent = task.slaRemaining;

    document.getElementById('modalCategoryBadge').textContent = task.categoryLabel;
    document.getElementById('modalCategoryBadge').className = `badge badge-${task.category.toLowerCase().replace('_', '-')}`;
    document.getElementById('modalTradeBadge').textContent = task.trade;

    const commentBox = document.getElementById('modalAdminCommentBox');
    if (task.adminComment) {
      document.getElementById('modalAdminCommentText').textContent = task.adminComment;
      commentBox.style.display = 'flex';
    } else {
      commentBox.style.display = 'none';
    }

    const actionFooter = document.getElementById('modalActionFooter');
    if (sourceTab === 'my-tasks') {
      actionFooter.innerHTML = `
        <button class="btn btn-primary" style="flex:2;" onclick="promptConfirmAction('ACCEPT', '${task.id}')">
          <span>✓ Accept Work Order</span>
        </button>
        <button class="btn btn-secondary" style="flex:1;" onclick="promptConfirmAction('DECLINE', '${task.id}')">
          <span>Decline</span>
        </button>
      `;
    } else if (sourceTab === 'open-pool') {
      actionFooter.innerHTML = `
        <button class="btn btn-primary btn-block" onclick="promptConfirmAction('CLAIM', '${task.id}')">
          <span>⚡ Claim Task (Lock & Start)</span>
        </button>
      `;
    } else if (sourceTab === 'in-progress') {
      actionFooter.innerHTML = `
        <div style="display: flex; flex-direction: column; gap: 10px; width: 100%;">
          <button class="btn btn-primary btn-block" style="background:#0284c7;" onclick="launchNavigation('${task.lat}', '${task.lng}')">
            <span>🚀 Open in Google Maps</span>
          </button>
          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
            <button class="btn btn-secondary" style="font-size:12px; padding:10px 4px; border:1px solid #10b981; color:#047857; background:#ecfdf5;" onclick="closeTaskDetailModal(); openCompleteModal('${task.id}')">
              <span>✅ Mark Done</span>
            </button>
            <button class="btn btn-secondary" style="font-size:12px; padding:10px 4px; border:1px solid #f59e0b; color:#b45309; background:#fffbeb;" onclick="closeTaskDetailModal(); openDropModal('${task.id}')">
              <span>⚠️ Drop Task</span>
            </button>
            <button class="btn btn-secondary" style="font-size:12px; padding:10px 4px; border:1px solid #ef4444; color:#b91c1c; background:#fef2f2;" onclick="closeTaskDetailModal(); openFlagInvalidModal('${task.id}')">
              <span>🚩 Flag Invalid</span>
            </button>
          </div>
        </div>
      `;
    }

    document.getElementById('taskDetailBackdrop').classList.add('active');
    document.getElementById('taskDetailModal').classList.add('active');
  };

  window.closeTaskDetailModal = function() {
    document.getElementById('taskDetailBackdrop').classList.remove('active');
    document.getElementById('taskDetailModal').classList.remove('active');
  };

  // 3. Confirmation Dialog Prompt System
  let pendingActionType = null;
  let pendingActionTaskId = null;
  let pendingActionPayload = null;

  window.promptConfirmAction = function(actionType, taskId, payload = null) {
    pendingActionType = actionType;
    pendingActionTaskId = taskId || activeActionTaskId;
    pendingActionPayload = payload;

    const modal = document.getElementById('crewConfirmModal');
    const title = document.getElementById('crewConfirmTitle');
    const desc = document.getElementById('crewConfirmDesc');

    if (actionType === 'ACCEPT') {
      title.textContent = 'Accept Task Assignment?';
      desc.textContent = `You are accepting ${pendingActionTaskId}. It will move to your In-Progress work orders. Note: System will pause auto-dispatching new tasks until cleared.`;
    } else if (actionType === 'DECLINE') {
      title.textContent = 'Decline Task Assignment?';
      desc.textContent = `You are declining ${pendingActionTaskId}. It will be released to the Open Maintenance Pool.`;
    } else if (actionType === 'CLAIM') {
      title.textContent = 'Claim Unassigned Job?';
      desc.textContent = `A transactional database lock will bind ${pendingActionTaskId} to your technician account.`;
    } else if (actionType === 'COMPLETE') {
      title.textContent = 'Submit Task Completion?';
      desc.textContent = `You are submitting after-repair proof for ${pendingActionTaskId}. The task will enter PENDING ADMIN REVIEW.`;
    } else if (actionType === 'DROP') {
      title.textContent = 'Confirm Dropping Task?';
      desc.textContent = `Warning: Dropping ${pendingActionTaskId} will incur a -5.00 point deduction to your performance rating and return the job to the Open Pool.`;
    } else if (actionType === 'FLAG_INVALID') {
      title.textContent = 'Confirm Invalid / No Fault Flag?';
      desc.textContent = `You are reporting that ${pendingActionTaskId} has no damage. If verified by admin, the original reporter will receive a -20 reputation penalty.`;
    }

    modal.classList.add('active');
  };

  window.executeConfirmedAction = function() {
    document.getElementById('crewConfirmModal').classList.remove('active');
    closeAllDrawers();

    if (pendingActionType === 'ACCEPT') {
      acceptTaskFlow(pendingActionTaskId);
    } else if (pendingActionType === 'DECLINE') {
      declineTaskFlow(pendingActionTaskId);
    } else if (pendingActionType === 'CLAIM') {
      claimTaskFlow(pendingActionTaskId);
    } else if (pendingActionType === 'COMPLETE') {
      completeTaskFlow(pendingActionTaskId);
    } else if (pendingActionType === 'DROP') {
      dropTaskFlow(pendingActionTaskId, pendingActionPayload);
    } else if (pendingActionType === 'FLAG_INVALID') {
      flagInvalidFlow(pendingActionTaskId, pendingActionPayload);
    }
  };

  window.closeCrewConfirmModal = function() {
    document.getElementById('crewConfirmModal').classList.remove('active');
  };

  // 4. Accept / Decline / Claim Workflows
  function acceptTaskFlow(taskId) {
    isAvailable = false;
    updateDutyBadge();

    const card = document.getElementById(`my-task-${taskId}`);
    if (card) card.style.display = 'none';

    document.getElementById('inProgressEmpty').style.display = 'none';
    const inProgCard = document.getElementById(`in-prog-${taskId}`);
    if (inProgCard) inProgCard.style.display = 'flex';

    decrementBadge('myTasksBadge');
    incrementBadge('inProgressBadge');

    showToast(`Task ${taskId} accepted! Work order active. Auto-dispatch paused.`);
  }

  function declineTaskFlow(taskId) {
    const card = document.getElementById(`my-task-${taskId}`);
    if (card) card.style.display = 'none';

    decrementBadge('myTasksBadge');
    incrementBadge('openPoolBadge');

    showToast(`Task ${taskId} declined. Moved to Open Maintenance Pool.`);
  }

  function claimTaskFlow(taskId) {
    isAvailable = false;
    updateDutyBadge();

    const card = document.getElementById(`pool-task-${taskId}`);
    if (card) card.style.display = 'none';

    decrementBadge('openPoolBadge');
    incrementBadge('inProgressBadge');

    document.getElementById('inProgressEmpty').style.display = 'none';
    const inProgCard = document.getElementById(`in-prog-${taskId}`);
    if (inProgCard) inProgCard.style.display = 'flex';

    showToast(`Transactional Lock OK! ${taskId} bound to your roster.`);
  }

  // 5. Navigation Launch
  window.launchNavigation = function(lat, lng) {
    const url = `https://www.google.com/maps/dir/?api=1&destination=${lat || 3.1579},${lng || 101.7120}`;
    window.open(url, '_blank');
  };

  // 6. MARK AS DONE FLOW
  window.openCompleteModal = function(taskId) {
    activeActionTaskId = taskId || 'RPT-2401';
    afterPhotoCaptured = false;

    document.getElementById('completeFrame').classList.remove('captured');
    document.getElementById('completePlaceholder').style.display = 'flex';
    document.getElementById('completeCapturedImg').style.display = 'none';
    document.getElementById('btnCompletePhoto').innerHTML = '<span>📷 Take After-Repair Photo</span>';
    document.getElementById('completeNotes').value = '';

    document.getElementById('completeModalBackdrop').classList.add('active');
    document.getElementById('completeDrawerModal').classList.add('active');
  };

  window.handleCompletePhotoCapture = function() {
    afterPhotoCaptured = true;
    const frame = document.getElementById('completeFrame');
    const img = document.getElementById('completeCapturedImg');
    const placeholder = document.getElementById('completePlaceholder');
    const btn = document.getElementById('btnCompletePhoto');

    frame.classList.add('captured');
    placeholder.style.display = 'none';
    img.src = 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&w=600&q=80';
    img.style.display = 'block';
    btn.innerHTML = '<span>🔄 Retake After-Repair Photo</span>';

    showToast('Live After-Repair photo captured and stamped.');
  };

  window.submitCompleteTask = function() {
    if (!afterPhotoCaptured) {
      alert('Please capture a live after-repair proof photo before submitting.');
      return;
    }

    const notes = document.getElementById('completeNotes').value.trim();
    promptConfirmAction('COMPLETE', activeActionTaskId, { notes });
  };

  function completeTaskFlow(taskId) {
    const inProgCard = document.getElementById(`in-prog-${taskId}`);
    if (inProgCard) inProgCard.style.display = 'none';

    decrementBadge('inProgressBadge');
    document.getElementById('inProgressEmpty').style.display = 'flex';

    isAvailable = true;
    updateDutyBadge();

    showToast(`Task ${taskId} submitted! Status: PENDING ADMIN REVIEW (Pre-Complete).`);
  }

  // 7. DROP TASK FLOW
  window.openDropModal = function(taskId) {
    activeActionTaskId = taskId || 'RPT-2401';
    document.getElementById('dropReasonSelect').value = 'HEAVY_MACHINERY';
    document.getElementById('otherReasonContainer').style.display = 'none';
    document.getElementById('otherReasonInput').value = '';

    document.getElementById('dropModalBackdrop').classList.add('active');
    document.getElementById('dropDrawerModal').classList.add('active');
  };

  window.handleDropReasonChange = function(selectElem) {
    const otherBox = document.getElementById('otherReasonContainer');
    if (selectElem.value === 'OTHER') {
      otherBox.style.display = 'block';
    } else {
      otherBox.style.display = 'none';
    }
  };

  window.submitDropTask = function() {
    const selectVal = document.getElementById('dropReasonSelect').value;
    let reason = selectVal;
    if (selectVal === 'OTHER') {
      reason = document.getElementById('otherReasonInput').value.trim();
      if (!reason) {
        alert('Please specify the custom drop reason.');
        return;
      }
    }

    promptConfirmAction('DROP', activeActionTaskId, { reason });
  };

  function dropTaskFlow(taskId, payload) {
    const inProgCard = document.getElementById(`in-prog-${taskId}`);
    if (inProgCard) inProgCard.style.display = 'none';

    decrementBadge('inProgressBadge');
    incrementBadge('openPoolBadge');
    document.getElementById('inProgressEmpty').style.display = 'flex';

    isAvailable = true;
    updateDutyBadge();

    showToast(`Task dropped (${payload?.reason || 'Reassigned'}). -5.00 rating penalty applied.`);
  }

  // 8. FLAG INVALID FLOW
  window.openFlagInvalidModal = function(taskId) {
    activeActionTaskId = taskId || 'RPT-2401';
    clearPhotoCaptured = false;

    document.getElementById('flagFrame').classList.remove('captured');
    document.getElementById('flagPlaceholder').style.display = 'flex';
    document.getElementById('flagCapturedImg').style.display = 'none';
    document.getElementById('btnFlagPhoto').innerHTML = '<span>📷 Capture Clear-Site Photo</span>';
    document.getElementById('flagNotes').value = '';

    document.getElementById('flagModalBackdrop').classList.add('active');
    document.getElementById('flagDrawerModal').classList.add('active');
  };

  window.handleFlagPhotoCapture = function() {
    clearPhotoCaptured = true;
    const frame = document.getElementById('flagFrame');
    const img = document.getElementById('flagCapturedImg');
    const placeholder = document.getElementById('flagPlaceholder');
    const btn = document.getElementById('btnFlagPhoto');

    frame.classList.add('captured');
    placeholder.style.display = 'none';
    img.src = 'https://images.unsplash.com/photo-1519331379826-f10be5486c6f?auto=format&fit=crop&w=600&q=80';
    img.style.display = 'block';
    btn.innerHTML = '<span>🔄 Retake Clear-Site Photo</span>';

    showToast('Live clear-site photo captured.');
  };

  window.submitFlagInvalid = function() {
    if (!clearPhotoCaptured) {
      alert('Please capture a live clear-site proof photo before submitting.');
      return;
    }

    const notes = document.getElementById('flagNotes').value.trim();
    if (!notes) {
      alert('Inspection findings cannot be blank. Please explain why this report is invalid.');
      return;
    }

    promptConfirmAction('FLAG_INVALID', activeActionTaskId, { notes });
  };

  function flagInvalidFlow(taskId, payload) {
    const inProgCard = document.getElementById(`in-prog-${taskId}`);
    if (inProgCard) inProgCard.style.display = 'none';

    decrementBadge('inProgressBadge');
    document.getElementById('inProgressEmpty').style.display = 'flex';

    isAvailable = true;
    updateDutyBadge();

    showToast(`Task ${taskId} submitted to Municipal Admin for invalid verification.`);
  }

  // 9. HELPER FUNCTIONS
  window.closeAllDrawers = function() {
    document.querySelectorAll('.drawer-backdrop').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.bottom-drawer').forEach(d => d.classList.remove('active'));
  };

  function updateDutyBadge() {
    const badge = document.getElementById('dutyBadge');
    if (isAvailable) {
      badge.className = 'duty-status-badge available';
      badge.innerHTML = '<span>🟢 Available</span>';
    } else {
      badge.className = 'duty-status-badge busy';
      badge.innerHTML = '<span>🟠 Busy (On Task)</span>';
    }
  }

  function decrementBadge(id) {
    const el = document.getElementById(id);
    if (el) el.textContent = Math.max(0, parseInt(el.textContent) - 1);
  }

  function incrementBadge(id) {
    const el = document.getElementById(id);
    if (el) el.textContent = parseInt(el.textContent) + 1;
  }

  window.showToast = function(msg) {
    const toast = document.getElementById('toastBanner');
    document.getElementById('toastMsg').textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3800);
  };
});
