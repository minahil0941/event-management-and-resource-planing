document.addEventListener("DOMContentLoaded", function () {
    
    // 1. Elements
    const sidebar = document.getElementById("sidebar");
    const toggleBtn = document.getElementById("toggle-sidebar");
    const themeBtn = document.getElementById("toggle-theme");
    const themeIcon = themeBtn ? themeBtn.querySelector("i") : null;
    const themeText = themeBtn ? themeBtn.querySelector(".link-text") : null;

    // ---------------------------------------------------------
    // 1. THEME SYNCHRONIZATION
    // ---------------------------------------------------------
    // We now rely on [data-bs-theme] in CSS for theme switching.
    // This script ensures the sidebar state is managed if needed.
    
    function syncSidebarTheme() {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme');
        if (sidebar) {
            if (currentTheme === 'dark') {
                sidebar.classList.remove('bg-white');
                sidebar.classList.add('bg-dark');
            } else {
                sidebar.classList.remove('bg-dark');
                sidebar.classList.add('bg-white');
            }
        }
    }

    // Run on initial load
    syncSidebarTheme();

    // Observe changes to data-bs-theme on <html>
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'data-bs-theme') {
                syncSidebarTheme();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });

    // ---------------------------------------------------------
    // 2. SIDEBAR COLLAPSE LOGIC (Keep existing working logic)
    // ---------------------------------------------------------
    if (toggleBtn && sidebar) {
        // Check saved state
        if (localStorage.getItem("sidebar-state") === "collapsed") {
            sidebar.classList.add("collapsed");
        }

        toggleBtn.addEventListener("click", function () {
            sidebar.classList.toggle("collapsed");
            
            if (sidebar.classList.contains("collapsed")) {
                localStorage.setItem("sidebar-state", "collapsed");
                // Close open submenus
                document.querySelectorAll('#sidebar .collapse.show').forEach(function(el) {
                    var bsCollapse = bootstrap.Collapse.getInstance(el);
                    if (bsCollapse) bsCollapse.hide();
                    else new bootstrap.Collapse(el).hide();
                });
            } else {
                localStorage.setItem("sidebar-state", "expanded");
            }
        });
    }
});