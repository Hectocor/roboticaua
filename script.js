const lightbox = document.querySelector("#lightbox");
const lightboxImage = lightbox?.querySelector("img");
const lightboxCaption = lightbox?.querySelector("figcaption");
const closeButton = lightbox?.querySelector(".lightbox-close");
const galleries = document.querySelectorAll("[data-gallery-dir]");
const newsLists = document.querySelectorAll("[data-news-list]");
const accessibilityButtons = document.querySelectorAll("[data-accessibility-action]");
const contactModal = document.querySelector("#contactModal");
const contactOpenButtons = document.querySelectorAll("[data-contact-open]");
const contactCloseButtons = document.querySelectorAll("[data-contact-close]");
const contactForms = document.querySelectorAll(".contact-form");
let siteAlertModal = null;
const imageExtensions = [".jpg", ".jpeg", ".png", ".webp", ".gif"];
const accessibilityStorageKey = "roboticaUaAccessibility";
const managedGalleryStorageKey = "roboticaUaManagedGallery";
const defaultAccessibilityState = {
  fontLevel: 0,
  contrast: false,
  invert: false,
};

function closeLightbox() {
  if (!lightbox || !lightboxImage) {
    return;
  }

  lightbox.classList.remove("is-open");
  lightbox.setAttribute("aria-hidden", "true");
  lightboxImage.src = "";
  lightboxImage.alt = "";
}

function openContactModal() {
  if (!contactModal) {
    return;
  }

  contactModal.classList.add("is-open");
  contactModal.setAttribute("aria-hidden", "false");
  contactModal.querySelector("input")?.focus();
}

function closeContactModal() {
  if (!contactModal) {
    return;
  }

  contactModal.classList.remove("is-open");
  contactModal.setAttribute("aria-hidden", "true");
}

function localApiPath(file) {
  return window.location.pathname.includes("/admin/") ? `../api/${file}` : `api/${file}`;
}

function closeSiteAlert() {
  if (!siteAlertModal) {
    return;
  }

  siteAlertModal.classList.remove("is-open");
  siteAlertModal.setAttribute("aria-hidden", "true");
}

function makeSiteAlertModal(alert) {
  const modal = document.createElement("div");
  const dialog = document.createElement("div");
  const close = document.createElement("button");
  const content = document.createElement("div");
  const eyebrow = document.createElement("p");
  const title = document.createElement("h2");
  const message = document.createElement("p");
  const actions = document.createElement("div");

  modal.className = `site-alert-modal${alert.image_path ? " has-image" : ""}`;
  modal.setAttribute("aria-hidden", "true");
  dialog.className = "site-alert-dialog";
  dialog.setAttribute("role", "dialog");
  dialog.setAttribute("aria-modal", "true");
  dialog.setAttribute("aria-labelledby", "siteAlertTitle");
  close.className = "modal-close";
  close.type = "button";
  close.setAttribute("aria-label", "Cerrar novedad");
  close.textContent = "×";
  content.className = "site-alert-content";
  eyebrow.className = "eyebrow";
  eyebrow.textContent = "Novedad";
  title.id = "siteAlertTitle";
  title.textContent = alert.title;
  message.textContent = alert.message;
  actions.className = "site-alert-actions";

  if (alert.image_path) {
    const image = document.createElement("img");
    image.className = "site-alert-image";
    image.src = alert.image_path;
    image.alt = alert.title;
    dialog.appendChild(image);
  }

  if (alert.link_url) {
    const link = document.createElement("a");
    link.className = "button primary alert-link";
    link.href = alert.link_url;
    link.target = "_blank";
    link.rel = "noopener noreferrer";
    link.innerHTML = '<span class="alert-button-bot" aria-hidden="true"></span>';
    link.append(document.createTextNode(alert.link_label || "Abrir formulario"));
    actions.appendChild(link);
  }

  const secondary = document.createElement("button");
  secondary.className = "button secondary";
  secondary.type = "button";
  secondary.textContent = "Ver después";
  actions.appendChild(secondary);

  content.append(eyebrow, title, message, actions);
  dialog.append(close, content);
  modal.appendChild(dialog);

  function dismiss() {
    sessionStorage.setItem(`roboticaUaAlertDismissed:${alert.id}`, "1");
    closeSiteAlert();
  }

  close.addEventListener("click", dismiss);
  secondary.addEventListener("click", dismiss);
  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      dismiss();
    }
  });

  return modal;
}

