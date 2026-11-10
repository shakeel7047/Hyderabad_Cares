/* FixMyHyderabad — small vanilla JS (no dependencies) */
(function () {
  "use strict";

  var isGithubPagesPreview = location.hostname.endsWith(".github.io");

  if (isGithubPagesPreview) {
    var previewBanner = document.createElement("aside");
    previewBanner.className = "preview-banner";
    previewBanner.setAttribute("role", "status");
    previewBanner.textContent =
      "Client preview — sample data only. Report submissions and live updates will be enabled after PHP/MySQL hosting.";
    var header = document.querySelector(".site-header");
    if (header) header.insertAdjacentElement("afterend", previewBanner);

    document.querySelectorAll(".site-footer__bottom p").forEach(function (paragraph) {
      if (paragraph.textContent.indexOf("Demo data notice:") === 0) {
        paragraph.textContent =
          "Preview notice: city statistics and sample reports are illustrative. Reports cannot be submitted on this static preview.";
      }
    });
  }

  /* ---- mobile nav ---- */
  var toggle = document.querySelector(".nav-toggle");
  var nav = document.querySelector(".nav");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  }

  /* ---- footer year ---- */
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });

  /* ---- report form → api/reports.php ---- */
  var form = document.querySelector("[data-report-form]");
  if (form) {
    if (isGithubPagesPreview) {
      form.hidden = true;
      var previewNotice = document.createElement("p");
      previewNotice.className = "note-box";
      previewNotice.setAttribute("role", "status");
      previewNotice.textContent =
        "Report submission is disabled in this client preview. It will be available after the PHP/MySQL backend is deployed.";
      form.parentNode.insertBefore(previewNotice, form);
    }

    var success = document.querySelector("[data-report-success]");
    var errorBox = document.querySelector("[data-report-error]");
    var idEl = document.querySelector("[data-report-id]");
    var submitBtn = form.querySelector('[type="submit"]');

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      if (!form.reportValidity()) return;

      if (errorBox) {
        errorBox.hidden = true;
        errorBox.textContent = "";
      }

      var originalLabel = submitBtn ? submitBtn.textContent : "";
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Sending…";
      }

      fetch(form.getAttribute("action") || "api/reports.php", {
        method: "POST",
        body: new FormData(form),
        headers: { Accept: "application/json" }
      })
        .then(function (response) {
          return response
            .json()
            .catch(function () {
              throw new Error("The server did not return a valid response.");
            })
            .then(function (data) {
              if (!response.ok || !data.ok) {
                throw new Error(data.error || "Could not submit the report.");
              }
              return data;
            });
        })
        .then(function (data) {
          if (idEl) idEl.textContent = data.public_id;
          form.hidden = true;
          if (success) {
            success.hidden = false;
            success.scrollIntoView({ behavior: "smooth", block: "center" });
          }
        })
        .catch(function (error) {
          if (errorBox) {
            errorBox.textContent =
              error.message +
              " Check your connection and make sure the reports database is configured.";
            errorBox.hidden = false;
          }
        })
        .finally(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalLabel;
          }
        });
    });

    var reset = document.querySelector("[data-report-reset]");
    if (reset) {
      reset.addEventListener("click", function () {
        form.reset();
        form.hidden = false;
        if (success) success.hidden = true;
        window.scrollTo({ top: 0, behavior: "smooth" });
      });
    }
  }

  /* ---- copy report id ---- */
  var copyBtn = document.querySelector("[data-copy-id]");
  if (copyBtn) {
    copyBtn.addEventListener("click", function () {
      var idEl = document.querySelector("[data-report-id]");
      if (!idEl || !navigator.clipboard) return;
      navigator.clipboard.writeText(idEl.textContent.trim()).then(function () {
        var old = copyBtn.textContent;
        copyBtn.textContent = "Copied";
        setTimeout(function () {
          copyBtn.textContent = old;
        }, 1500);
      });
    });
  }

  /* ---- copy app link ---- */
  var linkBtn = document.querySelector("[data-copy-link]");
  if (linkBtn) {
    linkBtn.addEventListener("click", function () {
      var status = document.querySelector("[data-copy-status]");
      var done = function () {
        if (status) status.textContent = "Link copied.";
        var old = linkBtn.textContent;
        linkBtn.textContent = "Copied";
        setTimeout(function () {
          linkBtn.textContent = old;
          if (status) status.textContent = "";
        }, 1800);
      };
      if (navigator.clipboard) {
        navigator.clipboard.writeText(window.location.href).then(done).catch(function () {
          if (status) status.textContent = "Copy failed — select the address bar instead.";
        });
      } else if (status) {
        status.textContent = "Copy the address from your browser.";
      }
    });
  }

  /* ---- installable app (PWA) ---- */
  var installButtons = document.querySelectorAll("[data-install-app]");
  var installStatus = document.querySelectorAll("[data-install-status]");
  var deferredInstall = null;

  var ua = navigator.userAgent || "";
  var isIOS =
    /iPad|iPhone|iPod/.test(ua) ||
    (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
  var isStandalone =
    (window.matchMedia && window.matchMedia("(display-mode: standalone)").matches) ||
    window.navigator.standalone === true;

  function setInstallStatus(message) {
    installStatus.forEach(function (el) {
      el.textContent = message;
    });
  }

  if (installButtons.length) {
    if (isStandalone) {
      installButtons.forEach(function (button) {
        button.hidden = true;
      });
      setInstallStatus("Installed. You are using the app right now.");
    } else if (isIOS) {
      installButtons.forEach(function (button) {
        button.hidden = true;
      });
      setInstallStatus(
        "On iPhone or iPad: tap Share, then Add to Home Screen."
      );
    } else {
      setInstallStatus(
        "Tap Install app below. If the button does nothing, open your browser menu and choose Install app or Add to Home screen."
      );
    }

    window.addEventListener("beforeinstallprompt", function (event) {
      event.preventDefault();
      deferredInstall = event;
      installButtons.forEach(function (button) {
        button.hidden = false;
        button.disabled = false;
      });
      setInstallStatus("Ready to install. It takes a second.");
    });

    installButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        if (!deferredInstall) {
          setInstallStatus(
            "Use your browser menu and choose Install app or Add to Home screen. On iPhone, use Share then Add to Home Screen."
          );
          return;
        }

        deferredInstall.prompt();
        deferredInstall.userChoice
          .then(function (choice) {
            if (choice.outcome === "accepted") {
              setInstallStatus(
                "Installing — look for the FixMyHyderabad icon on your home screen."
              );
            } else {
              setInstallStatus("No problem. You can install any time from this page.");
            }
            deferredInstall = null;
            installButtons.forEach(function (b) {
              b.hidden = true;
            });
          })
          .catch(function () {
            deferredInstall = null;
          });
      });
    });

    window.addEventListener("appinstalled", function () {
      setInstallStatus("Installed. Open FixMyHyderabad from your home screen.");
      installButtons.forEach(function (button) {
        button.hidden = true;
      });
    });
  }

  /* ---- service worker (offline shell + install support) ---- */
  if (
    "serviceWorker" in navigator &&
    (location.protocol === "https:" ||
      location.hostname === "localhost" ||
      location.hostname === "127.0.0.1")
  ) {
    window.addEventListener("load", function () {
      navigator.serviceWorker.register("sw.js").catch(function () {
        /* Service workers are an enhancement; the site works without one. */
      });
    });
  }
})();
