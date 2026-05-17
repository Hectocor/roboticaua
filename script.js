const lightbox = document.querySelector("#lightbox");
const lightboxImage = lightbox.querySelector("img");
const lightboxCaption = lightbox.querySelector("figcaption");
const closeButton = lightbox.querySelector(".lightbox-close");
const gallery = document.querySelector("#gallery");
const galleryDirectory = gallery?.dataset.galleryDir || "assets/gallery";
const imageExtensions = [".jpg", ".jpeg", ".png", ".webp", ".gif"];

function closeLightbox() {
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

async function loadFromGithubDirectory() {
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

  return files
    .filter((file) => file.type === "file" && isImageFile(file.name))
    .map((file) => ({
      src: file.download_url,
      caption: prettifyFilename(file.name),
    }));
}

async function loadFromManifest() {
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

async function loadFromDirectoryIndex() {
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

async function loadFromNumberedFallback() {
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

function renderGallery(images) {
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

async function loadGallery() {
  if (!gallery) {
    return;
  }

  const loaders = [
    loadFromGithubDirectory,
    loadFromManifest,
    loadFromDirectoryIndex,
    loadFromNumberedFallback,
  ];

  for (const loader of loaders) {
    try {
      const images = await loader();

      if (images.length) {
        renderGallery(images);
        return;
      }
    } catch (error) {
      console.warn("No se pudo cargar la galeria con este metodo.", error);
    }
  }

  renderGallery([]);
}

closeButton.addEventListener("click", closeLightbox);

lightbox.addEventListener("click", (event) => {
  if (event.target === lightbox) {
    closeLightbox();
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && lightbox.classList.contains("is-open")) {
    closeLightbox();
  }
});

loadGallery();
