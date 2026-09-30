document.addEventListener("DOMContentLoaded", function () {
  var toggle = document.querySelector(".nav-toggle");
  var links = document.querySelector(".nav-links");

  if (toggle && links) {
    toggle.addEventListener("click", function () {
      var isOpen = links.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    links.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        links.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  var notice = document.getElementById("siteNotice");
  if (notice) {
    var hideNotice = function () {
      notice.classList.add("is-hidden");
      window.removeEventListener("scroll", hideNotice);
    };
    window.addEventListener("scroll", hideNotice, { passive: true });

    var closeBtn = notice.querySelector(".site-notice-close");
    if (closeBtn) {
      closeBtn.addEventListener("click", hideNotice);
    }
  }

  document.getElementById("year") && (document.getElementById("year").textContent = new Date().getFullYear());
});