async function loadSiteAlert() {
  if (window.location.pathname.includes("/admin/")) {
    return;
  }

  try {
    const response = await fetch(localApiPath("alert.php"), { cache: "no-store" });

    if (!response.ok) {
      return;
    }

    const payload = await response.json();
    const alert = payload.alert;

    if (!alert || sessionStorage.getItem(`roboticaUaAlertDismissed:${alert.id}`) === "1") {
      return;
    }

    siteAlertModal = makeSiteAlertModal(alert);
    document.body.appendChild(siteAlertModal);
    window.setTimeout(() => {
      siteAlertModal.classList.add("is-open");
      siteAlertModal.setAttribute("aria-hidden", "false");
      siteAlertModal.querySelector(".modal-close")?.focus();
    }, 450);
  } catch (error) {
    console.warn("No se pudo cargar la novedad destacada.", error);
  }
}

function formatNewsDate(value) {
  if (!value) {
    return "";
  }

  const date = new Date(value.replace(" ", "T"));

  if (Number.isNaN(date.getTime())) {
    return "";
  }

  return new Intl.DateTimeFormat("es-CL", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  }).format(date);
}

function renderNewsList(container, items) {
  container.innerHTML = "";

  if (!items.length) {
    container.innerHTML = `
      <p class="gallery-status">
        Pronto compartiremos nuevas convocatorias y actividades del grupo.
      </p>
    `;
    return;
  }

  container.classList.add("gallery", "news-carousel");
  renderGallerySlider(container, items, createNewsCard, "noticias");
}

function createNewsCard(item) {
    const article = document.createElement("article");
    const content = document.createElement("div");
    const meta = document.createElement("span");
    const title = document.createElement("h3");
    const summary = document.createElement("p");
    const link = document.createElement("a");

    article.className = "news-card";
    content.className = "news-card-content";
    meta.textContent = formatNewsDate(item.published_at) || "Noticia";
    title.textContent = item.title;
    summary.textContent = item.summary || "Lee el detalle de esta noticia del grupo.";
    link.href = item.detail_url || `noticia.php?id=${item.id}`;
    link.textContent = "Leer detalle";
    link.setAttribute("aria-label", `Leer detalle de ${item.title}`);

    if (item.image_path) {
      const image = document.createElement("img");
      image.src = item.image_path;
      image.alt = item.title;
      image.loading = "lazy";
      article.appendChild(image);
    }

    content.append(meta, title, summary, link);
    article.appendChild(content);
    return article;
}

async function loadNewsList(container) {
  const limit = Number.parseInt(container.dataset.newsLimit || "3", 10);
  const params = new URLSearchParams();

  if (Number.isFinite(limit) && limit > 0) {
    params.set("limit", String(limit));
  }

  try {
    const response = await fetch(`${localApiPath("news.php")}${params.toString() ? `?${params}` : ""}`, { cache: "no-store" });

    if (!response.ok) {
      throw new Error("No se pudieron cargar las noticias.");
    }

    const payload = await response.json();
    renderNewsList(container, Array.isArray(payload.items) ? payload.items : []);
  } catch (error) {
    console.warn("No se pudieron cargar las noticias.", error);
    renderNewsList(container, []);
  }
}

