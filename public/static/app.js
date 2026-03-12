function getCtx() {
  const ctx = window.APP_CONTEXT || {};
  const params = new URLSearchParams(window.location.search);

  // если мы на странице /crm-tab — это CRM режим железно
  const isCrmPath = window.location.pathname === "/crm-tab";

  return {
    ...ctx,
    mode: ctx.mode || (isCrmPath ? "crm" : "settings"),
    tabId: ctx.tabId || params.get("tab_id") || null,
  };
}

let state = {
  portalId: null,
  entityTypeId: null,
  entities: [],
  tabs: [],
  activeTabId: null,
};
let sort = {
  active: false,
  draggedId: null,
  fromIndex: -1,
  originNextEl: null,

  ghost: null,
  placeholder: null,
  offsetX: 0,
  offsetY: 0,
  ghostH: 0,

  raf: 0,
  lastX: 0,
  lastY: 0,
  lastIdx: -1,
  tabRects: [],
  tabEls: [],
};
const elEntities = document.getElementById("entities");
const elTabs = document.getElementById("tabs");
const elEntityTitle = document.getElementById("entityTitle");
const elLinkInput = document.getElementById("linkInput");
const elPreview = document.getElementById("previewBox");
const elStatus = document.getElementById("status");
let autosaveTimer = null;
let lastSavedValue = null;

function getPortalId() {
  const pid = state.portalId || (window.APP_CONTEXT && window.APP_CONTEXT.portalId) || null;
  if (!pid) throw new Error("portalId is not set (BX24.getAuth() returned empty member_id)");
  return pid;
}

function withPortal(url) {
  const p = encodeURIComponent(getPortalId());
  return url.includes("?") ? `${url}&portal_id=${p}` : `${url}?portal_id=${p}`;
}

async function api(url, options = {}) {
  const finalUrl = withPortal(url);
  const method = (options.method || "GET").toUpperCase();
  console.log("API CALL:", method, finalUrl);

  const r = await fetch(finalUrl, options);
  if (!r.ok) throw new Error(await r.text());
  return r.json();
}

function ensurePlaceholder(width) {
  if (!sort.placeholder) {
    sort.placeholder = document.createElement("div");
    sort.placeholder.className = "tab-placeholder";
  }
  sort.placeholder.style.width = `${Math.max(80, Math.round(width))}px`;
  return sort.placeholder;
}

function createGhost(fromEl) {
  const g = fromEl.cloneNode(true);
  g.classList.add("sort-ghost");
  document.body.appendChild(g);
  return g;
}

function moveGhost(clientX, clientY) {
  if (!sort.ghost || !elTabs) return;

  const strip = elTabs.getBoundingClientRect();

  // X — как раньше
  const x = clientX - sort.offsetX;

  // Y — рядом с курсором, но зажат в пределах полосы табов
  const minY = strip.top + 2;
  const maxY = strip.bottom - sort.ghostH - 2;
  const yRaw = clientY - sort.offsetY;
  const y = Math.max(minY, Math.min(maxY, yRaw));

  sort.ghost.style.transform = `translate(${x}px, ${y}px)`;
}

function getInsertIndexByX(clientX) {
  // используем кэшированные rect'ы
  for (let i = 0; i < sort.tabRects.length; i++) {
    const r = sort.tabRects[i];
    const threshold = r.left + r.width * 0.65;
    if (clientX < threshold) return i;
  }
  return sort.tabRects.length;
}

