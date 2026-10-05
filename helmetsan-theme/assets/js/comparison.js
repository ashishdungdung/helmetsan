/**
 * Helmetsan Universal Comparison Engine Controller (V4 Enterprise)
 * High-performance synchronization across:
 * - LocalStorage state ('helmetsan_compare_list')
 * - Persistent floating tray (#hs-comparison-bar / #hsComparisonTray)
 * - Catalog card overlays (.js-add-to-compare)
 * - Single PDP comparison triggers
 * - Active comparison matrix table removal & clear actions
 * - Toast feedback system
 */
document.addEventListener("DOMContentLoaded", function () {
  "use strict";

  const STORAGE_KEY = "helmetsan_compare_list";
  const MAX_COMPARE = 4;

  // Elements
  const barEl =
    document.getElementById("hs-comparison-bar") ||
    document.getElementById("hsComparisonTray");
  const listEl =
    document.getElementById("hs-comparison-list") ||
    document.getElementById("hsCompareChips");
  const countEl =
    document.getElementById("hs-comparison-count") ||
    document.getElementById("hsCompareCount");
  const viewBtn =
    document.getElementById("hs-comparison-view") ||
    document.getElementById("hsLaunchCompareBtn");

  function getCompareList() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) {
      console.error("Helmetsan compare list parse error:", e);
      return [];
    }
  }

  function saveCompareList(list) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
    } catch (e) {
      console.error("Helmetsan compare list save error:", e);
    }
    updateTrayUI();
    syncButtonsAndCheckboxes();
  }

  function showToast(message, isWarning) {
    let container = document.getElementById("hs-notification-container");
    if (!container) {
      container = document.createElement("div");
      container.id = "hs-notification-container";
      document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.className = "hs-toast";
    if (isWarning) {
      toast.style.borderColor = "rgba(239, 68, 68, 0.5)";
      toast.style.color = "#fca5a5";
    }

    const icon = isWarning ? "⚠️" : "✓";
    toast.innerHTML = `<span style="font-size:1.1em; line-height:1;">${icon}</span> <span>${message}</span>`;
    container.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
      toast.style.opacity = "1";
      toast.style.transform = "translateY(0)";
    });

    // Auto remove
    setTimeout(() => {
      toast.style.opacity = "0";
      toast.style.transform = "translateY(15px)";
      setTimeout(() => {
        if (toast.parentNode) {
          toast.parentNode.removeChild(toast);
        }
      }, 300);
    }, 2800);
  }

  function updateTrayUI() {
    const list = getCompareList();
    if (!barEl) return;

    if (list.length > 0) {
      barEl.classList.remove("is-hidden", "hs-hidden");
      barEl.classList.add("is-active");

      if (countEl) {
        countEl.textContent = list.length;
      }

      if (listEl) {
        listEl.innerHTML = list
          .map((item) => {
            const titleEsc = (item.title || "Helmet").replace(/"/g, "&quot;");
            const initials = (item.title || "H").substring(0, 2).toUpperCase();
            const thumbContent = item.image
              ? `<img src="${item.image}" alt="${titleEsc}" loading="lazy">`
              : `<span>${initials}</span>`;

            return `<div class="hs-comp-thumb hs-remove-compare-chip" data-id="${item.id}" data-slug="${item.slug || ""}" title="Click to remove ${titleEsc}" aria-label="Remove ${titleEsc}">
                        ${thumbContent}
                    </div>`;
          })
          .join("");
      }

      if (viewBtn) {
        const idsParam = list.map((item) => item.slug || item.id).join(",");
        const baseUrl = (viewBtn.getAttribute("href") || "/comparison/").split(
          "?",
        )[0];
        viewBtn.setAttribute(
          "href",
          `${baseUrl}?ids=${encodeURIComponent(idsParam)}`,
        );
      }
    } else {
      barEl.classList.remove("is-active");
      barEl.classList.add("is-hidden", "hs-hidden");
    }
  }

  function syncButtonsAndCheckboxes() {
    const list = getCompareList();
    const ids = list.map((item) => String(item.id));
    const slugs = list.map((item) => String(item.slug || item.id));

    // Sync Checkboxes
    document.querySelectorAll(".hs-compare-checkbox").forEach((cb) => {
      const cbId = String(cb.dataset.helmetId || cb.dataset.id || cb.value);
      cb.checked = ids.includes(cbId) || slugs.includes(cbId);
    });

    // Sync Buttons & Anchors (.js-add-to-compare)
    document.querySelectorAll(".js-add-to-compare").forEach((btn) => {
      // Do not alter table header remove buttons on the comparison matrix
      if (
        btn.classList.contains("js-comp-remove-helmet") ||
        btn.closest(".hs-comp-header")
      ) {
        return;
      }

      const btnId = String(btn.dataset.id || btn.dataset.helmetId || "");
      const btnSlug = String(btn.dataset.slug || "");
      const isAdded =
        (btnId && ids.includes(btnId)) || (btnSlug && slugs.includes(btnSlug));

      if (btn.tagName.toLowerCase() === "button") {
        if (isAdded) {
          btn.classList.add("is-active", "hs-btn--success");
          btn.setAttribute("aria-pressed", "true");
          btn.setAttribute("title", "Remove from comparison");
        } else {
          btn.classList.remove("is-active", "hs-btn--success");
          btn.setAttribute("aria-pressed", "false");
          btn.setAttribute("title", "Add to comparison");
        }
      } else if (btn.tagName.toLowerCase() === "a") {
        if (isAdded) {
          btn.textContent = "✓ In Compare";
          btn.classList.add("is-active", "hs-btn--success");
        } else {
          btn.textContent = "+ Add to compare";
          btn.classList.remove("is-active", "hs-btn--success");
        }
      }
    });
  }

  function toggleHelmetInCompare(helmetObj) {
    let list = getCompareList();
    const existsIndex = list.findIndex(
      (item) =>
        String(item.id) === String(helmetObj.id) ||
        (helmetObj.slug && String(item.slug) === String(helmetObj.slug)),
    );

    if (existsIndex > -1) {
      const removed = list.splice(existsIndex, 1)[0];
      saveCompareList(list);
      showToast(`Removed ${removed.title || "helmet"} from comparison`);
      return false;
    } else {
      if (list.length >= MAX_COMPARE) {
        showToast(`Comparison limit reached: max ${MAX_COMPARE} helmets`, true);
        return false;
      }
      list.push(helmetObj);
      saveCompareList(list);
      showToast(
        `Added ${helmetObj.title || "helmet"} to comparison (${list.length}/${MAX_COMPARE})`,
      );
      return true;
    }
  }

  // Global Click Delegation Listener
  document.addEventListener("click", function (e) {
    // 1. Table Header Remove Button on /comparison/ or /vs/
    const tableRemoveBtn =
      e.target.closest(".js-comp-remove-helmet") ||
      e.target.closest(".hs-comp-header-actions .js-add-to-compare");
    if (tableRemoveBtn) {
      e.preventDefault();
      const id = String(tableRemoveBtn.dataset.id || tableRemoveBtn.dataset.helmetId || "");
      const slug = String(tableRemoveBtn.dataset.slug || "");

      let list = getCompareList();
      list = list.filter(
        (item) =>
          String(item.id) !== id &&
          (!slug || String(item.slug) !== slug),
      );
      saveCompareList(list);

      // Determine all currently displayed items
      let activeItems = [];
      const url = new URL(window.location.href);
      const searchIds = (url.searchParams.get("ids") || "")
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean);

      if (searchIds.length > 0) {
        activeItems = searchIds;
      } else if (Array.isArray(window.helmetsanComparisonSlugs) && window.helmetsanComparisonSlugs.length > 0) {
        activeItems = window.helmetsanComparisonSlugs.map(String);
      } else if (Array.isArray(window.helmetsanComparisonIds) && window.helmetsanComparisonIds.length > 0) {
        activeItems = window.helmetsanComparisonIds.map(String);
      }

      const remaining = activeItems.filter(
        (val) => val !== id && val !== slug,
      );

      if (remaining.length > 0) {
        window.location.href = "/comparison/?ids=" + encodeURIComponent(remaining.join(","));
      } else {
        window.location.href = "/comparison/";
      }
      return;
    }

    // 2. Clear All Buttons (Supports all class & ID variants)
    if (
      e.target.closest("#hs-comparison-clear") ||
      e.target.closest("#hsClearCompare") ||
      e.target.closest(".js-comparison-clear") ||
      e.target.closest("#hs-comp-clear-all")
    ) {
      e.preventDefault();
      saveCompareList([]);
      showToast("Cleared comparison list");

      if (
        window.location.pathname.includes("/comparison") ||
        window.location.pathname.includes("/vs/") ||
        document.getElementById("hs-comparison-table")
      ) {
        window.location.href = "/comparison/";
      }
      return;
    }

    // 3. Chip Remove in Tray (.hs-remove-compare-chip / .hs-comp-thumb)
    const chip = e.target.closest(".hs-remove-compare-chip");
    if (chip) {
      e.preventDefault();
      const id = chip.dataset.id;
      const slug = chip.dataset.slug;

      let list = getCompareList().filter(
        (item) =>
          String(item.id) !== String(id) &&
          (!slug || String(item.slug) !== String(slug)),
      );
      saveCompareList(list);
      showToast("Removed helmet from comparison");

      // If on comparison page, update table view
      if (
        window.location.pathname.includes("/comparison") ||
        document.getElementById("hs-comparison-table")
      ) {
        const url = new URL(window.location.href);
        const currentIds = (url.searchParams.get("ids") || "")
          .split(",")
          .filter(Boolean);
        const remaining = currentIds.filter(
          (val) => val !== String(id) && val !== String(slug),
        );
        if (remaining.length > 0) {
          url.searchParams.set("ids", remaining.join(","));
          window.location.href = url.toString();
        } else {
          url.searchParams.delete("ids");
          window.location.href = url.pathname;
        }
      }
      return;
    }

    // 4. Standard Compare Button / Card Overlay (.js-add-to-compare)
    const addBtn = e.target.closest(".js-add-to-compare");
    if (addBtn) {
      const id = addBtn.dataset.id || addBtn.dataset.helmetId;
      if (!id) return;

      const title = addBtn.dataset.title || addBtn.dataset.helmetTitle || "";
      const slug = addBtn.dataset.slug || "";
      const image = addBtn.dataset.image || "";

      const list = getCompareList();
      const isAlreadyIn = list.some(
        (item) =>
          String(item.id) === String(id) ||
          (slug && String(item.slug) === String(slug)),
      );

      // If an anchor tag is clicked and already in compare, allow navigating to compare page
      if (addBtn.tagName.toLowerCase() === "a" && isAlreadyIn) {
        const idsParam = list.map((item) => item.slug || item.id).join(",");
        addBtn.setAttribute(
          "href",
          `/comparison/?ids=${encodeURIComponent(idsParam)}`,
        );
        return;
      }

      e.preventDefault();
      toggleHelmetInCompare({
        id: id,
        slug: slug,
        title: title,
        image: image,
      });
      return;
    }
  });

  // Checkbox Listener
  document.addEventListener("change", function (e) {
    if (e.target && e.target.classList.contains("hs-compare-checkbox")) {
      const cb = e.target;
      const id = cb.dataset.helmetId || cb.dataset.id || cb.value;
      const title = cb.dataset.helmetTitle || cb.dataset.title || "";
      const slug = cb.dataset.slug || "";
      const image = cb.dataset.image || "";

      toggleHelmetInCompare({
        id: id,
        slug: slug,
        title: title,
        image: image,
      });
    }
  });

  // Initial Execution
  updateTrayUI();
  syncButtonsAndCheckboxes();
});