function prettifyFilename(path) {
  const filename = path.split("/").pop().replace(/\.[^.]+$/, "");
  return filename
    .replace(/[-_]+/g, " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function isImageFile(name) {
  const lowerName = name.toLowerCase();
  return imageExtensions.some((extension) => lowerName.endsWith(extension));
}

function sortImagesByNameDesc(images) {
  return [...images].sort((a, b) =>
    b.caption.localeCompare(a.caption, "es", {
      numeric: true,
      sensitivity: "base",
    })
  );
}

function getManagedGalleryPayload() {
  try {
    return JSON.parse(localStorage.getItem(managedGalleryStorageKey));
  } catch (error) {
    return null;
  }
}

function getManagedGalleryImages() {
  const payload = getManagedGalleryPayload();

  if (!payload || !Array.isArray(payload.items)) {
    return null;
  }

  return payload.items
    .filter((item) => item && item.src)
    .map((item) => ({
      src: item.src,
      caption: item.caption || prettifyFilename(item.src),
    }));
}

async function loadFromManagedGallery() {
  return getManagedGalleryImages() || [];
}

function getAccessibilityState() {
  try {
    return {
      ...defaultAccessibilityState,
      ...JSON.parse(localStorage.getItem(accessibilityStorageKey)),
    };
  } catch (error) {
    return { ...defaultAccessibilityState };
  }
}

function saveAccessibilityState(state) {
  localStorage.setItem(accessibilityStorageKey, JSON.stringify(state));
}

function applyAccessibilityState(state) {
  const root = document.documentElement;

  root.classList.toggle("access-font-large", state.fontLevel === 1);
  root.classList.toggle("access-font-larger", state.fontLevel === 2);
  root.classList.toggle("access-contrast", state.contrast);
  root.classList.toggle("access-invert", state.invert);

  accessibilityButtons.forEach((button) => {
    const action = button.dataset.accessibilityAction;

    if (action === "contrast") {
      button.setAttribute("aria-pressed", String(state.contrast));
    }

    if (action === "invert") {
      button.setAttribute("aria-pressed", String(state.invert));
    }
  });
}

function updateAccessibility(action) {
  const state = getAccessibilityState();

  if (action === "font-increase") {
    state.fontLevel = Math.min(state.fontLevel + 1, 2);
  }

  if (action === "font-decrease") {
    state.fontLevel = Math.max(state.fontLevel - 1, 0);
  }

  if (action === "contrast") {
    state.contrast = !state.contrast;
  }

  if (action === "invert") {
    state.invert = !state.invert;
  }

  if (action === "reset") {
    Object.assign(state, defaultAccessibilityState);
  }

  saveAccessibilityState(state);
  applyAccessibilityState(state);
}

function inferGithubRepo() {
  const host = window.location.hostname;

  if (!host.endsWith(".github.io")) {
    return null;
  }

  const owner = host.replace(".github.io", "");
  const pathParts = window.location.pathname.split("/").filter(Boolean);
  const repo = pathParts[0] || `${owner}.github.io`;

  return { owner, repo };
}

async function loadFromGithubDirectory(galleryDirectory) {
  const repo = inferGithubRepo();

  if (!repo) {
    return [];
  }

  const endpoint = `https://api.github.com/repos/${repo.owner}/${repo.repo}/contents/${galleryDirectory}`;
  const response = await fetch(endpoint);

  if (!response.ok) {
    return [];
  }

  const files = await response.json();

  return sortImagesByNameDesc(files
    .filter((file) => file.type === "file" && isImageFile(file.name))
    .map((file) => ({
      src: file.download_url,
      caption: prettifyFilename(file.name),
    })));
}

async function loadFromBackofficeApi(galleryDirectory, gallery) {
  const params = new URLSearchParams();
  const order = gallery?.dataset.galleryOrder || "";
  const limit = gallery?.dataset.galleryLimit || "";

  if (order) {
    params.set("order", order);
  }

  if (limit) {
    params.set("limit", limit);
  }

  const response = await fetch(`${localApiPath("gallery.php")}${params.toString() ? `?${params}` : ""}`, { cache: "no-store" });

  if (!response.ok) {
    return [];
  }

  const payload = await response.json();

  if (!Array.isArray(payload.items)) {
    return [];
  }

  return payload.items
    .filter((item) => item && item.src)
    .map((item) => ({
      src: item.src,
      caption: item.caption || item.title || prettifyFilename(item.src),
    }));
}

async function loadFromInlineManifest(galleryDirectory) {
  const files = window.ROBOTICA_GALLERY_IMAGES || [];

  return files
    .map((entry) => {
      if (typeof entry === "string") {
        return {
          src: `${galleryDirectory}/${entry}`,
          caption: prettifyFilename(entry),
        };
      }

      return {
        src: entry?.src || "",
        caption: entry?.caption || prettifyFilename(entry?.src || ""),
      };
    })
    .filter((item) => item.src && (item.src.startsWith("data:image/") || item.src.startsWith("http") || isImageFile(item.src)))
    .map((item) => ({
      src: item.src.startsWith("http") || item.src.startsWith("data:image/") || item.src.includes("/")
        ? item.src
        : `${galleryDirectory}/${item.src}`,
      caption: item.caption,
    }));
}

async function loadFromManifest(galleryDirectory) {
  const response = await fetch(`${galleryDirectory}/manifest.json`, { cache: "no-store" });

  if (!response.ok) {
    return [];
  }

  const files = await response.json();

  return files
    .filter(isImageFile)
    .map((file) => ({
      src: `${galleryDirectory}/${file}`,
      caption: prettifyFilename(file),
    }));
}

async function loadFromDirectoryIndex(galleryDirectory) {
  const response = await fetch(`${galleryDirectory}/`);

  if (!response.ok) {
    return [];
  }

  const html = await response.text();
  const doc = new DOMParser().parseFromString(html, "text/html");

  return [...doc.querySelectorAll("a")]
    .map((link) => link.getAttribute("href"))
    .filter(Boolean)
    .map((href) => decodeURIComponent(href.split("/").pop()))
    .filter(isImageFile)
    .map((file) => ({
      src: `${galleryDirectory}/${file}`,
      caption: prettifyFilename(file),
    }));
}

function imageExists(src) {
  return new Promise((resolve) => {
    const image = new Image();
    image.onload = () => resolve(true);
    image.onerror = () => resolve(false);
    image.src = src;
  });
}

async function loadFromNumberedFallback(galleryDirectory) {
  const candidates = [];

  for (let index = 1; index <= 80; index += 1) {
    candidates.push(`actividad-${String(index).padStart(2, "0")}.jpg`);
  }

  const checks = await Promise.all(
    candidates.map(async (file) => ({
      file,
      exists: await imageExists(`${galleryDirectory}/${file}`),
    }))
  );

  return checks
    .filter((item) => item.exists)
    .map((item) => ({
      src: `${galleryDirectory}/${item.file}`,
      caption: `Actividad ${item.file.match(/\d+/)?.[0] || ""}`.trim(),
    }));
}

function bindGalleryItem(item) {
  item.addEventListener("click", () => {
    if (!lightbox || !lightboxImage || !lightboxCaption || !closeButton) {
      return;
    }

    const image = item.dataset.full;
    const caption = item.dataset.caption;

    lightboxImage.src = image;
    lightboxImage.alt = caption;
    lightboxCaption.textContent = caption;
    lightbox.classList.add("is-open");
    lightbox.setAttribute("aria-hidden", "false");
    closeButton.focus();
  });
}

function renderGallery(gallery, images) {
  gallery.innerHTML = "";

  if (!images.length) {
    gallery.innerHTML = `
      <p class="gallery-status">
        Pronto compartiremos nuevos registros de nuestras actividades.
      </p>
    `;
    return;
  }

  if (gallery.dataset.galleryMode === "slider") {
    renderGallerySlider(gallery, images);
    return;
  }

  if (gallery.dataset.galleryMode === "pages") {
    renderGalleryPages(gallery, images);
    return;
  }

  images.forEach((image, index) => {
    gallery.appendChild(createGalleryItem(image, index));
  });
}

function createGalleryItem(image, index) {
    const button = document.createElement("button");
    const thumbnail = document.createElement("img");
    const label = document.createElement("span");
    const caption = image.caption || `Actividad ${index + 1}`;

    button.className = "gallery-item";
    button.type = "button";
    button.dataset.full = image.src;
    button.dataset.caption = caption;
    thumbnail.src = image.src;
    thumbnail.alt = caption;
    thumbnail.loading = "lazy";
    label.textContent = caption;
    button.append(thumbnail, label);
    bindGalleryItem(button);

  return button;
}

function renderGallerySlider(gallery, images, makeItem = createGalleryItem, kind = "imágenes") {
  let currentIndex = 0;
  let autoplayId = null;
  const viewport = document.createElement("div");
  const track = document.createElement("div");
  const controls = document.createElement("div");
  const previousButton = document.createElement("button");
  const nextButton = document.createElement("button");
  const dots = document.createElement("div");

  gallery.classList.add("gallery-slider");
  viewport.className = "gallery-slider-viewport";
  track.className = "gallery-slider-track";
  controls.className = "gallery-slider-controls";
  previousButton.className = "slider-control";
  nextButton.className = "slider-control";
  previousButton.type = "button";
  nextButton.type = "button";
  previousButton.setAttribute("aria-label", `Ver ${kind} anteriores`);
  nextButton.setAttribute("aria-label", `Ver ${kind} siguientes`);
  previousButton.innerHTML = '<span class="slider-bot is-left" aria-hidden="true"><span></span></span>';
  nextButton.innerHTML = '<span class="slider-bot is-right" aria-hidden="true"><span></span></span>';
  dots.className = "gallery-slider-dots";
  viewport.tabIndex = 0;
  viewport.setAttribute("aria-label", `Carrusel de últimas ${kind}`);

  images.forEach((image, index) => {
    track.appendChild(makeItem(image, index));
  });

  function visibleCount() {
    if (window.matchMedia("(max-width: 620px)").matches) {
      return 1;
    }

    if (window.matchMedia("(max-width: 980px)").matches) {
      return 2;
    }

    return 3;
  }

  function pageCount() {
    return Math.max(1, images.length - visibleCount() + 1);
  }

  function updateSlider() {
    currentIndex = ((currentIndex % pageCount()) + pageCount()) % pageCount();
    track.style.transform = `translate3d(calc(${currentIndex} * -1 * var(--slider-step)), 0, 0)`;
    controls.style.display = pageCount() > 1 ? "flex" : "none";
    dots.style.display = pageCount() > 1 ? "flex" : "none";
    dots.querySelectorAll("button").forEach((dot, index) => {
      dot.setAttribute("aria-current", String(index === currentIndex));
    });
  }

  function renderDots() {
    dots.innerHTML = "";

    for (let index = 0; index < pageCount(); index += 1) {
      const dot = document.createElement("button");
      dot.type = "button";
      dot.setAttribute("aria-label", `Ir al grupo de ${kind} ${index + 1}`);
      dot.addEventListener("click", () => {
        currentIndex = index;
        updateSlider();
      });
      dots.appendChild(dot);
    }
  }

  previousButton.addEventListener("click", () => {
    currentIndex -= 1;
    updateSlider();
  });

  nextButton.addEventListener("click", () => {
    currentIndex += 1;
    updateSlider();
  });

  viewport.addEventListener("keydown", (event) => {
    if (event.key === "ArrowLeft") {
      currentIndex -= 1;
      updateSlider();
    }

    if (event.key === "ArrowRight") {
      currentIndex += 1;
      updateSlider();
    }
  });

  let touchStartX = 0;

  viewport.addEventListener("touchstart", (event) => {
    touchStartX = event.touches[0]?.clientX || 0;
    stopAutoplay();
  }, { passive: true });

  viewport.addEventListener("touchend", (event) => {
    const touchEndX = event.changedTouches[0]?.clientX || 0;
    const delta = touchEndX - touchStartX;

    if (Math.abs(delta) > 42) {
      currentIndex += delta < 0 ? 1 : -1;
      updateSlider();
    }

    startAutoplay();
  }, { passive: true });

  function startAutoplay() {
    if (autoplayId || images.length <= visibleCount()) {
      return;
    }

    autoplayId = window.setInterval(() => {
      currentIndex += 1;
      updateSlider();
    }, 4200);
  }

  function stopAutoplay() {
    window.clearInterval(autoplayId);
    autoplayId = null;
  }

  window.addEventListener("resize", () => {
    renderDots();
    updateSlider();
    stopAutoplay();
    startAutoplay();
  });

  gallery.addEventListener("mouseenter", stopAutoplay);
  gallery.addEventListener("mouseleave", startAutoplay);
  gallery.addEventListener("focusin", stopAutoplay);
  gallery.addEventListener("focusout", startAutoplay);

  controls.append(previousButton, nextButton);
  viewport.append(track, controls);
  gallery.append(viewport, dots);
  renderDots();
  updateSlider();
  startAutoplay();
}

function renderGalleryPages(gallery, images) {
  let currentPage = 0;
  const pageSize = Number.parseInt(gallery.dataset.galleryPageSize || "9", 10);
  const safePageSize = Number.isFinite(pageSize) && pageSize > 0 ? pageSize : 9;
  const grid = document.createElement("div");
  const controls = document.createElement("div");
  const previousButton = document.createElement("button");
  const nextButton = document.createElement("button");
  const pageNumbers = document.createElement("div");
  const pageTotal = Math.ceil(images.length / safePageSize);

  gallery.classList.add("gallery-pager");
  grid.className = "gallery-page-grid";
  controls.className = "gallery-page-controls";
  previousButton.className = "page-control";
  nextButton.className = "page-control";
  previousButton.type = "button";
  nextButton.type = "button";
  previousButton.innerHTML = '<span class="pager-bot is-left" aria-hidden="true"></span>';
  nextButton.innerHTML = '<span class="pager-bot is-right" aria-hidden="true"></span>';
  previousButton.setAttribute("aria-label", "Página anterior");
  nextButton.setAttribute("aria-label", "Página siguiente");
  pageNumbers.className = "gallery-page-numbers";

  function renderPageNumbers() {
    pageNumbers.innerHTML = "";

    for (let index = 0; index < pageTotal; index += 1) {
      const pageButton = document.createElement("button");
      pageButton.type = "button";
      pageButton.textContent = String(index + 1);
      pageButton.setAttribute("aria-label", `Ir a página ${index + 1}`);
      pageButton.setAttribute("aria-current", String(index === currentPage));
      pageButton.addEventListener("click", () => {
        currentPage = index;
        updatePage();
        gallery.scrollIntoView({ behavior: "smooth", block: "start" });
      });
      pageNumbers.appendChild(pageButton);
    }
  }

  function updatePage() {
    currentPage = Math.min(Math.max(currentPage, 0), pageTotal - 1);
    const start = currentPage * safePageSize;
    const pageItems = images.slice(start, start + safePageSize);

    grid.innerHTML = "";
    pageItems.forEach((image, index) => {
      grid.appendChild(createGalleryItem(image, start + index));
    });

    previousButton.disabled = currentPage === 0;
    nextButton.disabled = currentPage >= pageTotal - 1;
    renderPageNumbers();
  }

  previousButton.addEventListener("click", () => {
    currentPage -= 1;
    updatePage();
    gallery.scrollIntoView({ behavior: "smooth", block: "start" });
  });

  nextButton.addEventListener("click", () => {
    currentPage += 1;
    updatePage();
    gallery.scrollIntoView({ behavior: "smooth", block: "start" });
  });

  controls.append(previousButton, pageNumbers, nextButton);
  gallery.append(grid, controls);
  updatePage();
}

async function loadGallery(gallery) {
  const galleryDirectory = gallery.dataset.galleryDir || "assets/gallery";
  const limit = Number.parseInt(gallery.dataset.galleryLimit || "", 10);
  const loaders = [
    loadFromBackofficeApi,
    loadFromManagedGallery,
    loadFromGithubDirectory,
    loadFromInlineManifest,
    loadFromManifest,
    loadFromDirectoryIndex,
    loadFromNumberedFallback,
  ];

  for (const loader of loaders) {
    try {
      const images = await loader(galleryDirectory, gallery);

      if (images.length) {
        renderGallery(gallery, Number.isFinite(limit) ? images.slice(0, limit) : images);
        return;
      }
    } catch (error) {
      console.warn("No se pudo cargar la galería con este método.", error);
    }
  }

  renderGallery(gallery, []);
}

if (closeButton) {
  closeButton.addEventListener("click", closeLightbox);
}

if (lightbox) {
  lightbox.addEventListener("click", (event) => {
    if (event.target === lightbox) {
      closeLightbox();
    }
  });
}

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && lightbox?.classList.contains("is-open")) {
    closeLightbox();
  }

  if (event.key === "Escape" && contactModal?.classList.contains("is-open")) {
    closeContactModal();
  }

  if (event.key === "Escape" && siteAlertModal?.classList.contains("is-open")) {
    closeSiteAlert();
  }
});