function startSort(draggedId, handleEl, ev) {
  if (!elTabs) return;
  const tabEl = handleEl.closest(".tab");
  if (!tabEl) return;
      // следующий .tab после исходного таба (может быть null, если он последний)
    sort.originNextEl = tabEl.nextElementSibling && tabEl.nextElementSibling.classList.contains("tab")
      ? tabEl.nextElementSibling
      : null;

    sort.fromIndex = state.tabs.findIndex(t => t.id === draggedId);

  sort.active = true;
  sort.draggedId = draggedId;
  sort.fromIndex = state.tabs.findIndex(t => t.id === draggedId);

  const rect = tabEl.getBoundingClientRect();
  sort.ghostH = rect.height;
  sort.offsetX = ev.clientX - rect.left;
  sort.offsetY = ev.clientY - rect.top;

  tabEl.classList.add("tab--dragging");
  // ✅ кэш табов и их rect на старт сортировки (вместо вычисления на каждый move)
    sort.tabEls = Array.from(elTabs.querySelectorAll(".tab")).filter(el => el !== tabEl);
    sort.tabRects = sort.tabEls.map(el => el.getBoundingClientRect());
    sort.lastIdx = -1;

  sort.ghost = createGhost(tabEl);
  sort.ghost.style.width = `${Math.round(rect.width)}px`;
  sort.ghost.style.height = `${Math.round(rect.height)}px`;
  moveGhost(ev.clientX, ev.clientY);

  const ph = ensurePlaceholder(rect.width);
  elTabs.insertBefore(ph, tabEl);

  handleEl.setPointerCapture(ev.pointerId);

  const onMove = (e) => {
  if (!sort.active) return;

  sort.lastX = e.clientX;
  sort.lastY = e.clientY;

  if (sort.raf) return; // уже запланирован кадр

  sort.raf = requestAnimationFrame(() => {
    sort.raf = 0;

    // 1) ghost двигаем каждый кадр — это дёшево
    moveGhost(sort.lastX, sort.lastY);

    // 2) позицию вставки пересчитываем и меняем placeholder только если индекс изменился
    const idx = getInsertIndexByX(sort.lastX);
    if (idx === sort.lastIdx) return;
    sort.lastIdx = idx;

    // вставка placeholder среди кэшированных tabEls (без querySelectorAll)
    if (idx >= sort.tabEls.length) {
      elTabs.appendChild(sort.placeholder);
    } else {
      elTabs.insertBefore(sort.placeholder, sort.tabEls[idx]);
    }

    // скрываем "то же место" (если ты уже добавляла эту логику)
    const nowNext = sort.placeholder.nextElementSibling && sort.placeholder.nextElementSibling.classList.contains("tab")
      ? sort.placeholder.nextElementSibling
      : null;

    if (nowNext === sort.originNextEl) {
      sort.placeholder.remove();
      sort.lastIdx = -1;
    }
  });
};

  const onUp = async (e) => {
    if (!sort.active) return;

    handleEl.releasePointerCapture(e.pointerId);

    // ✅ позиция вставки: индекс следующего таба после placeholder
    const tabs = Array.from(elTabs.querySelectorAll(".tab")).filter(
      el => !el.classList.contains("tab--dragging")
    );

    let before = tabs.length;
    const next = sort.placeholder?.nextElementSibling;
    if (next && next.classList.contains("tab")) {
      const idx = tabs.indexOf(next);
      if (idx >= 0) before = idx;
    }

    const fromIndex = state.tabs.findIndex(t => t.id === sort.draggedId);
    if (fromIndex >= 0) {
      const [moved] = state.tabs.splice(fromIndex, 1);
      state.tabs.splice(Math.min(before, state.tabs.length), 0, moved);
    }

    if (sort.placeholder?.parentElement) sort.placeholder.remove();
    if (sort.ghost?.parentElement) sort.ghost.remove();

    sort.active = false;
    sort.draggedId = null;
    sort.ghost = null;
    sort.fromIndex = -1;
    sort.originNextEl = null;
    sort.ghostH = 0;

    renderTabs();
    await persistTabOrder();
    await loadTabs();
    if (sort.raf) {
      cancelAnimationFrame(sort.raf);
      sort.raf = 0;
    }
    sort.tabRects = [];
    sort.tabEls = [];
    sort.lastIdx = -1;

    window.removeEventListener("pointermove", onMove);
    window.removeEventListener("pointerup", onUp);
  };

  window.addEventListener("pointermove", onMove);
  window.addEventListener("pointerup", onUp);
}

function toId(x) {
  const n = Number(x);
  return Number.isFinite(n) ? n : null;
}

function setActiveTab(id) {
  state.activeTabId = toId(id);
  // запомним выбор, чтобы даже после reloadTabs не прыгало
  if (state.activeTabId) {
    localStorage.setItem(`activeTab:${state.entityTypeId}`, String(state.activeTabId));
  }
}

function setStatus(msg) {
  if (!elStatus) return;
  elStatus.textContent = msg || "";
}
function isValidUrl(value) {
  if (!value) return true; // пустую ссылку разрешаем
  return /^https?:\/\/.+/i.test(value);
}

function showLinkError(message) {
  if (elLinkInput) elLinkInput.classList.add("input--error");
  setStatus(message);
}

function clearLinkError() {
  if (elLinkInput) elLinkInput.classList.remove("input--error");
}

// ---------- entities ----------
async function loadEntities() {
  const data = await api("/api/entities");
  state.entities = data.entities;

  // по умолчанию — первая сущность (Сделки)
  if (!state.entityTypeId && state.entities.length > 0) {
    const first = state.entities[0];
    selectEntity(first); // ← ВАЖНО
  } else {
    renderEntities();
    await loadTabs();
  }
}

