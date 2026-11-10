document.querySelectorAll(".news-form, .managed-news-item form").forEach((form) => {
  const editorElement = form.querySelector("[data-news-editor]");
  const plainField = form.querySelector('[name="news_body"]');
  const htmlField = form.querySelector('[name="news_body_html"]');
  if (!editorElement || !plainField || !htmlField || !window.Quill) return;

  editorElement.style.display = "block";

  const quill = new Quill(editorElement, {
    theme: "snow",
    placeholder: "Cuenta qué está pasando, agrega listas, enlaces e imágenes…",
    modules: {
      toolbar: [
        [{ header: [2, 3, false] }],
        ["bold", "italic", "underline"],
        [{ list: "ordered" }, { list: "bullet" }],
        [{ background: [false, "#fff0a8", "#ffdbe3", "#d9f4f6", "#e4f4c5"] }],
        ["link", "image"],
        ["clean"],
      ],
    },
  });

  plainField.hidden = true;
  plainField.required = false;

  quill.getModule("toolbar").addHandler("image", () => {
    const picker = document.createElement("input");
    picker.type = "file";
    picker.accept = "image/jpeg,image/png,image/webp,image/gif";
    picker.hidden = true;
    form.appendChild(picker);
    picker.addEventListener("cancel", () => picker.remove(), { once: true });
    picker.addEventListener("change", async () => {
      const file = picker.files?.[0];
      if (!file) {
        picker.remove();
        return;
      }
      const data = new FormData();
      data.set("action", "upload_news_inline");
      data.set("ajax", "1");
      data.set("csrf_token", form.querySelector('[name="csrf_token"]').value);
      data.set("news_inline_image", file);
      try {
        const response = await fetch("./", { method: "POST", body: data, credentials: "same-origin" });
        let result;
        try {
          result = await response.json();
        } catch {
          throw new Error("El servidor no devolvió una respuesta válida. Revisa la configuración de PHP para subidas.");
        }
        if (!response.ok || !result.ok) throw new Error(result.message || "No se pudo subir la imagen.");
        const index = quill.getSelection(true)?.index ?? quill.getLength();
        quill.insertEmbed(index, "image", result.url, "user");
        quill.setSelection(index + 1);
      } catch (error) {
        window.alert(error.message);
      } finally {
        picker.remove();
      }
    }, { once: true });
    picker.click();
  });

  form.addEventListener("submit", (event) => {
    if (event.submitter?.value === "delete_news") return;
    const plain = quill.getText().trim();
    if (plain.length < 12) {
      event.preventDefault();
      window.alert("Escribe al menos 12 caracteres en el detalle.");
      quill.focus();
      return;
    }
    plainField.value = plain;
    htmlField.value = quill.getSemanticHTML();
  });
});

document.querySelectorAll("[data-gallery-edit-form]").forEach((form) => {
  const status = form.querySelector(".admin-inline-status");
  form.addEventListener("submit", async (event) => {
    if (event.submitter?.value !== "update") return;
    event.preventDefault();
    const button = event.submitter;
    const data = new FormData(form);
    data.set("action", "update");
    data.set("ajax", "1");
    button.disabled = true;
    status.textContent = "Guardando…";
    status.classList.remove("is-error");
    try {
      const response = await fetch("./?view=gallery", { method: "POST", body: data, credentials: "same-origin" });
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || "No se pudieron guardar los cambios.");
      status.textContent = "✓ Cambios guardados";
    } catch (error) {
      status.textContent = error.message;
      status.classList.add("is-error");
    } finally {
      button.disabled = false;
    }
  });
});
