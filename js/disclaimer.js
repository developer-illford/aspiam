document.addEventListener("DOMContentLoaded", function () {
  const popupOverlay = document.getElementById("popupOverlay");
  if (!popupOverlay) return;

  // Check if disclaimer was already handled on any page
  if (localStorage.getItem("disclaimerHandled") === "true") {
    return; // Don't show again
  }

  let disclaimerShown = false;

  window.addEventListener("scroll", function () {
    const scrollTop = window.scrollY || window.pageYOffset;
    const viewportHeight = window.innerHeight;
    const pageHeight = Math.max(
      document.body.scrollHeight,
      document.documentElement.scrollHeight
    );

    const atBottom = scrollTop + viewportHeight >= pageHeight - 5;

    if (!disclaimerShown && atBottom) {
      popupOverlay.style.display = "flex";
      disclaimerShown = true; // shown once per page
    }
  });

  window.acceptDisclaimer = function () {
    popupOverlay.style.display = "none";
    localStorage.setItem("disclaimerHandled", "true"); // Remember across pages
  };

  window.declineDisclaimer = function () {
    popupOverlay.style.display = "none";
    localStorage.setItem("disclaimerHandled", "true"); // Remember across pages
  };
});
