/**
 * EcoAsset - Module 1: Public Crowdsourced Damage Reporting Portal
 * Fullscreen Interactive Map, Sample Pins, Upvoting, Survey Form, Live Mini-Map Location Picker & EXIF Check
 */

document.addEventListener('DOMContentLoaded', () => {
  let map;
  let userLocation = { lat: 3.1578, lng: 101.7119 }; // Current User GPS location (Persiaran KLCC)
  let userMarker = null;
  let markersLayerGroup = null;
  let activeSelectedIncident = null;
  
  // Reporting Form State & Mini-Map
  let reportMiniMap = null;
  let miniMapMarker = null;
  let photoCaptured = false;
  let capturedExifGps = null;
  let selectedFacilityLocation = { lat: 3.1578, lng: 101.7119, name: 'Persiaran KLCC (3.1578° N, 101.7119° E)' };
  let decayTimeRemaining = 15 * 60;
  let decayTimerInterval = null;
  let selectedCategoryData = {
    category: 'CRITICAL_SAFETY',
    weight: 10,
    title: 'Critical Safety Hazard'
  };
  let selectedTrade = 'STRUCTURAL';

  // Places Gazetteer for Search Auto-Pan
  const placesGazetteer = [
    { name: 'KLCC / Bukit Bintang', lat: 3.1578, lng: 101.7119, zoom: 15 },
    { name: 'Titiwangsa Lake Garden', lat: 3.1750, lng: 101.7060, zoom: 15 },
    { name: 'Perdana Botanical Garden', lat: 3.1435, lng: 101.6885, zoom: 15 },
    { name: 'Merdeka Square / Dataran Merdeka', lat: 3.1490, lng: 101.6935, zoom: 15 },
    { name: 'Batu Caves Tourism Center', lat: 3.2379, lng: 101.6840, zoom: 15 }
  ];

  // Active Sample Incidents Database (Clustered in KLCC / Bukit Bintang area for immediate interactive viewing)
  const sampleIncidents = [
    {
      id: 'RPT-2401',
      category: 'CRITICAL_SAFETY',
      categoryLabel: 'Critical Safety',
      requiredSkill: 'STRUCTURAL',
      title: 'Broken handrail on pedestrian bridge',
      address: 'Persiaran KLCC, Bukit Bintang',
      description: 'Severe structural fracture on metal railing posing high falling hazard for tourists.',
      lat: 3.1579,
      lng: 101.7120,
      upvotes: 47,
      pScore: 9.2,
      status: 'IN_PROGRESS',
      statusLabel: 'In Progress',
      photoUrl: 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80',
      resolvedDaysAgo: null,
      hasUpvoted: false
    },
    {
      id: 'RPT-2389',
      category: 'OPERATIONAL',
      categoryLabel: 'Operational',
      requiredSkill: 'ELECTRICAL',
      title: 'Exposed street lighting wiring',
      address: 'Jalan Ampang, KLCC',
      description: 'Open electrical junction box with dangling live wires near park bench.',
      lat: 3.1595,
      lng: 101.7150,
      upvotes: 31,
      pScore: 8.5,
      status: 'IN_PROGRESS',
      statusLabel: 'In Progress',
      photoUrl: 'https://images.unsplash.com/photo-1555680202-c86f0e12f086?auto=format&fit=crop&w=600&q=80',
      resolvedDaysAgo: null,
      hasUpvoted: false
    },
    {
      id: 'RPT-2374',
      category: 'CRITICAL_SAFETY',
      categoryLabel: 'Critical Safety',
      requiredSkill: 'STRUCTURAL',
      title: 'Collapsed step on walkway staircase',
      address: 'Jalan Pinang, KLCC Park',
      description: 'Missing concrete slab step creating trip hazard during night hours.',
      lat: 3.1555,
      lng: 101.7140,
      upvotes: 28,
      pScore: 8.1,
      status: 'OPEN_FOR_CLAIM',
      statusLabel: 'Pending Dispatch',
      photoUrl: 'https://images.unsplash.com/photo-1584467746235-9b608306890e?auto=format&fit=crop&w=600&q=80',
      resolvedDaysAgo: null,
      hasUpvoted: false
    },
    {
      id: 'RPT-2340',
      category: 'OPERATIONAL',
      categoryLabel: 'Operational',
      requiredSkill: 'SANITATION',
      title: 'Blocked drainage pipe causing pathway flooding',
      address: 'Jalan Sultan Ismail, Bukit Bintang',
      description: 'Drainage blockage overflow spilling dirty water over primary tourist walkway.',
      lat: 3.1530,
      lng: 101.7085,
      upvotes: 38,
      pScore: 8.9,
      status: 'OPEN_FOR_CLAIM',
      statusLabel: 'Pending Dispatch',
      photoUrl: 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=600&q=80',
      resolvedDaysAgo: null,
      hasUpvoted: false
    },
    {
      id: 'RPT-2203',
      category: 'AESTHETIC',
      categoryLabel: 'Aesthetic',
      requiredSkill: 'SANITATION',
      title: 'Overfilled park waste bins & litter',
      address: 'KLCC Symphony Lake Promenade',
      description: 'Trash bins cleaned and replaced with high-capacity stainless waste units.',
      lat: 3.1565,
      lng: 101.7135,
      upvotes: 12,
      pScore: 4.8,
      status: 'COMPLETED',
      statusLabel: 'Resolved (3d ago)',
      photoUrl: 'https://images.unsplash.com/photo-1605600659908-0ef719419d41?auto=format&fit=crop&w=600&q=80',
      resolvedDaysAgo: 3,
      hasUpvoted: false
    },
    // Flagged Invalid Report (Hidden from public map)
    {
      id: 'RPT-2100',
      category: 'OPERATIONAL',
      categoryLabel: 'Operational',
      requiredSkill: 'STRUCTURAL',
      title: 'Reported crack on park bench',
      address: 'Merdeka Square',
      description: 'Inspected on-site: bench is intact and verified normal.',
      lat: 3.1480,
      lng: 101.6940,
      upvotes: 1,
      pScore: 1.0,
      status: 'INVALID_RESOLVED',
      statusLabel: 'Flagged Invalid',
      photoUrl: 'https://images.unsplash.com/photo-1519331379826-f10be5486c6f?auto=format&fit=crop&w=600&q=80',
      resolvedDaysAgo: null,
      hasUpvoted: false
    }
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
    updateVisibleMarkers();

    map.on('zoomend moveend', () => {
      updateVisibleMarkers();
    });
  }

  // 2. Render User GPS Location Pulse Marker
  function renderUserCurrentLocation() {
    const userPulseIcon = L.divIcon({
      className: 'custom-pulse-icon',
      html: `<div class="user-current-location-marker" title="Your GPS Location"></div>`,
      iconSize: [20, 20],
      iconAnchor: [10, 10]
    });

    userMarker = L.marker([userLocation.lat, userLocation.lng], { icon: userPulseIcon }).addTo(map);
  }

  // 3. Self-Locate Button Handler (Google Maps style - positioned at bottom left)
  window.handleSelfLocate = function() {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          userLocation = { lat: pos.coords.latitude, lng: pos.coords.longitude };
          if (userMarker) userMarker.setLatLng([userLocation.lat, userLocation.lng]);
          map.flyTo([userLocation.lat, userLocation.lng], 15, { animate: true, duration: 1.2 });
          showToast('GPS Location synchronized.');
        },
        () => {
          map.flyTo([userLocation.lat, userLocation.lng], 15, { animate: true, duration: 1.2 });
          showToast('Centered to GPS location (Persiaran KLCC).');
        }
      );
    } else {
      map.flyTo([userLocation.lat, userLocation.lng], 15, { animate: true, duration: 1.2 });
      showToast('Centered to GPS location.');
    }
  };

  // 4. Zoom LOD Marker Rendering (Upright Pins with Non-Blocking Top-Right Upvote Badges)
  function updateVisibleMarkers() {
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

    sampleIncidents.forEach(incident => {
      if (incident.status === 'INVALID_RESOLVED') return;
      if (incident.status === 'COMPLETED' && incident.resolvedDaysAgo > 7) return;

      if (searchQuery) {
        const matchTitle = incident.title.toLowerCase().includes(searchQuery);
        const matchDesc = incident.description.toLowerCase().includes(searchQuery);
        const matchAddr = incident.address.toLowerCase().includes(searchQuery);
        if (!matchTitle && !matchDesc && !matchAddr) return;
      }

      if (bounds.contains([incident.lat, incident.lng])) {
        let pinClass = 'pin-operational';
        let iconHtml = '⚠️';

        if (incident.category === 'CRITICAL_SAFETY') {
          pinClass = 'pin-critical';
          iconHtml = '⚡';
        } else if (incident.status === 'COMPLETED') {
          pinClass = 'pin-resolved';
          iconHtml = '✓';
        }

        const customIcon = L.divIcon({
          className: 'custom-leaflet-pin',
          html: `
            <div class="custom-pin-marker ${pinClass}" id="pin-${incident.id}">
              <span class="pin-icon">${iconHtml}</span>
              <div class="pin-upvote-counter">
                <span>🔥</span><span>${incident.upvotes}</span>
              </div>
            </div>
          `,
          iconSize: [36, 36],
          iconAnchor: [18, 18]
        });

        const marker = L.marker([incident.lat, incident.lng], { icon: customIcon });
        marker.on('click', () => openIncidentDrawer(incident));
        markersLayerGroup.addLayer(marker);
      }
    });
  }

  // 5. Search Bar Handler
  window.handleSearchInput = function() {
    const query = document.getElementById('searchInput').value.trim();
    const clearBtn = document.getElementById('searchClearBtn');

    if (query.length > 0) {
      clearBtn.style.display = 'block';
    } else {
      clearBtn.style.display = 'none';
    }

    const matchedPlace = placesGazetteer.find(p => p.name.toLowerCase().includes(query.toLowerCase()));
    if (matchedPlace && query.length >= 4) {
      map.flyTo([matchedPlace.lat, matchedPlace.lng], matchedPlace.zoom, { animate: true, duration: 1.0 });
    }

    updateVisibleMarkers();
  };

  window.clearSearch = function() {
    document.getElementById('searchInput').value = '';
    document.getElementById('searchClearBtn').style.display = 'none';
    updateVisibleMarkers();
  };

  // 6. Incident Detail Drawer (Clean View without Priority Score for Normal Users)
  window.openIncidentDrawer = function(incident) {
    activeSelectedIncident = incident;

    document.getElementById('drawerPhoto').src = incident.photoUrl;
    document.getElementById('drawerTitle').textContent = incident.title;
    document.getElementById('drawerAddress').textContent = incident.address;
    document.getElementById('drawerDesc').textContent = incident.description;
    document.getElementById('drawerUpvoteCount').textContent = incident.upvotes;

    const catBadge = document.getElementById('drawerCategoryBadge');
    catBadge.className = `badge badge-${incident.category.toLowerCase().replace('_', '-')}`;
    catBadge.textContent = incident.categoryLabel;

    const statusBadge = document.getElementById('drawerStatusBadge');
    statusBadge.className = `badge ${incident.status === 'COMPLETED' ? 'badge-resolved' : 'badge-operational'}`;
    statusBadge.textContent = incident.statusLabel;

    const upvoteBtn = document.getElementById('btnUpvote');
    if (incident.hasUpvoted) {
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

  window.closeDrawer = function() {
    document.getElementById('incidentDrawer').classList.remove('active');
    document.getElementById('drawerBackdrop').classList.remove('active');
    document.getElementById('impactDrawer').classList.remove('active');
  };

  // 7. Upvote Action (User-friendly toast without internal priority score formula details)
  window.handleUpvote = function() {
    if (!activeSelectedIncident || activeSelectedIncident.hasUpvoted) return;

    activeSelectedIncident.upvotes += 1;
    activeSelectedIncident.hasUpvoted = true;
    activeSelectedIncident.pScore = (parseFloat(activeSelectedIncident.pScore) + 0.3).toFixed(1);

    document.getElementById('drawerUpvoteCount').textContent = activeSelectedIncident.upvotes;

    const upvoteBtn = document.getElementById('btnUpvote');
    upvoteBtn.classList.remove('btn-primary');
    upvoteBtn.classList.add('btn-secondary');
    upvoteBtn.innerHTML = '<span>✓ Upvoted</span>';
    upvoteBtn.disabled = true;

    updateVisibleMarkers();
    showToast('Upvote registered! Thank you for supporting this issue.');
  };

  window.handleSharePin = function() {
    if (!activeSelectedIncident) return;
    const shareUrl = `${window.location.origin}/module1/?report=${activeSelectedIncident.id}`;
    if (navigator.clipboard) {
      navigator.clipboard.writeText(shareUrl).then(() => showToast('Pin sharing link copied!'));
    } else {
      showToast('Link generated for ' + activeSelectedIncident.id);
    }
  };

  // =========================================================================
  // REPORTING FORM MODAL & INTERACTIVE MINI-MAP LOCATION PICKER
  // =========================================================================

  window.openReportModal = function() {
    photoCaptured = false;
    capturedExifGps = null;
    document.getElementById('photoFrame').classList.remove('captured');
    document.getElementById('photoPlaceholder').style.display = 'flex';
    document.getElementById('capturedImg').style.display = 'none';
    document.getElementById('btnCapturePhoto').innerHTML = '<span>📷 Take Live Photo</span>';
    
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
        const pos = e.target.getLatLng();
        updateSelectedLocation(pos.lat, pos.lng);
      });

      reportMiniMap.on('click', (e) => {
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
      name: name || `Custom Pin (${lat.toFixed(4)}° N, ${lng.toFixed(4)}° E)`
    };
    document.getElementById('selectedLocationLabel').textContent = selectedFacilityLocation.name;
  }

  window.resetLocationToGps = function() {
    updateSelectedLocation(userLocation.lat, userLocation.lng, `Persiaran KLCC (${userLocation.lat.toFixed(4)}° N, ${userLocation.lng.toFixed(4)}° E)`);
    if (reportMiniMap && miniMapMarker) {
      reportMiniMap.flyTo([userLocation.lat, userLocation.lng], 15);
      miniMapMarker.setLatLng([userLocation.lat, userLocation.lng]);
    }
    document.getElementById('mismatchModal').classList.remove('active');
    showToast('Mini-map pin reset to GPS location.');
  };

  window.simulateFarLocationPick = function() {
    const farLat = 3.1488;
    const farLng = 101.7133;
    updateSelectedLocation(farLat, farLng, 'Pavilion KL Entrance (1.1 km away from GPS)');
    if (reportMiniMap && miniMapMarker) {
      reportMiniMap.flyTo([farLat, farLng], 15);
      miniMapMarker.setLatLng([farLat, farLng]);
    }
    showToast('Selected pin moved >50m away to test mismatch rule.');
  };

  // 8.1 Photo Capture Simulation
  window.handlePhotoCaptureButton = function() {
    photoCaptured = true;
    capturedExifGps = { lat: userLocation.lat, lng: userLocation.lng, timestamp: new Date().toISOString() };

    const frame = document.getElementById('photoFrame');
    const placeholder = document.getElementById('photoPlaceholder');
    const img = document.getElementById('capturedImg');
    const btn = document.getElementById('btnCapturePhoto');

    frame.classList.add('captured');
    placeholder.style.display = 'none';
    img.src = 'https://images.unsplash.com/photo-1590486803833-1c5dc8ddd4c8?auto=format&fit=crop&w=600&q=80';
    img.style.display = 'block';
    btn.innerHTML = '<span>🔄 Retake Photo</span>';

    document.getElementById('decayBanner').style.display = 'flex';
    decayTimeRemaining = 15 * 60;
    updateTimerText();
    clearInterval(decayTimerInterval);
    decayTimerInterval = setInterval(() => {
      decayTimeRemaining--;
      updateTimerText();
      if (decayTimeRemaining <= 0) {
        clearInterval(decayTimerInterval);
        alert('15-Minute timer expired! Photo cache wiped. Please recapture photo.');
        openReportModal();
      }
    }, 1000);

    showToast('Photo captured live! EXIF GPS tags stamped.');
  };

  function updateTimerText() {
    const m = Math.floor(decayTimeRemaining / 60).toString().padStart(2, '0');
    const s = (decayTimeRemaining % 60).toString().padStart(2, '0');
    const elem = document.getElementById('decayTimerVal');
    if (elem) elem.textContent = `${m}:${s}`;
  }

  // 8.2 Survey Category Selection
  window.selectSurveyOption = function(card, category, weight, title) {
    document.querySelectorAll('.survey-question-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    selectedCategoryData = { category, weight, title };
  };

  // 8.3 Required Trade Card Selection
  window.selectTradeCard = function(card, trade) {
    document.querySelectorAll('.trade-chip-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    selectedTrade = trade;
  };

  // 8.4 Description Input Counter
  window.handleDescInput = function(textarea) {
    document.getElementById('charCount').textContent = textarea.value.length;
  };

  // 8.5 Form Submission & 50m Distance Check
  window.handleReportSubmitClick = function(e) {
    if (e) e.preventDefault();

    if (!photoCaptured) {
      alert('Please take a live photo of the damaged facility first.');
      return;
    }

    const desc = document.getElementById('reportDesc').value.trim();
    if (!desc) {
      alert('Please enter a description for the reported damage.');
      return;
    }

    const distMeters = calculateHaversineMeters(
      capturedExifGps.lat, capturedExifGps.lng,
      selectedFacilityLocation.lat, selectedFacilityLocation.lng
    );

    if (distMeters > 50.0) {
      document.getElementById('mismatchDistText').textContent = `${Math.round(distMeters)} meters away`;
      document.getElementById('mismatchModal').classList.add('active');
      return;
    }

    document.getElementById('confirmSummaryCategory').textContent = selectedCategoryData.title;
    document.getElementById('confirmSummaryTrade').textContent = selectedTrade;
    document.getElementById('confirmSummaryLocation').textContent = selectedFacilityLocation.name;
    document.getElementById('confirmPromptModal').classList.add('active');
  };

  window.confirmFinalSubmission = function() {
    document.getElementById('confirmPromptModal').classList.remove('active');
    closeReportModal();
    showToast('Report submitted successfully! Queued into maintenance backlog.');
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