accessibilityButtons.forEach((button) => {
  button.addEventListener("click", () => {
    updateAccessibility(button.dataset.accessibilityAction);
  });
});

contactOpenButtons.forEach((button) => {
  button.addEventListener("click", openContactModal);
});

contactCloseButtons.forEach((button) => {
  button.addEventListener("click", closeContactModal);
});

if (contactModal) {
  contactModal.addEventListener("click", (event) => {
    if (event.target === contactModal) {
      closeContactModal();
    }
  });
}

contactForms.forEach((form) => {
  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const status = form.querySelector(".form-status");
    const submitButton = form.querySelector("button[type='submit']");
    const formData = new FormData(form);
    const name = String(formData.get("name") || "").trim();
    const email = String(formData.get("email") || "").trim();
    const subject = String(formData.get("subject") || "").trim();
    const message = String(formData.get("message") || "").trim();
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const validationMessages = [];

    if (name.length < 2) {
      validationMessages.push("Cuéntanos tu nombre o institución.");
    }

    if (!emailPattern.test(email)) {
      validationMessages.push("Déjanos un correo válido para responderte.");
    }

    if (subject.length < 4) {
      validationMessages.push("Agrega un asunto breve.");
    }

    if (message.length < 12) {
      validationMessages.push("El mensaje necesita un poquito más de contexto.");
    }

    if (validationMessages.length) {
      if (status) {
        status.textContent = validationMessages.join(" ");
        status.classList.add("is-error");
      }
      return;
    }

    if (status) {
      status.textContent = "El robot mensajero está saliendo...";
      status.classList.remove("is-error");
    }

    if (submitButton) {
      submitButton.disabled = true;
    }

    try {
      const response = await fetch(form.action, {
        method: "POST",
        body: formData,
        headers: {
          Accept: "application/json",
        },
      });
      const payload = await response.json();

      if (!response.ok || !payload.ok) {
        throw new Error(payload.message || "No se pudo enviar el mensaje.");
      }

      form.reset();
      if (status) {
        status.textContent = payload.message || "Mensaje enviado correctamente.";
        status.classList.remove("is-error");
      }
    } catch (error) {
      if (status) {
        status.textContent = error.message || "No se pudo enviar el mensaje.";
        status.classList.add("is-error");
      }
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
      }
    }
  });
});

applyAccessibilityState(getAccessibilityState());
document.querySelectorAll("[data-news-image-viewer]").forEach((viewer) => {
  viewer.querySelectorAll("[data-image-mode]").forEach((button) => {
    button.addEventListener("click", () => {
      viewer.classList.toggle("is-cover", button.dataset.imageMode === "cover");
      viewer.querySelectorAll("[data-image-mode]").forEach((control) => {
        control.setAttribute("aria-pressed", String(control === button));
      });
    });
  });
});
galleries.forEach(loadGallery);
newsLists.forEach(loadNewsList);
loadSiteAlert();