function renderEntities() {
  if (!elEntities) return;
  elEntities.innerHTML = "";

  for (const e of state.entities) {
    const div = document.createElement("div");
    div.className = "entity" + (e.id === state.entityTypeId ? " entity--active" : "");
    div.textContent = e.name;
    div.onclick = () => selectEntity(e);
    elEntities.appendChild(div);
  }
}

function selectEntity(e) {
  state.entityTypeId = e.id;
  state.activeTabId = null;
  // (не удаляем localStorage глобально — он отдельно по сущности)
  if (elEntityTitle) elEntityTitle.textContent = e.name;
  renderEntities();
  loadTabs();
}

// ---------- tabs ----------
function openTabNameModal(titleText, initialValue = "") {
  const modal = document.getElementById("nameModal");
  const title = document.getElementById("nameModalTitle");
  const input = document.getElementById("nameModalInput");
  const btnOk = document.getElementById("nameModalOk");
  const btnCancel = document.getElementById("nameModalCancel");

  if (!modal || !title || !input || !btnOk || !btnCancel) {
    // fallback если вдруг модалки нет
    const v = prompt(titleText, initialValue);
    return Promise.resolve(v ? v.trim() : "");
  }
  if (modal.parentElement !== document.body) document.body.appendChild(modal);
  modal.style.position = "fixed";
  modal.style.inset = "0";
  modal.style.zIndex = "2147483647";
  modal.style.display = "grid";
  modal.style.placeItems = "center";
  title.textContent = titleText;
  input.value = initialValue || "";
  modal.hidden = false;

  const backdrop = modal.querySelector(".modal__backdrop");
if (backdrop) {
  backdrop.style.position = "fixed";
  backdrop.style.inset = "0";
}

  return new Promise((resolve) => {
    const close = (val) => {
      modal.hidden = true;
      cleanup();
      resolve(val);
    };

    const onOk = () => close(input.value.trim());
    const onCancel = () => close("");

    const onKey = (e) => {
      if (e.key === "Enter") onOk();
      if (e.key === "Escape") onCancel();
    };

    const onBackdrop = (e) => {
      if (e.target.classList.contains("modal__backdrop")) onCancel();
    };

    function cleanup() {
      btnOk.removeEventListener("click", onOk);
      btnCancel.removeEventListener("click", onCancel);
      window.removeEventListener("keydown", onKey);
      modal.removeEventListener("click", onBackdrop);
    }

    btnOk.addEventListener("click", onOk);
    btnCancel.addEventListener("click", onCancel);
    window.addEventListener("keydown", onKey);
    modal.addEventListener("click", onBackdrop);

    // фокус в инпут
    setTimeout(() => input.focus(), 0);
  });
}

async function loadTabs() {
  // 1) что было активным до загрузки
  const prevActive = toId(state.activeTabId);

  // 2) что было сохранено в localStorage
  const saved = toId(localStorage.getItem(`activeTab:${state.entityTypeId}`));

  const data = await api(`/api/tabs?entity_type_id=${encodeURIComponent(state.entityTypeId)}`);
  state.tabs = (data.tabs || []).map(t => ({ ...t, id: toId(t.id) }));

  // выбираем активный:
  const canUsePrev = prevActive && state.tabs.some(t => t.id === prevActive);
  const canUseSaved = saved && state.tabs.some(t => t.id === saved);

  if (canUsePrev) {
    state.activeTabId = prevActive;
  } else if (canUseSaved) {
    state.activeTabId = saved;
  } else {
    state.activeTabId = state.tabs[0]?.id ?? null;
  }

  renderTabs();
  renderEditor();
}

