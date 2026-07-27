function clearFormErrors(form) {
  form.querySelectorAll(".field-error").forEach((el) => (el.textContent = ""));
  form.querySelectorAll(".invalid").forEach((el) => el.classList.remove("invalid"));
  const banner = form.querySelector(".alert-danger");
  if (banner) banner.classList.add("hidden");
}

function displayFormErrors(form, apiError) {
  clearFormErrors(form);

  const banner = form.querySelector(".alert-danger");

  const fieldErrors = apiError.errors || {};
  const fieldNames = Object.keys(fieldErrors);

  fieldNames.forEach((field) => {
    const input = form.querySelector(`[name="${field}"]`);
    const errorEl = form.querySelector(`[data-error-for="${field}"]`);
    const message = fieldErrors[field][0];

    if (input) input.classList.add("invalid");
    if (errorEl) {
      errorEl.textContent = message;
    } else if (banner) {
      banner.textContent = message;
      banner.classList.remove("hidden");
    }
  });

  if (fieldNames.length === 0 && banner) {
    banner.textContent = apiError.message || "Something went wrong.";
    banner.classList.remove("hidden");
  }
}

function setButtonLoading(button, isLoading, loadingText = "Please wait...") {
  if (isLoading) {
    button.dataset.originalText = button.textContent;
    button.textContent = loadingText;
    button.disabled = true;
  } else {
    button.textContent = button.dataset.originalText || button.textContent;
    button.disabled = false;
  }
}
