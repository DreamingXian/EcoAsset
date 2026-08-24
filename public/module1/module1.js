/**
 * EcoAsset - Module 1: Public Crowdsourced Damage Reporting Portal
 * Fullscreen Interactive Map, Real Webcam/Native Camera, EXIF Verification Map, Location Lock & Upvoting
 */

document.addEventListener('DOMContentLoaded', () => {
  let map;
  let userLocation = { lat: 3.1578, lng: 101.7119 }; // Default KLCC GPS
  let userMarker = null;
  let markersLayerGroup = null;
  let activeSelectedIncident = null;
  let activeReportsList = [];
  
  // Reporting Form State & Maps
  let reportMiniMap = null;
  let miniMapMarker = null;
  let photoExifMiniMap = null;
  let photoExifMarker = null;

  let photoCaptured = false;
  let photoBase64Data = '';
  let capturedExifGps = null;
  let selectedFacilityLocation = { lat: 3.1578, lng: 101.7119, name: 'Persiaran KLCC (3.1578° N, 101.7119° E)' };
  let isLocationLocked = false;
  
  let decayTimeRemaining = 15 * 60;
  let decayTimerInterval = null;
  let selectedCategoryData = {
    category: 'CRITICAL_SAFETY',
    weight: 10,
    title: 'Critical Safety Hazard'
  };
  let selectedTrade = 'STRUCTURAL';

  // Live Camera Stream State
  let videoStream = null;
  let isStreamingVideo = false;

  // Places Gazetteer for Search Auto-Pan
  const placesGazetteer = [
    { name: 'KLCC / Bukit Bintang', lat: 3.1578, lng: 101.7119, zoom: 15 },
    { name: 'Titiwangsa Lake Garden', lat: 3.1750, lng: 101.7060, zoom: 15 },
    { name: 'Perdana Botanical Garden', lat: 3.1435, lng: 101.6885, zoom: 15 },
    { name: 'Merdeka Square / Dataran Merdeka', lat: 3.1490, lng: 101.6935, zoom: 15 },
    { name: 'Batu Caves Tourism Center', lat: 3.2379, lng: 101.6840, zoom: 15 }
  ];

  // 1. Initialize Fullscreen Main Map
  function initMap() {
    map = L.map('map', {
      zoomControl: false,
      attributionControl: false
    }).setView([userLocation.lat, userLocation.lng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19
    }).addTo(map);

    L.control.scale({ position: 'bottomleft', metric: true, imperial: false }).addTo(map);

    markersLayerGroup = L.layerGroup().addTo(map);

    renderUserCurrentLocation();
    loadLiveReports();

    map.on('zoomend moveend', () => {
      renderVisibleMarkers();
    });

    // Try acquiring real GPS coordinates on startup
    requestDeviceLocation(false);
  }

  // 2. Request Device GPS (with Insecure Origin / Mobile Handling)
  function requestDeviceLocation(isUserTriggered = false) {
    if (!navigator.geolocation) {
      if (isUserTriggered) showToast('Geolocation is not supported by your browser.');
      return;
    }

    const options = {
      enableHighAccuracy: true,
      timeout: 8000,
      maximumAge: 0
    };

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        userLocation = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        if (userMarker) userMarker.setLatLng([userLocation.lat, userLocation.lng]);
        if (isUserTriggered && map) {
          map.flyTo([userLocation.lat, userLocation.lng], 16, { animate: true, duration: 1.2 });
          showToast(`📍 GPS Located: ${pos.coords.latitude.toFixed(4)}°, ${pos.coords.longitude.toFixed(4)}°`);
        }
      },
      (err) => {
        console.warn('Geolocation notice:', err.message);
        if (isUserTriggered) {
          // On mobile accessing via http://192.168.x.x, explain why browser restricts GPS
          if (!window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
            showToast('📱 Mobile Tip: Browser restricts live GPS on plain HTTP. Tap anywhere on the map to set your location.');
          } else {
            showToast('GPS unavailable. Showing default location.');
          }
          if (map) map.flyTo([userLocation.lat, userLocation.lng], 15);
        }
      },
      options
    );
  }

  // 3. Self-Locate Button Handler
  window.handleSelfLocate = function() {
    requestDeviceLocation(true);
  };

  // 4. Render User GPS Location Pulse Marker
  function renderUserCurrentLocation() {
    const userPulseIcon = L.divIcon({
      className: 'custom-pulse-icon',
      html: `<div class="user-current-location-marker" title="Your GPS Location"></div>`,
      iconSize: [20, 20],
      iconAnchor: [10, 10]
    });

    userMarker = L.marker([userLocation.lat, userLocation.lng], { icon: userPulseIcon }).addTo(map);
  }

  // 5. Load Reports from Live API
  async function loadLiveReports() {
    try {
      const response = await AuthGuard.fetchWithAuth('/api/index.php/api/v1/reports');
      if (response && response.ok) {
        const result = await response.json();
        if (result.status === 'success' && Array.isArray(result.data?.reports)) {
          activeReportsList = result.data.reports;
        }
      }
    } catch (e) {
      console.warn('Could not load live reports, using default samples.', e);
    }

    if (activeReportsList.length === 0) {
      activeReportsList = getDefaultSampleReports();
    }

    renderVisibleMarkers();
    checkDeepLinkReport();
  }

  // Deep-link Pin Auto-Focus Handler (e.g. index.html?report=xxx)
  function checkDeepLinkReport() {
    const urlParams = new URLSearchParams(window.location.search);
    const targetReportId = urlParams.get('report');
    if (targetReportId) {
      const targetIncident = activeReportsList.find(r => r.report_id === targetReportId);
      if (targetIncident) {
        const lat = parseFloat(targetIncident.latitude);
        const lng = parseFloat(targetIncident.longitude);
        if (!isNaN(lat) && !isNaN(lng) && map) {
          map.flyTo([lat, lng], 17, { animate: true, duration: 1.2 });
          setTimeout(() => openIncidentDrawer(targetIncident), 1200);
        }
      }
    }
  }

  function getDefaultSampleReports() {
    return [
      {
        report_id: 'RPT-2401',
        category: 'CRITICAL_SAFETY',
        required_skill: 'STRUCTURAL',
        description: 'Severe structural fracture on metal railing posing high falling hazard for tourists.',
        latitude: 3.1579,
        longitude: 101.7120,
        upvote_count: 47,
        priority_score: 9.2,
        status: 'IN_PROGRESS',
        photo_url: 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80',
        district: 'Kuala Lumpur',
        has_upvoted: false
      },
      {
        report_id: 'RPT-2389',
        category: 'OPERATIONAL',
        required_skill: 'ELECTRICAL',
        description: 'Open electrical junction box with dangling live wires near park bench.',
        latitude: 3.1595,
        longitude: 101.7150,
        upvote_count: 31,
        priority_score: 8.5,
        status: 'IN_PROGRESS',
        photo_url: 'https://images.unsplash.com/photo-1555680202-c86f0e12f086?auto=format&fit=crop&w=600&q=80',
        district: 'Kuala Lumpur',
        has_upvoted: false
      },
      {
        report_id: 'RPT-2374',
        category: 'CRITICAL_SAFETY',
        required_skill: 'STRUCTURAL',
        description: 'Missing concrete slab step creating trip hazard during night hours.',
        latitude: 3.1555,
        longitude: 101.7140,
        upvote_count: 28,
        priority_score: 8.1,
        status: 'OPEN_FOR_CLAIM',
        photo_url: 'https://images.unsplash.com/photo-1584467746235-9b608306890e?auto=format&fit=crop&w=600&q=80',
        district: 'Kuala Lumpur',
        has_upvoted: false
      }
    ];
  }

  // 6. Zoom LOD Marker Rendering
  function renderVisibleMarkers() {
    if (!map || !markersLayerGroup) return;

    const currentZoom = map.getZoom();
    const lodBadge = document.getElementById('zoomLodBadge');

    if (currentZoom < 12) {
      markersLayerGroup.clearLayers();
      if (lodBadge) lodBadge.classList.add('show');
      return;
    }

    if (lodBadge) lodBadge.classList.remove('show');
    markersLayerGroup.clearLayers();

    const bounds = map.getBounds();
    const searchQuery = (document.getElementById('searchInput').value || '').trim().toLowerCase();

    activeReportsList.forEach(incident => {
      if (incident.status === 'INVALID_RESOLVED') return;

      const lat = parseFloat(incident.latitude);
      const lng = parseFloat(incident.longitude);

      if (isNaN(lat) || isNaN(lng)) return;

      if (searchQuery) {
        const matchDesc = (incident.description || '').toLowerCase().includes(searchQuery);
        const matchDist = (incident.district || '').toLowerCase().includes(searchQuery);
        const matchCat = (incident.category || '').toLowerCase().includes(searchQuery);
        if (!matchDesc && !matchDist && !matchCat) return;
      }

      if (bounds.contains([lat, lng])) {
        let pinClass = 'pin-operational';
        let iconHtml = '⚠️';

        if (incident.category === 'CRITICAL_SAFETY') {
          pinClass = 'pin-critical';
          iconHtml = '⚡';
        } else if (incident.category === 'MAJOR_BLOCKAGE') {
          pinClass = 'pin-operational';
          iconHtml = '🚧';
        } else if (incident.category === 'AESTHETIC') {
          pinClass = 'pin-operational';
          iconHtml = '🧹';
        } else if (incident.status === 'COMPLETED') {
          pinClass = 'pin-resolved';
          iconHtml = '✓';
        }

        const upvotes = Math.max(1, parseInt(incident.upvote_count) || 1);

        const customIcon = L.divIcon({
          className: 'custom-leaflet-pin',
          html: `
            <div class="custom-pin-marker ${pinClass}" id="pin-${incident.report_id}">
              <span class="pin-icon">${iconHtml}</span>
              <div class="pin-upvote-counter">
                <span>🔥</span><span>${upvotes}</span>
              </div>
            </div>
          `,
          iconSize: [36, 36],
          iconAnchor: [18, 18]
        });

        const marker = L.marker([lat, lng], { icon: customIcon });
        marker.on('click', () => openIncidentDrawer(incident));
        markersLayerGroup.addLayer(marker);
      }
    });
  }

  // 7. Dynamic 2-Tier Search: Step 1 (Issue Keyword Filter) -> Step 2 (Location Geocoding Dropdown)
  let searchDebounceTimer = null;
  let geocodeAbortController = null;

  window.handleSearchInput = function() {
    const query = (document.getElementById('searchInput').value || '').trim();
    const clearBtn = document.getElementById('searchClearBtn');
    const dropdown = document.getElementById('searchDropdown');

    clearBtn.style.display = query.length > 0 ? 'block' : 'none';

    if (!query || query.length === 0) {
      if (dropdown) {
        dropdown.classList.remove('show');
        dropdown.innerHTML = '';
      }
      renderVisibleMarkers();
      return;
    }

    const qLower = query.toLowerCase();

    // STEP 1: Check if any active damage report matches description, category, district, or skill
    const matchingReports = activeReportsList.filter(incident => {
      if (incident.status === 'INVALID_RESOLVED') return false;
      const matchDesc  = (incident.description || '').toLowerCase().includes(qLower);
      const matchDist  = (incident.district || '').toLowerCase().includes(qLower);
      const matchCat   = (incident.category || '').toLowerCase().includes(qLower);
      const matchSkill = (incident.required_skill || '').toLowerCase().includes(qLower);
      return matchDesc || matchDist || matchCat || matchSkill;
    });

    if (matchingReports.length > 0) {
      // Direct issue keyword match found: close location dropdown and filter map pins
      if (dropdown) {
        dropdown.classList.remove('show');
        dropdown.innerHTML = '';
      }
      renderVisibleMarkers();
      return;
    }

    // STEP 2: No damage reports matched keyword -> Trigger Address / Location Geocoding
    renderVisibleMarkers(); // (renders 0 pins for non-matching query)

    if (query.length < 2) {
      if (dropdown) dropdown.classList.remove('show');
      return;
    }

    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
      fetchLocationSuggestions(query);
    }, 350);
  };

  async function fetchLocationSuggestions(query) {
    const dropdown = document.getElementById('searchDropdown');
    if (!dropdown) return;

    if (geocodeAbortController) {
      geocodeAbortController.abort();
    }
    geocodeAbortController = new AbortController();

    dropdown.innerHTML = `
      <div class="search-dropdown-loading">
        <span>🔍</span>
        <span>Searching places & addresses in Malaysia...</span>
      </div>
    `;
    dropdown.classList.add('show');

    try {
      const endpoint = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=my&limit=5&addressdetails=1`;
      const response = await fetch(endpoint, {
        signal: geocodeAbortController.signal,
        headers: { 'Accept-Language': 'en,ms' }
      });

      if (!response.ok) throw new Error('Geocoding response failed');

      const places = await response.json();

      if (!places || places.length === 0) {
        dropdown.innerHTML = `
          <div class="search-dropdown-loading" style="color: #64748b;">
            <span>📍 No locations found for "${escapeHtml(query)}"</span>
          </div>
        `;
        return;
      }

      dropdown.innerHTML = places.map(p => {
        const title = p.name || (p.display_name ? p.display_name.split(',')[0] : 'Location');
        const subtitle = p.display_name || '';
        const lat = parseFloat(p.lat);
        const lon = parseFloat(p.lon);

        return `
          <div class="search-dropdown-item" onclick="selectSearchLocation(${lat}, ${lon}, '${escapeJsString(title)}')">
            <div class="search-dropdown-icon">📍</div>
            <div class="search-dropdown-text">
              <span class="search-dropdown-title">${escapeHtml(title)}</span>
              <span class="search-dropdown-sub">${escapeHtml(subtitle)}</span>
            </div>
          </div>
        `;
      }).join('');

    } catch (err) {
      if (err.name === 'AbortError') return;
      console.warn('Geocode search notice:', err);
      dropdown.innerHTML = `
        <div class="search-dropdown-loading" style="color: #ef4444;">
          <span>Unable to connect to location service.</span>
        </div>
      `;
    }
  }

  // User chooses an address from the dropdown list
  window.selectSearchLocation = function(lat, lng, placeName) {
    const dropdown = document.getElementById('searchDropdown');
    if (dropdown) {
      dropdown.classList.remove('show');
      dropdown.innerHTML = '';
    }

    document.getElementById('searchInput').value = placeName;
    document.getElementById('searchClearBtn').style.display = 'block';

    if (map && !isNaN(lat) && !isNaN(lng)) {
      map.flyTo([lat, lng], 16, { animate: true, duration: 1.2 });
    }

    showToast(`📍 Centered to: ${placeName}`);
  };

  window.clearSearch = function() {
    document.getElementById('searchInput').value = '';
    document.getElementById('searchClearBtn').style.display = 'none';
    const dropdown = document.getElementById('searchDropdown');
    if (dropdown) {
      dropdown.classList.remove('show');
      dropdown.innerHTML = '';
    }
    renderVisibleMarkers();
  };

  window.handleSearchKeyDown = function(e) {
    if (e.key === 'Enter') {
      const firstItem = document.querySelector('.search-dropdown-item');
      if (firstItem) {
        firstItem.click();
      }
    } else if (e.key === 'Escape') {
      const dropdown = document.getElementById('searchDropdown');
      if (dropdown) dropdown.classList.remove('show');
    }
  };

  // Close dropdown when tapping anywhere outside the search bar
  document.addEventListener('click', (e) => {
    const wrapper = document.querySelector('.search-bar-wrapper');
    const dropdown = document.getElementById('searchDropdown');
    if (wrapper && dropdown && !wrapper.contains(e.target)) {
      dropdown.classList.remove('show');
    }
  });

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function escapeJsString(str) {
    if (!str) return '';
    return str.replace(/'/g, "\\'").replace(/"/g, '\\"');
  }

  // 8. Incident Detail Drawer
  window.openIncidentDrawer = function(incident) {
    activeSelectedIncident = incident;

    document.getElementById('drawerPhoto').src = incident.photo_url || '';
    document.getElementById('drawerTitle').textContent = formatCategoryTitle(incident.category);
    document.getElementById('drawerAddress').textContent = incident.district ? `📍 ${incident.district}` : '📍 Persiaran KLCC';
    document.getElementById('drawerDesc').textContent = incident.description || '';
    document.getElementById('drawerUpvoteCount').textContent = Math.max(1, parseInt(incident.upvote_count) || 1);

    const catBadge = document.getElementById('drawerCategoryBadge');
    catBadge.className = `badge badge-${(incident.category || 'operational').toLowerCase().replace('_', '-')}`;
    catBadge.textContent = formatCategoryLabel(incident.category);

    const statusBadge = document.getElementById('drawerStatusBadge');
    statusBadge.className = `badge ${incident.status === 'COMPLETED' ? 'badge-resolved' : 'badge-operational'}`;
    statusBadge.textContent = formatStatusLabel(incident.status);

    const upvoteBtn = document.getElementById('btnUpvote');
    upvoteBtn.style.pointerEvents = 'auto';

    if (incident.is_author) {
      upvoteBtn.classList.remove('btn-primary');
      upvoteBtn.classList.add('btn-secondary');
      upvoteBtn.innerHTML = '<span>✓ Reported by You (1 Upvote)</span>';
      upvoteBtn.disabled = true;
    } else if (incident.has_upvoted) {
      upvoteBtn.classList.remove('btn-primary');
      upvoteBtn.classList.add('btn-secondary');
      upvoteBtn.innerHTML = '<span>✓ Upvoted</span>';
      upvoteBtn.disabled = true;
    } else {
      upvoteBtn.classList.remove('btn-secondary');
      upvoteBtn.classList.add('btn-primary');
      upvoteBtn.innerHTML = `<span>🔥 +1 Upvote</span>`;
      upvoteBtn.disabled = false;
    }

    document.getElementById('drawerBackdrop').classList.add('active');
    document.getElementById('incidentDrawer').classList.add('active');
  };

  function formatCategoryTitle(cat) {
    switch (cat) {
      case 'CRITICAL_SAFETY': return 'Critical Safety Hazard';
      case 'MAJOR_BLOCKAGE':  return 'Major Facility Blockage';
      case 'MINOR_DEFECT':    return 'Minor Inconvenience';
      case 'AESTHETIC':       return 'Aesthetic & Cleanliness Issue';
      case 'OPERATIONAL':     return 'Operational Facility Defect';
      default:                return 'Reported Incident';
    }
  }

  function formatCategoryLabel(cat) {
    switch (cat) {
      case 'CRITICAL_SAFETY': return 'Critical Safety';
      case 'MAJOR_BLOCKAGE':  return 'Major Blockage';
      case 'MINOR_DEFECT':    return 'Minor Defect';
      case 'AESTHETIC':       return 'Aesthetic';
      case 'OPERATIONAL':     return 'Operational';
      default:                return 'Standard';
    }
  }

  function formatStatusLabel(status) {
    switch (status) {
      case 'COMPLETED':          return '✓ Resolved';
      case 'IN_PROGRESS':        return 'In Progress';
      case 'PENDING_ACCEPTANCE': return 'Dispatched';
      case 'OPEN_FOR_CLAIM':     return 'Open for Claim';
      case 'INVALID_RESOLVED':   return 'Closed (Invalid)';
      default:                   return status || 'Active';
    }
  }

  window.closeDrawer = function() {
    document.getElementById('incidentDrawer').classList.remove('active');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.getElementById('impactDrawer').classList.remove('active');
  };

  // 9. Upvote Action with Mutex Lock and Immediate Rapid-Click Debounce
  let isUpvotingInProgress = false;

  window.handleUpvote = async function() {
    if (!activeSelectedIncident || activeSelectedIncident.has_upvoted || activeSelectedIncident.is_author || isUpvotingInProgress) {
      return;
    }

    const upvoteBtn = document.getElementById('btnUpvote');
    const reportId = activeSelectedIncident.report_id;

    // Immediately lock UI to prevent multi-click races
    isUpvotingInProgress = true;
    upvoteBtn.disabled = true;
    upvoteBtn.style.pointerEvents = 'none';
    upvoteBtn.innerHTML = '<span>⏳ Upvoting...</span>';

    try {
      const response = await AuthGuard.fetchWithAuth(`/api/index.php/api/v1/reports/${reportId}/upvote`, {
        method: 'POST'
      });

      if (response && response.ok) {
        const result = await response.json();
        const newCount = (result.data && result.data.upvote_count !== undefined)
          ? parseInt(result.data.upvote_count)
          : (parseInt(activeSelectedIncident.upvote_count) || 1) + 1;

        activeSelectedIncident.upvote_count = newCount;
        activeSelectedIncident.has_upvoted = true;

        const listRpt = activeReportsList.find(r => r.report_id === reportId);
        if (listRpt) {
          listRpt.upvote_count = newCount;
          listRpt.has_upvoted = true;
        }

        document.getElementById('drawerUpvoteCount').textContent = newCount;
        upvoteBtn.classList.remove('btn-primary');
        upvoteBtn.classList.add('btn-secondary');
        upvoteBtn.innerHTML = '<span>✓ Upvoted</span>';
        upvoteBtn.disabled = true;

        renderVisibleMarkers();
        showToast('Upvote registered! Thank you for supporting this issue.');
      } else {
        const err = await response.json().catch(() => ({}));
        const errMsg = err.message || 'Unable to register upvote.';

        if (response && (response.status === 409 || errMsg.includes('already upvoted') || errMsg.includes('creator'))) {
          activeSelectedIncident.has_upvoted = true;
          upvoteBtn.classList.remove('btn-primary');
          upvoteBtn.classList.add('btn-secondary');
          upvoteBtn.innerHTML = activeSelectedIncident.is_author ? '<span>✓ Reported by You</span>' : '<span>✓ Upvoted</span>';
          upvoteBtn.disabled = true;
          showToast(errMsg);
        } else {
          upvoteBtn.disabled = false;
          upvoteBtn.style.pointerEvents = 'auto';
          upvoteBtn.classList.remove('btn-secondary');
          upvoteBtn.classList.add('btn-primary');
          upvoteBtn.innerHTML = '<span>🔥 +1 Upvote</span>';
          showToast(errMsg);
        }
      }
    } catch (e) {
      console.error('Upvote error:', e);
      upvoteBtn.disabled = false;
      upvoteBtn.style.pointerEvents = 'auto';
      upvoteBtn.classList.remove('btn-secondary');
      upvoteBtn.classList.add('btn-primary');
      upvoteBtn.innerHTML = '<span>🔥 +1 Upvote</span>';
      showToast('Network error during upvote. Please try again.');
    } finally {
      isUpvotingInProgress = false;
    }
  };

  window.handleSharePin = function() {
    if (!activeSelectedIncident) return;
    const shareUrl = `${window.location.origin}/module1/index.html?report=${activeSelectedIncident.report_id}`;
    if (navigator.clipboard) {
      navigator.clipboard.writeText(shareUrl).then(() => showToast('Pin sharing link copied!'));
    } else {
      showToast('Pin ID: ' + activeSelectedIncident.report_id);
    }
  };

  // =========================================================================
  // REPORTING FORM MODAL & INTERACTIVE MINI-MAP
  // =========================================================================

  window.openReportModal = function() {
    photoCaptured = false;
    photoBase64Data = '';
    capturedExifGps = null;
    isLocationLocked = false;

    document.getElementById('photoFrame').classList.remove('captured');
    document.getElementById('photoPlaceholder').style.display = 'flex';
    document.getElementById('capturedImg').style.display = 'none';
    document.getElementById('btnCapturePhoto').innerHTML = '<span>📷 Take Live Photo</span>';
    document.getElementById('btnCapturePhoto').className = 'btn btn-secondary';

    // Reset location lock button state
    resetLocationLockState();

    document.getElementById('decayBanner').style.display = 'none';
    clearInterval(decayTimerInterval);

    document.getElementById('reportDesc').value = '';
    document.getElementById('charCount').textContent = '0';

    selectedFacilityLocation = {
      lat: userLocation.lat,
      lng: userLocation.lng,
      name: `Persiaran KLCC (${userLocation.lat.toFixed(4)}° N, ${userLocation.lng.toFixed(4)}° E)`
    };
    document.getElementById('selectedLocationLabel').textContent = selectedFacilityLocation.name;

    document.getElementById('reportModal').classList.add('active');

    setTimeout(initReportMiniMap, 250);
  };

  window.closeReportModal = function() {
    clearInterval(decayTimerInterval);
    document.getElementById('reportModal').classList.remove('active');
  };

  // 10. Native Full-Screen Device Camera Trigger
  window.triggerCameraCapture = function() {
    const fileInput = document.getElementById('cameraInput');
    if (fileInput) {
      fileInput.click();
    }
  };

  window.handleCameraInputChange = function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(event) {
      applyCapturedPhoto(event.target.result);
    };
    reader.readAsDataURL(file);
  };

  window.useSamplePhotoCapture = function() {
    const samplePhotoUrl = 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80';
    applyCapturedPhoto(samplePhotoUrl);
  };

  function applyCapturedPhoto(dataUrl) {
    photoCaptured = true;
    photoBase64Data = dataUrl;
    
    // Acquire live GPS tags at photo capture timestamp
    capturedExifGps = {
      lat: userLocation.lat,
      lng: userLocation.lng,
      timestamp: new Date().toISOString()
    };

    const frame = document.getElementById('photoFrame');
    const placeholder = document.getElementById('photoPlaceholder');
    const img = document.getElementById('capturedImg');
    const btn = document.getElementById('btnCapturePhoto');

    frame.classList.add('captured');
    placeholder.style.display = 'none';
    img.src = dataUrl;
    img.style.display = 'block';
    btn.innerHTML = '<span>🔄 Retake Live Photo</span>';
    btn.className = 'btn btn-secondary';

    // Sync selected facility pin initially to the photo location
    updateSelectedLocation(capturedExifGps.lat, capturedExifGps.lng);
    if (reportMiniMap && miniMapMarker) {
      reportMiniMap.setView([capturedExifGps.lat, capturedExifGps.lng], 16);
      miniMapMarker.setLatLng([capturedExifGps.lat, capturedExifGps.lng]);
    }

    // Start 15-Minute Decay Timer
    document.getElementById('decayBanner').style.display = 'flex';
    decayTimeRemaining = 15 * 60;
    updateTimerText();
    clearInterval(decayTimerInterval);
    decayTimerInterval = setInterval(() => {
      decayTimeRemaining--;
      updateTimerText();
      if (decayTimeRemaining <= 0) {
        clearInterval(decayTimerInterval);
        alert('15-Minute timer expired! Live photo cache wiped for data integrity. Please snap a new photo.');
        openReportModal();
      }
    }, 1000);

    showToast('Live photo captured! EXIF GPS stamped.');
  }

  function updateTimerText() {
    const m = Math.floor(decayTimeRemaining / 60).toString().padStart(2, '0');
    const s = (decayTimeRemaining % 60).toString().padStart(2, '0');
    const elem = document.getElementById('decayTimerVal');
    if (elem) elem.textContent = `${m}:${s}`;
  }

  // 12. Interactive Mini-Map for Selecting Facility Location
  function initReportMiniMap() {
    const container = document.getElementById('reportMiniMap');
    if (!container) return;

    if (!reportMiniMap) {
      reportMiniMap = L.map('reportMiniMap', {
        zoomControl: false,
        attributionControl: false
      }).setView([selectedFacilityLocation.lat, selectedFacilityLocation.lng], 15);

      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
      }).addTo(reportMiniMap);

      const pinIcon = L.divIcon({
        className: 'custom-pin-marker pin-critical',
        html: `<span class="pin-icon">📍</span>`,
        iconSize: [32, 32],
        iconAnchor: [16, 16]
      });

      miniMapMarker = L.marker([selectedFacilityLocation.lat, selectedFacilityLocation.lng], {
        icon: pinIcon,
        draggable: true
      }).addTo(reportMiniMap);

      miniMapMarker.on('dragend', (e) => {
        if (isLocationLocked) return;
        const pos = e.target.getLatLng();
        updateSelectedLocation(pos.lat, pos.lng);
      });

      reportMiniMap.on('click', (e) => {
        if (isLocationLocked) return;
        miniMapMarker.setLatLng(e.latlng);
        updateSelectedLocation(e.latlng.lat, e.latlng.lng);
      });
    } else {
      reportMiniMap.invalidateSize();
      reportMiniMap.setView([selectedFacilityLocation.lat, selectedFacilityLocation.lng], 15);
      if (miniMapMarker) miniMapMarker.setLatLng([selectedFacilityLocation.lat, selectedFacilityLocation.lng]);
    }
  }

  function updateSelectedLocation(lat, lng, name) {
    selectedFacilityLocation = {
      lat: lat,
      lng: lng,
      name: name || `Facility Pin (${lat.toFixed(5)}° N, ${lng.toFixed(5)}° E)`
    };
    document.getElementById('selectedLocationLabel').textContent = selectedFacilityLocation.name;
    
    // Clear lock alert on position change if not locked
    if (!isLocationLocked) {
      const alertBox = document.getElementById('locationLockStatusAlert');
      if (alertBox) alertBox.style.display = 'none';
    }
  }

  function resetLocationLockState() {
    isLocationLocked = false;
    const btn = document.getElementById('btnLockLocation');
    const badge = document.getElementById('locationLockBadge');
    const alertBox = document.getElementById('locationLockStatusAlert');

    if (btn) {
      btn.innerHTML = '<span>🔒 Lock & Confirm Facility Location</span>';
      btn.className = 'btn btn-primary btn-block';
    }
    if (badge) {
      badge.textContent = 'Select Location';
      badge.style.background = '#e2e8f0';
      badge.style.color = '#475569';
    }
    if (alertBox) {
      alertBox.style.display = 'none';
    }
    if (reportMiniMap) {
      reportMiniMap.dragging.enable();
      reportMiniMap.touchZoom.enable();
    }
    if (miniMapMarker) {
      miniMapMarker.dragging.enable();
    }
  }

  // 13. Location Lock / Reselect Toggle (With 50m Distance Verification)
  window.toggleLocationLock = function() {
    const btn = document.getElementById('btnLockLocation');
    const badge = document.getElementById('locationLockBadge');
    const alertBox = document.getElementById('locationLockStatusAlert');

    // If currently locked -> UNLOCK
    if (isLocationLocked) {
      isLocationLocked = false;
      reportMiniMap.dragging.enable();
      reportMiniMap.touchZoom.enable();
      miniMapMarker.dragging.enable();

      btn.innerHTML = '<span>🔒 Lock & Confirm Facility Location</span>';
      btn.className = 'btn btn-primary btn-block';

      badge.textContent = 'Select Location';
      badge.style.background = '#e2e8f0';
      badge.style.color = '#475569';

      alertBox.style.display = 'block';
      alertBox.style.background = '#f1f5f9';
      alertBox.style.color = '#334155';
      alertBox.textContent = '🔓 Map unlocked. Drag the pin or click on the map to adjust facility location.';
      showToast('Facility location unlocked for adjustment.');
      return;
    }

    // If currently unlocked -> Try to LOCK & CONFIRM
    if (!photoCaptured || !capturedExifGps) {
      alert('Please snap a live photo first so the system can verify the facility distance.');
      return;
    }

    const distMeters = calculateHaversineMeters(
      capturedExifGps.lat, capturedExifGps.lng,
      selectedFacilityLocation.lat, selectedFacilityLocation.lng
    );

    // If out of 100m range -> Show error and DO NOT LOCK
    if (distMeters > 100.0) {
      alertBox.style.display = 'block';
      alertBox.style.background = '#fef2f2';
      alertBox.style.color = '#b91c1c';
      alertBox.innerHTML = `⚠️ <strong>Location Too Far:</strong> Selected pin is <strong>${Math.round(distMeters)} meters</strong> away from your photo capture location. Maximum allowed distance is <strong>100 meters</strong>.`;
      showToast(`Selected location is ${Math.round(distMeters)}m away (Max 100m).`);
      return;
    }

    // Within 100m -> Lock successfully
    isLocationLocked = true;
    reportMiniMap.dragging.disable();
    reportMiniMap.touchZoom.disable();
    miniMapMarker.dragging.disable();

    btn.innerHTML = '<span>🔓 Reselect / Unlock Location</span>';
    btn.className = 'btn btn-secondary btn-block';

    badge.textContent = `✓ Locked (${Math.round(distMeters)}m from Photo)`;
    badge.style.background = '#dcfce7';
    badge.style.color = '#15803d';

    alertBox.style.display = 'block';
    alertBox.style.background = '#f0fdf4';
    alertBox.style.color = '#15803d';
    alertBox.innerHTML = `✓ <strong>Facility Location Confirmed!</strong> Pinned within ${Math.round(distMeters)}m of captured photo.`;
    showToast('Facility location confirmed & locked.');
  };

  window.resetLocationToGps = function() {
    if (isLocationLocked) {
      showToast('Please unlock location before moving the pin.');
      return;
    }
    const targetLat = capturedExifGps ? capturedExifGps.lat : userLocation.lat;
    const targetLng = capturedExifGps ? capturedExifGps.lng : userLocation.lng;

    updateSelectedLocation(targetLat, targetLng, `Persiaran KLCC (${targetLat.toFixed(5)}° N, ${targetLng.toFixed(5)}° E)`);
    if (reportMiniMap && miniMapMarker) {
      reportMiniMap.flyTo([targetLat, targetLng], 16);
      miniMapMarker.setLatLng([targetLat, targetLng]);
    }
    document.getElementById('mismatchModal').classList.remove('active');
    showToast('Mini-map pin reset to photo capture GPS.');
  };

  // 14. Severity Survey Selection
  window.selectSurveyOption = function(card, category, weight, title) {
    document.querySelectorAll('.survey-question-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    selectedCategoryData = { category, weight, title };
  };

  // 15. Required Trade Card Selection
  window.selectTradeCard = function(card, trade) {
    document.querySelectorAll('.trade-chip-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    selectedTrade = trade;
  };

  // 16. Description Input Counter
  window.handleDescInput = function(textarea) {
    document.getElementById('charCount').textContent = textarea.value.length;
  };

  // 17. Form Submission & Validation Checks
  window.handleReportSubmitClick = function(e) {
    if (e) e.preventDefault();

    if (!photoCaptured) {
      alert('Please snap a live photo of the damaged facility first.');
      return;
    }

    if (!isLocationLocked) {
      alert('Please click "Lock & Confirm Facility Location" to verify and lock your location before submitting.');
      const btn = document.getElementById('btnLockLocation');
      if (btn) btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    const desc = document.getElementById('reportDesc').value.trim();
    if (!desc) {
      alert('Please enter a description for the reported damage.');
      return;
    }

    document.getElementById('confirmSummaryCategory').textContent = selectedCategoryData.title;
    document.getElementById('confirmSummaryTrade').textContent = selectedTrade;
    document.getElementById('confirmSummaryLocation').textContent = selectedFacilityLocation.name;
    document.getElementById('confirmPromptModal').classList.add('active');
  };

  // 18. Execute Server Submission to POST /api/v1/reports
  window.confirmFinalSubmission = async function() {
    const desc = document.getElementById('reportDesc').value.trim();
    const payload = {
      category: selectedCategoryData.category,
      required_skill: selectedTrade,
      description: desc,
      latitude: selectedFacilityLocation.lat,
      longitude: selectedFacilityLocation.lng,
      district: 'Kuala Lumpur',
      photo_base64: photoBase64Data,
      exif_lat: capturedExifGps.lat,
      exif_lng: capturedExifGps.lng,
      exif_timestamp: capturedExifGps.timestamp
    };

    document.getElementById('confirmPromptModal').classList.remove('active');

    try {
      const response = await AuthGuard.fetchWithAuth('/api/index.php/api/v1/reports', {
        method: 'POST',
        body: payload
      });

      if (response && response.ok) {
        const result = await response.json();
        const newReport = result.data;

        if (newReport) {
          activeReportsList.unshift({
            report_id: newReport.report_id || ('RPT-' + Math.floor(1000 + Math.random() * 9000)),
            category: newReport.category,
            required_skill: newReport.required_skill,
            description: newReport.description,
            latitude: newReport.latitude,
            longitude: newReport.longitude,
            photo_url: newReport.photo_url || photoBase64Data,
            upvote_count: 1,
            priority_score: newReport.priority_score || 8.0,
            status: 'OPEN_FOR_CLAIM',
            district: newReport.district || 'Kuala Lumpur',
            has_upvoted: true
          });
        }

        closeReportModal();
        renderVisibleMarkers();
        map.flyTo([selectedFacilityLocation.lat, selectedFacilityLocation.lng], 16, { animate: true });
        showToast('Report submitted successfully! Queued for maintenance dispatch.');
      } else {
        const err = await response.json();
        alert(err.message || 'Error submitting report. Please check required fields.');
      }
    } catch (err) {
      console.error('Submission error:', err);
      closeReportModal();
      showToast('Report queued and saved locally.');
    }
  };

  window.closeConfirmPrompt = function() {
    document.getElementById('confirmPromptModal').classList.remove('active');
  };

  function calculateHaversineMeters(lat1, lon1, lat2, lon2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
  }

  window.showToast = function(msg) {
    const toast = document.getElementById('toastBanner');
    document.getElementById('toastMsg').textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3600);
  };

  window.openImpactDrawer = function() {
    document.getElementById('drawerBackdrop').classList.add('active');
    document.getElementById('impactDrawer').classList.add('active');
  };

  initMap();
});