function renderTabs() {
  if (!elTabs) return;
  elTabs.innerHTML = "";

  // --- рисуем табы ---
  for (const t of state.tabs) {
    const tab = document.createElement("div");
    tab.className = "tab" + (t.id === state.activeTabId ? " tab--active" : "");
    tab.dataset.tabId = String(t.id);

    tab.onclick = () => {
      setActiveTab ? setActiveTab(t.id) : (state.activeTabId = t.id);
      renderTabs();
      renderEditor();
    };

    const title = document.createElement("span");
    title.textContent = t.title;
    tab.appendChild(title);

    if (getCtx().mode === "settings") {
        const handle = document.createElement("span");
        handle.className = "tab-handle";
        handle.title = "Перетащить";
        handle.textContent = "⋮⋮";
        handle.onpointerdown = (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          startSort(t.id, handle, ev);
        };
        tab.insertBefore(handle, title);
      // ✎ rename
      const editBtn = document.createElement("button");
      editBtn.className = "tab-edit";
      editBtn.type = "button";
      editBtn.title = "Переименовать";
      editBtn.textContent = "✎";

      editBtn.onclick = async (ev) => {
        ev.stopPropagation();
        const newTitle = prompt("Новое название таба:", t.title);
        if (!newTitle) return;

        await api(`/api/tabs/${t.id}`, {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ title: newTitle.trim() })
        });

        await loadTabs();
      };

      tab.appendChild(editBtn);
    }

    elTabs.appendChild(tab);
  }
}

// ---------- editor ----------
async function renderEditor() {
  const tab = state.tabs.find(t => t.id === state.activeTabId);

  if (!tab) {
    if (elPreview) elPreview.textContent = "Выбери таб";
    return;
  }

  if (elLinkInput) elLinkInput.value = tab.link || "";
  clearLinkError();

  await renderPreview(tab.link);
}

