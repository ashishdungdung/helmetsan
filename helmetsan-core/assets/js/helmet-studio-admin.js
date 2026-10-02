/**
 * Helmetsan 5-Shot Helmet Image Studio Admin Controller
 *
 * Client-driven asynchronous orchestrator for FLUX.1 generation,
 * WebP asset management, and Moonshot AI Kimi-K3 visual quality auditing.
 */

(function () {
  'use strict';

  const HS = window.HELMETSAN_STUDIO || {};
  let currentHelmet = null;
  let isBatchGenerating = false;

  document.addEventListener('DOMContentLoaded', () => {
    initFilters();
    initSearch();
    initModalEvents();
  });

  // -------------------------------------------------------------
  // Filter & Search Handling
  // -------------------------------------------------------------
  function initFilters() {
    const filterBtns = document.querySelectorAll('.hs-filter-btn');
    const rows = document.querySelectorAll('.hs-helmet-row');

    filterBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const filter = btn.dataset.filter;
        rows.forEach(row => {
          const approved = parseInt(row.dataset.approved || '0', 10);
          const isAudited = row.dataset.audited === 'true';

          let show = true;
          if (filter === 'incomplete' && approved >= 5) show = false;
          if (filter === 'complete' && approved < 5) show = false;
          if (filter === 'audited' && !isAudited) show = false;

          row.style.display = show ? '' : 'none';
        });
      });
    });
  }

  function initSearch() {
    const searchInput = document.getElementById('hs-search-helmets');
    if (!searchInput) return;

    searchInput.addEventListener('input', e => {
      const term = e.target.value.toLowerCase().trim();
      const rows = document.querySelectorAll('.hs-helmet-row');

      rows.forEach(row => {
        const title = (row.dataset.title || '').toLowerCase();
        const id = (row.dataset.id || '').toLowerCase();
        const matches = title.includes(term) || id.includes(term);
        row.style.display = matches ? '' : 'none';
      });
    });
  }

  // -------------------------------------------------------------
  // Studio Modal Orchestration
  // -------------------------------------------------------------
  function initModalEvents() {
    // Open modal buttons
    document.querySelectorAll('.hs-open-studio-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const helmetId = btn.dataset.helmetId;
        const title = btn.dataset.title;
        const brand = btn.dataset.brand;
        const shell = btn.dataset.shell;

        openStudioModal({
          id: helmetId,
          title: title,
          brand: brand,
          shell: shell,
        });
      });
    });

    // Close buttons
    document.querySelectorAll('.hs-modal-close, .hs-close-modal-trigger').forEach(el => {
      el.addEventListener('click', () => {
        closeAllModals();
      });
    });

    // Close on escape
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeAllModals();
    });

    // Batch generate all missing shots
    const batchBtn = document.getElementById('hs-btn-generate-all-missing');
    if (batchBtn) {
      batchBtn.addEventListener('click', handleBatchGenerateMissing);
    }
  }

  function closeAllModals() {
    if (isBatchGenerating) {
      if (!confirm('Generation is in progress. Are you sure you want to close?')) return;
      isBatchGenerating = false;
    }
    document.querySelectorAll('.hs-modal-backdrop').forEach(m => m.classList.add('hidden'));
  }

  function logConsole(msg, type = 'info') {
    const consoleEl = document.getElementById('hs-studio-console');
    if (!consoleEl) return;

    const time = new Date().toLocaleTimeString();
    const line = document.createElement('div');
    line.className = `log-line log-${type}`;
    line.innerHTML = `<span class="log-time">[${time}]</span> ${escapeHtml(msg)}`;
    consoleEl.appendChild(line);
    consoleEl.scrollTop = consoleEl.scrollHeight;
  }

  // Open & Load Studio Drawer
  async function openStudioModal(helmet) {
    currentHelmet = helmet;
    const modal = document.getElementById('hs-studio-modal');
    if (!modal) return;

    // Reset details
    document.getElementById('hs-modal-helmet-title').textContent = helmet.title;
    document.getElementById('hs-modal-helmet-subtitle').textContent = `${helmet.brand || 'Helmetsan'} • ID: ${helmet.id}`;
    document.getElementById('hs-studio-console').innerHTML = '';

    modal.classList.remove('hidden');
    logConsole(`Cockpit initialized for helmet "${helmet.title}" (ID: ${helmet.id})`, 'info');

    await reloadHelmetData(helmet.id);
  }

  async function reloadHelmetData(helmetId) {
    logConsole(`Fetching 5-shot studio coverage and assets...`, 'info');

    try {
      const res = await fetch(`${HS.restUrl}/helmets/${helmetId}/images`, {
        headers: {
          'X-WP-Nonce': HS.nonce,
        },
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      renderShotsGrid(data.coverage, data.images);
    } catch (err) {
      logConsole(`Error loading helmet images: ${err.message}`, 'error');
    }
  }

  // Render 5-Shot Grid
  function renderShotsGrid(coverage, images) {
    const grid = document.getElementById('hs-shots-container');
    if (!grid) return;

    const canonicalShots = HS.canonicalShots || {
      front_hero: { label: '3/4 Front Isometric Hero', desc: 'Visor cracked 10mm, Pinlock pins, softbox 45°' },
      side_profile: { label: 'Lateral Side Profile', desc: 'Perpendicular 90°, spoiler contour, visor baseplate' },
      rear_exhaust: { label: 'Rear Exhaust & Diffuser', desc: 'Diffuser channels, ECE 22.06 / DOT decal' },
      interior_macro: { label: 'Macro Interior & Retention', desc: 'EPS channels, emergency tabs, D-ring' },
      cockpit_context: { label: 'Motorcycle Cockpit Pairing', desc: 'Sportbike fuel tank mount, daylight bokeh' },
    };

    grid.innerHTML = '';
    const shotsMap = coverage.shots || {};

    // Update Progress Bar
    const approved = coverage.approved || 0;
    const pct = Math.round((approved / 5) * 100);
    const fillEl = document.getElementById('hs-coverage-progress-fill');
    const labelEl = document.getElementById('hs-coverage-progress-label');
    if (fillEl) fillEl.style.width = `${pct}%`;
    if (labelEl) labelEl.textContent = `${approved}/5 Shots Approved (${pct}%)`;

    // Render Each Card
    Object.keys(canonicalShots).forEach(shotType => {
      const spec = canonicalShots[shotType];
      const shotData = shotsMap[shotType];
      const card = document.createElement('div');
      card.className = 'hs-shot-card';
      card.id = `hs-shot-card-${shotType}`;

      const hasImage = Boolean(shotData && (shotData.url || shotData.thumbnail_url));
      const imageUrl = hasImage ? (shotData.thumbnail_url || shotData.url) : '';
      const status = shotData ? (shotData.validation_status || 'queued') : 'missing';
      const isPrimary = Boolean(shotData && shotData.is_primary);

      // Audit badge info
      let auditBadgeHtml = '';
      if (hasImage) {
        if (status === 'audit_passed' || status === 'verified' || status === 'approved') {
          auditBadgeHtml = '<span class="hs-audit-badge hs-audit-pass">✓ Kimi Pass</span>';
        } else if (status === 'audit_failed') {
          auditBadgeHtml = '<span class="hs-audit-badge hs-audit-fail">✕ Defect Flag</span>';
        } else {
          auditBadgeHtml = '<span class="hs-audit-badge hs-audit-pending">⏳ Audit Pending</span>';
        }
      }

      card.innerHTML = `
        <div class="hs-shot-preview">
          ${hasImage 
            ? `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(spec.label)}" loading="lazy" />`
            : `<div class="hs-shot-empty">
                 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                 <div>Missing Shot</div>
               </div>`
          }
        </div>
        <div class="hs-shot-content">
          <h4>${escapeHtml(spec.label)}</h4>
          <div class="spec-desc">${escapeHtml(spec.desc)}</div>
          <div class="hs-shot-meta-row">
            ${isPrimary ? '<span style="color:#0284c7;font-weight:700;">★ Primary Hero</span>' : '<span></span>'}
            ${auditBadgeHtml}
          </div>
          <div class="hs-shot-actions">
            <div style="display:flex;gap:0.3rem;margin-bottom:0.3rem;">
              <select class="hs-select-model" id="hs-model-${shotType}" style="font-size:0.75rem;padding:0.2rem;flex:1;">
                <option value="flux-dev" selected>FLUX.1-dev (12B SOTA — Fast & Stable)</option>
                <option value="flux-schnell">FLUX.1-schnell (Distilled Preview)</option>
              </select>
              <button type="button" class="button button-small button-primary hs-btn-generate-shot" data-shot="${shotType}">
                ⚡ ${hasImage ? 'Regenerate' : 'Generate'}
              </button>
            </div>
            ${hasImage ? `
              <div style="display:flex;gap:0.3rem;">
                <button type="button" class="button button-small hs-btn-audit-shot" data-shot="${shotType}" data-image-id="${escapeHtml(shotData.id)}" data-image-url="${escapeHtml(shotData.url)}">
                  🔍 Kimi Audit
                </button>
                ${!isPrimary ? `
                  <button type="button" class="button button-small hs-btn-primary-shot" data-shot="${shotType}" data-image-id="${escapeHtml(shotData.id)}">
                    ★ Set Hero
                  </button>
                ` : ''}
                <button type="button" class="button button-small hs-btn-delete-shot" data-image-id="${escapeHtml(shotData.id)}" style="color:#ef4444;">
                  🗑️
                </button>
              </div>
            ` : ''}
          </div>
        </div>
      `;

      grid.appendChild(card);
    });

    attachCardActionHandlers();
  }

  function attachCardActionHandlers() {
    // Generate single shot
    document.querySelectorAll('.hs-btn-generate-shot').forEach(btn => {
      btn.addEventListener('click', async () => {
        const shotType = btn.dataset.shot;
        const modelSelect = document.getElementById(`hs-model-${shotType}`);
        const model = modelSelect ? modelSelect.value : 'flux-dev';
        await generateSingleShot(currentHelmet.id, shotType, model, btn);
      });
    });

    // Kimi Audit Trigger
    document.querySelectorAll('.hs-btn-audit-shot').forEach(btn => {
      btn.addEventListener('click', async () => {
        const shotType = btn.dataset.shot;
        const imageId = btn.dataset.imageId;
        const imageUrl = btn.dataset.imageUrl;
        await triggerKimiAudit(currentHelmet.id, imageId, imageUrl, shotType, btn);
      });
    });

    // Set Primary Hero
    document.querySelectorAll('.hs-btn-primary-shot').forEach(btn => {
      btn.addEventListener('click', async () => {
        const imageId = btn.dataset.imageId;
        await setPrimaryHero(currentHelmet.id, imageId);
      });
    });

    // Delete Shot
    document.querySelectorAll('.hs-btn-delete-shot').forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!confirm('Are you sure you want to delete this shot asset?')) return;
        const imageId = btn.dataset.imageId;
        await deleteShot(imageId);
      });
    });
  }

  // -------------------------------------------------------------
  // Safe JSON / Error Response Parser
  // -------------------------------------------------------------
  async function parseJsonResponse(res) {
    const contentType = res.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      return await res.json();
    }
    const text = await res.text();
    const isHtml = text.trim().startsWith('<');
    const titleMatch = isHtml ? text.match(/<title>(.*?)<\/title>/i) : null;
    const title = titleMatch ? titleMatch[1].trim() : (isHtml ? 'Gateway Timeout / Origin Error' : text.substring(0, 150));
    throw new Error(`Server returned HTTP ${res.status} non-JSON response: ${title}`);
  }

  // -------------------------------------------------------------
  // Single Shot Generation Pipeline
  // -------------------------------------------------------------
  async function generateSingleShot(helmetId, shotType, model, triggerBtn) {
    const card = document.getElementById(`hs-shot-card-${shotType}`);
    if (card) card.classList.add('generating');

    const originalText = triggerBtn ? triggerBtn.innerHTML : '';
    if (triggerBtn) {
      triggerBtn.disabled = true;
      triggerBtn.innerHTML = '⏳ Generating...';
    }

    logConsole(`[${shotType.toUpperCase()}] Starting FLUX.1 generation via NVIDIA NIM (${model})...`, 'info');

    try {
      const res = await fetch(`${HS.restUrl}/helmets/${helmetId}/images/generate-shot`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': HS.nonce,
        },
        body: JSON.stringify({
          shot_type: shotType,
          model: model,
          auto_audit: true,
        }),
      });

      const data = await parseJsonResponse(res);
      if (!res.ok || !data.success) {
        throw new Error(data.message || `Generation failed (HTTP ${res.status})`);
      }

      logConsole(`[${shotType.toUpperCase()}] ✅ Generated successfully! Model: ${data.model || model}. pHash: ${data.phash}`, 'success');

      if (data.audit) {
        const decision = data.audit.decision || 'human_review';
        const score = data.audit.quality_score || (decision === 'pass' ? 95 : 60);
        logConsole(`[${shotType.toUpperCase()}] Kimi-K3 Audit result: ${decision.toUpperCase()} (Score: ${score}/100)`, decision === 'pass' ? 'success' : 'warn');
      }

      // Reload grid
      await reloadHelmetData(helmetId);
    } catch (err) {
      logConsole(`[${shotType.toUpperCase()}] ❌ Error: ${err.message}`, 'error');
      alert(`Generation failed for ${shotType}: ${err.message}`);
    } finally {
      if (card) card.classList.remove('generating');
      if (triggerBtn) {
        triggerBtn.disabled = false;
        triggerBtn.innerHTML = originalText;
      }
    }
  }

  // -------------------------------------------------------------
  // Batch Sequential Generation (Client-Driven Swarm)
  // -------------------------------------------------------------
  async function handleBatchGenerateMissing() {
    if (!currentHelmet) return;

    const btn = document.getElementById('hs-btn-generate-all-missing');
    btn.disabled = true;
    btn.innerHTML = '⏳ Processing Swarm...';
    isBatchGenerating = true;

    logConsole(`🚀 [SWARM QUEUE] Beginning sequential generation of missing shots...`, 'info');

    try {
      // 1. Fetch current status to find missing
      const statusRes = await fetch(`${HS.restUrl}/helmets/${currentHelmet.id}/images`, {
        headers: { 'X-WP-Nonce': HS.nonce },
      });
      const statusData = await parseJsonResponse(statusRes);
      const missing = statusData.coverage?.missing || [];

      if (missing.length === 0) {
        logConsole(`All 5 shots are already complete for this helmet!`, 'success');
        alert('All 5 canonical shots are already generated for this helmet.');
        return;
      }

      logConsole(`Found ${missing.length} missing shot(s) to process: ${missing.join(', ')}`, 'info');

      // Process one by one with stable FLUX.1-dev to avoid server timeouts
      for (let i = 0; i < missing.length; i++) {
        if (!isBatchGenerating) {
          logConsole('Batch generation aborted by operator.', 'warn');
          break;
        }

        const shotType = missing[i];
        logConsole(`👉 Step ${i + 1}/${missing.length}: Generating ${shotType}...`, 'info');
        await generateSingleShot(currentHelmet.id, shotType, 'flux-dev', null);
      }

      logConsole(`🎉 [SWARM QUEUE] Batch generation cycle complete!`, 'success');
    } catch (err) {
      logConsole(`Batch error: ${err.message}`, 'error');
    } finally {
      isBatchGenerating = false;
      btn.disabled = false;
      btn.innerHTML = '⚡ Generate All Missing 5 Shots';
    }
  }

  // -------------------------------------------------------------
  // Moonshot AI Kimi-K3 Visual Quality Audit
  // -------------------------------------------------------------
  async function triggerKimiAudit(helmetId, imageId, imageUrl, shotType, btn) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Inspecting...';

    logConsole(`[KIMI-K3 AUDIT] Submitting multimodal inspection request for image ${imageId}...`, 'info');

    try {
      const res = await fetch(`${HS.restUrl}/helmets/${helmetId}/images/audit`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': HS.nonce,
        },
        body: JSON.stringify({
          image_id: imageId,
          url: imageUrl,
          shot_type: shotType,
        }),
      });

      const data = await parseJsonResponse(res);
      if (!res.ok || !data.success) {
        throw new Error(data.message || `Audit failed (HTTP ${res.status})`);
      }

      const audit = data.audit || {};
      logConsole(`[KIMI-K3 AUDIT] Inspection complete! Decision: ${audit.decision?.toUpperCase()}`, 'success');

      openAuditReportModal(shotType, audit);
      await reloadHelmetData(helmetId);
    } catch (err) {
      logConsole(`[KIMI-K3 AUDIT] Error: ${err.message}`, 'error');
      alert(`Kimi-K3 Audit failed: ${err.message}`);
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  }

  function openAuditReportModal(shotType, audit) {
    const modal = document.getElementById('hs-audit-modal');
    if (!modal) return;

    const score = audit.quality_score || (audit.decision === 'pass' ? 95 : 50);
    const decision = audit.decision || 'human_review';

    const gaugeEl = document.getElementById('hs-audit-gauge');
    if (gaugeEl) {
      gaugeEl.textContent = `${score}`;
      gaugeEl.className = `hs-audit-gauge hs-gauge-${decision === 'pass' ? 'pass' : (decision === 'fail' ? 'fail' : 'review')}`;
    }

    document.getElementById('hs-audit-decision-text').textContent = 
      decision === 'pass' ? 'APPROVED & VERIFIED' : (decision === 'fail' ? 'DEFECT FLAGGED' : 'REQUIRES HUMAN REVIEW');

    const checks = audit.checks || {};
    const checksList = document.getElementById('hs-audit-checks-list');
    if (checksList) {
      checksList.innerHTML = `
        <li>
          <span>Specular Highlights & Glare Controlled</span>
          <span class="${checks.specular_blowout_controlled !== false ? 'hs-check-pass' : 'hs-check-fail'}">
            ${checks.specular_blowout_controlled !== false ? '✓ Passed' : '✕ Reflection Clipped'}
          </span>
        </li>
        <li>
          <span>Framing & Camera Angle Accurate (${escapeHtml(shotType)})</span>
          <span class="${checks.framing_and_angle_accurate !== false ? 'hs-check-pass' : 'hs-check-fail'}">
            ${checks.framing_and_angle_accurate !== false ? '✓ Accurate' : '✕ Angle Drift'}
          </span>
        </li>
        <li>
          <span>Edge Segmentation & Contours Clean</span>
          <span class="${checks.edge_segmentation_clean !== false ? 'hs-check-pass' : 'hs-check-fail'}">
            ${checks.edge_segmentation_clean !== false ? '✓ Clean' : '✕ Halo Artifact'}
          </span>
        </li>
        <li>
          <span>Color & Material Finish Photorealistic</span>
          <span class="${checks.color_consistency_valid !== false ? 'hs-check-pass' : 'hs-check-fail'}">
            ${checks.color_consistency_valid !== false ? '✓ Valid' : '✕ Plastic/Warped'}
          </span>
        </li>
        <li>
          <span>Visor Aperture & Pivot Plausible</span>
          <span class="${checks.visor_aperture_plausible !== false ? 'hs-check-pass' : 'hs-check-fail'}">
            ${checks.visor_aperture_plausible !== false ? '✓ Plausible' : '✕ Malformed'}
          </span>
        </li>
        <li>
          <span>Retention System Plausibility</span>
          <span class="${checks.retention_strap_valid !== false ? 'hs-check-pass' : 'hs-check-fail'}">
            ${checks.retention_strap_valid !== false ? '✓ Accurate' : '✕ Inconsistent'}
          </span>
        </li>
      `;
    }

    const recEl = document.getElementById('hs-audit-recommendations');
    if (recEl) {
      recEl.textContent = audit.recommendations || audit.warnings?.join(', ') || 'No defects detected. Geometry and optics meet catalog standards.';
    }

    modal.classList.remove('hidden');
  }

  // -------------------------------------------------------------
  // Primary Hero & Deletion Actions
  // -------------------------------------------------------------
  async function setPrimaryHero(helmetId, imageId) {
    try {
      const res = await fetch(`${HS.restUrl}/helmets/${helmetId}/images/set-primary`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': HS.nonce,
        },
        body: JSON.stringify({ image_id: imageId }),
      });

      const data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.message || 'Failed to set primary');

      logConsole(`Set image ${imageId} as primary hero for catalog listing.`, 'success');
      await reloadHelmetData(helmetId);
    } catch (err) {
      logConsole(`Error setting primary: ${err.message}`, 'error');
      alert(`Failed to set primary: ${err.message}`);
    }
  }

  async function deleteShot(imageId) {
    try {
      const res = await fetch(`${HS.restUrl}/helmets/images/${imageId}`, {
        method: 'DELETE',
        headers: { 'X-WP-Nonce': HS.nonce },
      });

      const data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.message || 'Failed to delete');

      logConsole(`Deleted image record ${imageId}.`, 'warn');
      if (currentHelmet) await reloadHelmetData(currentHelmet.id);
    } catch (err) {
      logConsole(`Error deleting: ${err.message}`, 'error');
      alert(`Delete failed: ${err.message}`);
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
})();
