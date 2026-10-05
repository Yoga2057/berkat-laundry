// Berkat Laundry - Client-Side Script

document.addEventListener("DOMContentLoaded", () => {
    initTheme();
    initTransactionCalculator();
    initSidebarToggle();
});

// --- 1. THEME CONTROLLER ---
function initTheme() {
    const themeBtn = document.getElementById("theme-toggle-btn");
    if (!themeBtn) return;

    // Load theme from localStorage
    const savedTheme = localStorage.getItem("berkat_laundry_theme") || "light";
    document.body.setAttribute("data-theme", savedTheme);
    updateThemeIcon(themeBtn, savedTheme);

    themeBtn.addEventListener("click", () => {
        const currentTheme = document.body.getAttribute("data-theme");
        const newTheme = currentTheme === "dark" ? "light" : "dark";
        
        document.body.setAttribute("data-theme", newTheme);
        localStorage.setItem("berkat_laundry_theme", newTheme);
        updateThemeIcon(themeBtn, newTheme);
    });
}

function updateThemeIcon(btn, theme) {
    if (theme === "dark") {
        btn.innerHTML = '<i class="fa-solid fa-sun"></i>';
    } else {
        btn.innerHTML = '<i class="fa-solid fa-moon"></i>';
    }
}

// --- 2. TRANSACTION CALCULATOR ---
function initTransactionCalculator() {
    const qtyInputs = document.querySelectorAll(".service-qty-input");
    const serviceChecks = document.querySelectorAll(".service-check-input");
    
    if (qtyInputs.length === 0 || serviceChecks.length === 0) return;

    function recalculateTotal() {
        let total = 0;
        const summaryItemsContainer = document.getElementById("summary-items-list");
        const totalDisplay = document.getElementById("summary-total-price");
        
        if (!summaryItemsContainer || !totalDisplay) return;

        summaryItemsContainer.innerHTML = ""; // Clear current summary items

        serviceChecks.forEach(check => {
            if (check.checked) {
                const row = check.closest(".service-item-row");
                const qtyInput = row.querySelector(".service-qty-input");
                const price = parseInt(check.getAttribute("data-price"), 10);
                const name = check.getAttribute("data-name");
                const unit = check.getAttribute("data-unit");
                
                let qty = parseFloat(qtyInput.value);
                if (isNaN(qty) || qty <= 0) {
                    qty = 1;
                    qtyInput.value = 1;
                }

                const subtotal = price * qty;
                total += subtotal;

                // Add to summary UI
                const itemEl = document.createElement("div");
                itemEl.className = "order-summary-item";
                itemEl.innerHTML = `
                    <span class="label">${name} (${qty} ${unit} x Rp ${price.toLocaleString("id-ID")})</span>
                    <span class="val">Rp ${subtotal.toLocaleString("id-ID")}</span>
                `;
                summaryItemsContainer.appendChild(itemEl);
            }
        });

        // Update Total
        totalDisplay.textContent = "Rp " + total.toLocaleString("id-ID");
    }

    // Attach listeners
    serviceChecks.forEach(check => {
        check.addEventListener("change", (e) => {
            const row = check.closest(".service-item-row");
            const qtyInput = row.querySelector(".service-qty-input");
            
            if (check.checked) {
                qtyInput.disabled = false;
                qtyInput.focus();
            } else {
                qtyInput.disabled = true;
                qtyInput.value = 1;
            }
            recalculateTotal();
        });
    });

    qtyInputs.forEach(input => {
        input.addEventListener("input", recalculateTotal);
        input.addEventListener("blur", () => {
            if (parseFloat(input.value) <= 0 || isNaN(parseFloat(input.value))) {
                input.value = 1;
                recalculateTotal();
            }
        });
    });

    // Run initial calc
    recalculateTotal();
}

// --- 3. SIDEBAR TOGGLE FOR MOBILE ---
function initSidebarToggle() {
    const menuBtn = document.getElementById("menu-toggle-btn");
    const closeBtn = document.getElementById("sidebar-close-btn");
    
    if (!menuBtn) return;
    
    menuBtn.addEventListener("click", () => {
        document.body.classList.add("sidebar-active");
    });
    
    if (closeBtn) {
        closeBtn.addEventListener("click", () => {
            document.body.classList.remove("sidebar-active");
        });
    }
    
    // Close sidebar when clicking outside (on overlay)
    document.addEventListener("click", (e) => {
        if (document.body.classList.contains("sidebar-active") && 
            !e.target.closest("aside") && 
            !e.target.closest("#menu-toggle-btn")) {
            document.body.classList.remove("sidebar-active");
        }
    });
}
