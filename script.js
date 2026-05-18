const lightbox = document.querySelector("#lightbox");
const lightboxImage = lightbox?.querySelector("img");
const lightboxCaption = lightbox?.querySelector("figcaption");
const closeButton = lightbox?.querySelector(".lightbox-close");
const galleries = document.querySelectorAll("[data-gallery-dir]");
const accessibilityButtons = document.querySelectorAll("[data-accessibility-action]");
const imageExtensions = [".jpg", ".jpeg", ".png", ".webp", ".gif"];
const accessibilityStorageKey = "roboticaUaAccessibility";
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

async function loadFromInlineManifest(galleryDirectory) {
  const files = window.ROBOTICA_GALLERY_IMAGES || [];

  return files
    .filter(isImageFile)
    .map((file) => ({
      src: `${galleryDirectory}/${file}`,
      caption: prettifyFilename(file),
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

  images.forEach((image, index) => {
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
    gallery.appendChild(button);
  });
}

async function loadGallery(gallery) {
  const galleryDirectory = gallery.dataset.galleryDir || "assets/gallery";
  const limit = Number.parseInt(gallery.dataset.galleryLimit || "", 10);
  const loaders = [
    loadFromGithubDirectory,
    loadFromInlineManifest,
    loadFromManifest,
    loadFromDirectoryIndex,
    loadFromNumberedFallback,
  ];

  for (const loader of loaders) {
    try {
      const images = await loader(galleryDirectory);

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
});

accessibilityButtons.forEach((button) => {
  button.addEventListener("click", () => {
    updateAccessibility(button.dataset.accessibilityAction);
  });
});

applyAccessibilityState(getAccessibilityState());
galleries.forEach(loadGallery);