async function renderPreview(url) {
  if (!elPreview) return;
  elPreview.innerHTML = "";

  if (!url) {
    elPreview.innerHTML = `
      <div class="preview-placeholder">
        <div class="preview-icon">🔗</div>
        <div class="preview-text">Ссылка не задана</div>
      </div>
    `;
    return;
  }

  if (!isValidUrl(url)) {
    elPreview.innerHTML = `
      <div class="preview-placeholder preview-placeholder--error">
        <div class="preview-icon">⚠️</div>
        <div class="preview-text">
          Введите корректную ссылку<br>
          <span class="preview-hint">например: https://example.com</span>
        </div>
      </div>
    `;
    return;
  }

  const iframe = document.createElement("iframe");
  iframe.src = url;
  elPreview.appendChild(iframe);
}
async function persistTabOrder() {
  // записываем order_index согласно текущему порядку в state.tabs
  try {
    setStatus("Сохраняю порядок…");
    for (let i = 0; i < state.tabs.length; i++) {
      const t = state.tabs[i];
      await api(`/api/tabs/${t.id}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ order_index: i })
      });
    }
    setStatus("Порядок сохранён ✓");
  } catch (e) {
    console.error(e);
    setStatus("Ошибка сохранения порядка");
  }
}
async function saveActiveLinkNow({ silent = false } = {}) {
  const tab = state.tabs.find(t => t.id === state.activeTabId);
  if (!tab) return;

  const value = (elLinkInput?.value || "").trim();
  if (!isValidUrl(value)) {
      showLinkError("Ссылка должна начинаться с http:// или https://");
      return;
  }
  // если ничего не изменилось — не шлём запрос
  if (value === (tab.link || "") && value === (lastSavedValue || "")) {
    if (!silent) setStatus("Без изменений.");
    return;
  }

  try {
    if (!silent) setStatus("Сохраняю…");

    await api(`/api/tabs/${tab.id}`, {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ link: value })
    });

    lastSavedValue = value;
    tab.link = value; // локально обновим, чтобы предпросмотр совпадал
    renderPreview(value);

    if (!silent) setStatus("Сохранено ✓");
    else setStatus("Автосохранение ✓");
  } catch (e) {
    console.error(e);
    setStatus("Ошибка сохранения");
  }
}
// ---------- buttons ----------
if (getCtx().mode === "settings" && elLinkInput) {
  elLinkInput.addEventListener("input", () => {
      const value = elLinkInput.value.trim();

      renderPreview(value);

      // ❌ невалидная ссылка — не сохраняем
      if (!isValidUrl(value)) {
        clearTimeout(autosaveTimer);
        showLinkError("Ссылка должна начинаться с http:// или https://");
        return;
      }

      clearLinkError();
      setStatus("Изменения не сохранены…");

      if (autosaveTimer) clearTimeout(autosaveTimer);
      autosaveTimer = setTimeout(() => {
        saveActiveLinkNow({ silent: true });
      }, 800);
  });
}
const btnAdd = document.getElementById("btnAddTab");
const btnSave = document.getElementById("btnSave");
const btnDelete = document.getElementById("btnDelete");

if (btnAdd) {
  btnAdd.onclick = async () => {
    try {
      const title = await openTabNameModal("Название таба", "");
      if (!title) return;

      const created = await api("/api/tabs", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          entity_type_id: state.entityTypeId,
          title: title.trim()
        })
      });

      await loadTabs();   // ← если тут падение, мы его увидим

      const newId =
        created?.tab?.id ??
        created?.id ??
        created?.tab_id ??
        null;

      if (newId) {
        setActiveTab(newId);
        renderTabs();
        renderEditor();
      }

    } catch (e) {
      console.error("Ошибка создания таба:", e);
      setStatus("Ошибка создания таба: " + (e?.message || e));
    }
  };
}

if (btnSave) {
  btnSave.onclick = async (ev) => {
    ev.preventDefault();
    ev.stopPropagation();

    if (autosaveTimer) {
      clearTimeout(autosaveTimer);
      autosaveTimer = null;
    }
    await saveActiveLinkNow({ silent: false });
    // НИЧЕГО больше не вызываем
  };
}

if (btnDelete) {
  btnDelete.onclick = async () => {
    const tab = state.tabs.find(t => t.id === state.activeTabId);
    if (!tab) return;

    if (!confirm("Удалить таб?")) return;

    if (autosaveTimer) {
    clearTimeout(autosaveTimer);
    autosaveTimer = null;
    }

    await api(`/api/tabs/${tab.id}`, { method: "DELETE" });
    loadTabs();
  };
}

function startApp() {
  return (async () => {
    try {
      const ctx = getCtx();
      console.log("CTX", { href: location.href, ctx });

      if (ctx.mode === "crm") {
        console.log("CRM MODE ON", { href: location.href, ctx });

        const tabId = ctx.tabId || new URLSearchParams(location.search).get("tab_id");
        if (!tabId) {
          document.body.innerHTML = "<div style='padding:20px'>tab_id is missing</div>";
          return;
        }

        document.body.innerHTML = "";
        document.documentElement.style.height = "100%";
        document.body.style.height = "100%";
        document.body.style.margin = "0";

        const data = await api(`/api/tabs/${encodeURIComponent(tabId)}`);
        const link = data?.tab?.link || "";

        if (!link) {
          document.body.innerHTML = "<div style='padding:20px'>Ссылка не задана</div>";
          return;
        }

        const iframe = document.createElement("iframe");
        iframe.src = link;
        iframe.style.width = "100%";
        iframe.style.height = "100vh";
        iframe.style.border = "0";
        iframe.style.display = "block";
        document.body.appendChild(iframe);

        return;
      }

      if (ctx.mode === "settings") {
        await loadEntities();
        return;
      }

    } catch (e) {
      console.error(e);
      if (elStatus) elStatus.textContent = "Ошибка загрузки";
    }
  })();
}

BX24.init(async function () {
  const auth = BX24.getAuth && BX24.getAuth();
  const portalId = auth?.member_id || "LOCAL";

  state.portalId = portalId;
  window.APP_CONTEXT = { ...(window.APP_CONTEXT || {}), portalId };

  console.log("PORTAL_ID SET =", portalId);

  // один sync, без null/undefined
  try {
    const domain = auth?.domain || auth?.DOMAIN || "";
    const access = auth?.access_token || auth?.AUTH_ID || "";
    const refresh = auth?.refresh_token || auth?.REFRESH_ID || "";

    const payload = {
      member_id: portalId,
    };

    if (domain) {
      payload.domain = domain;
      payload.client_endpoint = `https://${domain}/rest/`;
    }
    if (access) payload.access_token = access;
    if (refresh) payload.refresh_token = refresh;

    const resp = await fetch("/api/portal/sync", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    console.log("PORTAL SYNC OK", await resp.json());
  } catch (e) {
    console.warn("PORTAL SYNC FAILED", e);
  }
  try {
    const p = BX24.placement && BX24.placement.info ? BX24.placement.info() : null;

    console.log("PLACEMENT INFO", p);

    const placement = p?.placement;
    const options = p?.options || {};

    const tabIdFromPlacement =
      options.tab_id ||
      options.TAB_ID ||
      null;

    if (
      placement === "CRM_DEAL_DETAIL_TAB" ||
      placement === "CRM_CONTACT_DETAIL_TAB" ||
      placement === "CRM_COMPANY_DETAIL_TAB" ||
      placement === "CRM_LEAD_DETAIL_TAB"
    ) {
      window.APP_CONTEXT = {
        ...(window.APP_CONTEXT || {}),
        mode: "crm",
        tabId: tabIdFromPlacement || new URLSearchParams(location.search).get("tab_id"),
      };
    }
  } catch (e) {
    console.warn("PLACEMENT INFO FAILED", e);
  }

  startApp();
});