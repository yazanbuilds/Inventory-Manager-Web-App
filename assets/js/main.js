/**
 * Abaya Wholesale Manager
 * Main Javascript Module
 */

document.addEventListener('DOMContentLoaded', () => {
    // Route initialization based on DOM elements
    if (document.getElementById('salesForm')) initSales();
    if (document.getElementById('productsTable') || document.getElementById('productsCardView')) initProducts();
    if (document.getElementById('addVendorForm')) initVendors();
    if (document.getElementById('paymentsAccordion') || document.getElementById('addPaymentForm')) initPayments();
    if (document.querySelector('.viewInvoiceBtn') || document.querySelector('.deleteInvoiceBtn')) initInvoices();
    if (document.getElementById('salesChart')) initReports();
});

// ==========================================
// Global Functions (called via inline onclick)
// ==========================================

window.openAddProduct = function () {
    window.location.href = 'products.php?action=add';
};

window.openAddVendor = function () {
    window.location.href = 'vendors.php?action=add';
};


// ==========================================
// Sales Module
// ==========================================
function initSales() {
    let invoiceItems = [];

    // Custom Fuzzy Search Algorithm for Select2
    function fuzzyMatch(params, data) {
        if (jQuery.trim(params.term) === '') return data;
        if (typeof data.text === 'undefined') return null;

        var searchTerms = params.term.toLowerCase().split(/\s+/);
        var textNoSpaces = data.text.replace(/\s+/g, '').toLowerCase();

        for (var i = 0; i < searchTerms.length; i++) {
            if (textNoSpaces.indexOf(searchTerms[i]) === -1) {
                return null; // A word was missing
            }
        }
        return data; // Passed all checks!
    }

    // Initialize Select2
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        jQuery('#vendorSelect').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search vendor by name or phone...',
            allowClear: true,
            width: '100%',
            tags: true,
            createTag: function (params) {
                return {
                    id: params.term,
                    text: params.term,
                    newTag: true
                };
            },
            matcher: fuzzyMatch
        });

        jQuery('#productSelect').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search product by name or ID...',
            allowClear: true,
            width: '100%',
            tags: true,
            createTag: function (params) {
                return {
                    id: params.term,
                    text: params.term,
                    newTag: true
                };
            },
            matcher: fuzzyMatch
        });
    }

    // Vendor selection
    jQuery('#vendorSelect').on('select2:select', function (e) {
        var data = e.params.data;
        if (data.newTag) {
            // New Vendor! Prompt for phone
            var phone = prompt("New Vendor Detected.\nPlease enter phone number for \"" + data.text + "\":");
            if (phone !== null) {
                jQuery.post('api/vendors-api.php', {
                    action: 'add',
                    vendor_name: data.text,
                    phone: phone,
                    credit: 0
                }, function (res) {
                    if (res.success) {
                        var newOption = new Option(data.text + " - " + phone + " (Credit: Rs. 0.00)", res.vendor_id, false, true);
                        jQuery(newOption).attr('data-credit', '0');
                        jQuery(newOption).attr('data-phone', phone);
                        jQuery('#vendorSelect').append(newOption).trigger('change');

                        document.getElementById('previousCredit').value = 0;
                        renderInvoiceItems();
                    } else {
                        alert('Error creating vendor: ' + res.error);
                        jQuery('#vendorSelect').val(null).trigger('change');
                    }
                }, 'json');
            } else {
                jQuery('#vendorSelect').val(null).trigger('change');
            }
            return;
        }
    });

    jQuery('#vendorSelect').on('change', function () {
        const vendorId = jQuery(this).val();
        const displayInput = document.getElementById('previousCreditDisplay'); // NEW

        if (!vendorId) {
            document.getElementById('previousCredit').value = 0;
            if (displayInput) displayInput.value = 'AED 0.00'; // NEW
            renderInvoiceItems();
            return;
        }

        if (jQuery(this).find('option:selected').attr('data-select2-tag')) { return; }

        const selected = document.querySelector(`#vendorSelect option[value="${vendorId}"]`);
        if (selected) {
            const credit = selected.getAttribute('data-credit') || 0;
            document.getElementById('previousCredit').value = credit;
            if (displayInput) displayInput.value = 'AED ' + parseFloat(credit).toFixed(2); // NEW
        }
        renderInvoiceItems();
    });

    // Product selection — default to 0 (cost price is hidden from customer)
    jQuery('#productSelect').on('change', function () {
        const productId = jQuery(this).val();
        if (!productId) {
            document.getElementById('productPrice').value = 0;
            return;
        }
        // Do NOT auto-fill with saved cost price — user must enter selling price
        document.getElementById('productPrice').value = 0;
    });

    // Add product button click (with double-click prevention)
    const addProductBtn = document.getElementById('addProductBtn');
    if (addProductBtn) {
        addProductBtn.addEventListener('click', function () {
            // === DOUBLE-CLICK GUARD ===
            if (this.disabled) return;
            this.disabled = true;
            const reenableBtn = () => { setTimeout(() => { addProductBtn.disabled = false; }, 300); };

            const $productSelect = jQuery('#productSelect');
            const productId = $productSelect.val();
            if (!productId) {
                alert('Please select a product');
                reenableBtn();
                return;
            }

            const $selected = $productSelect.find('option:selected');
            if (!$selected.length || !$selected.val()) {
                alert('Product not found');
                reenableBtn();
                return;
            }

            const itemType = document.getElementById('itemType').value;
            const qty = parseInt(document.getElementById('productQty').value) || 1;
            const price = parseFloat(document.getElementById('productPrice').value) || 0;

            if (qty <= 0) {
                alert('Quantity must be greater than 0');
                reenableBtn();
                return;
            }
            if (price <= 0) {
                alert('Price must be greater than 0');
                reenableBtn();
                return;
            }

            // ==========================================
            // AUTO CREATE PRODUCT INTERCEPTOR
            // ==========================================
            if ($selected.attr('data-select2-tag')) {
                const newProductName = $selected.text();
                jQuery.post('api/products-api.php', {
                    action: 'add_auto',
                    product_name: newProductName,
                    unit_price: price
                }, function (res) {
                    if (res.success) {
                        const newId = res.product_id;
                        var newOption = new Option(newProductName + " - " + newId + " (Stock: 100)", newId, false, true);
                        jQuery(newOption).attr('data-price', price);
                        jQuery(newOption).attr('data-name', newProductName);
                        jQuery(newOption).attr('data-stock', '100');
                        $productSelect.append(newOption);

                        // === MERGE DUPLICATE CHECK (auto-create path) ===
                        const existingIndex = invoiceItems.findIndex(
                            item => item.productId == newId && item.type === itemType
                        );
                        if (existingIndex !== -1) {
                            invoiceItems[existingIndex].quantity += qty;
                            invoiceItems[existingIndex].total = invoiceItems[existingIndex].quantity * invoiceItems[existingIndex].unitPrice;
                        } else {
                            invoiceItems.push({
                                type: itemType,
                                productId: newId,
                                productName: newProductName,
                                quantity: qty,
                                unitPrice: price,
                                total: qty * price
                            });
                        }

                        renderInvoiceItems();
                        $productSelect.val(null).trigger('change');
                        document.getElementById('productQty').value = 1;
                        document.getElementById('productPrice').value = 0;
                        document.getElementById('productSelect').focus();
                    } else {
                        alert("Failed to auto-create product: " + res.error);
                    }
                    reenableBtn();
                }, 'json');
                return; // halt and wait for async callback
            }

            const stock = parseInt($selected.attr('data-stock') || 0);
            const productName = $selected.attr('data-name');

            // Check stock for sales (not for returns)
            if (itemType === 'sale' && typeof window.IGNORE_STOCK_LIMITS === 'undefined' && qty > stock) {
                // Also double check against actual string "999999" just in case window variable failed to inject
                if (stock !== 999999 && $selected.text().indexOf('Unlimited') === -1) {
                    alert(`Insufficient stock! Available: ${stock}`);
                    reenableBtn();
                    return;
                }
            }

            // === MERGE DUPLICATE CHECK ===
            const existingIndex = invoiceItems.findIndex(
                item => item.productId == productId && item.type === itemType
            );
            if (existingIndex !== -1) {
                // Merge: add quantity, keep original price
                invoiceItems[existingIndex].quantity += qty;
                invoiceItems[existingIndex].total = invoiceItems[existingIndex].quantity * invoiceItems[existingIndex].unitPrice;
            } else {
                invoiceItems.push({
                    type: itemType,
                    productId: productId,
                    productName: productName,
                    quantity: qty,
                    unitPrice: price,
                    total: qty * price
                });
            }

            renderInvoiceItems();

            // Reset fields
            $productSelect.val(null).trigger('change');
            document.getElementById('productQty').value = 1;
            document.getElementById('productPrice').value = 0;
            reenableBtn();
        });
    }

    // Must make removeItem globally accessible since it is inline called
    window.removeInvoiceItem = function (index) {
        invoiceItems.splice(index, 1);
        renderInvoiceItems();
    };

    // === INLINE EDIT: switch row to edit mode ===
    window.editInvoiceItem = function (index) {
        const item = invoiceItems[index];
        if (!item) return;

        // We now look for the card instead of a table row
        const card = document.querySelector(`.product-item-card[data-index="${index}"]`);
        if (!card) return;

        // Target the containers inside the card
        const fieldsContainer = card.querySelector('.d-flex.gap-3.text-muted');
        const actionsContainer = card.querySelector('.inv-actions');

        // Swap text for input fields
        fieldsContainer.innerHTML = `
            <div class="d-flex gap-2 align-items-center">
                <input type="number" class="form-control form-control-sm" value="${item.quantity}" min="1" id="editQty_${index}" style="width:70px" placeholder="Qty">
                <input type="number" step="0.01" class="form-control form-control-sm" value="${item.unitPrice.toFixed(2)}" min="0" id="editPrice_${index}" style="width:100px" placeholder="Price">
            </div>
        `;

        // Swap edit/delete buttons for save/cancel buttons
        actionsContainer.innerHTML = `
            <button type="button" class="btn btn-sm btn-success me-1 text-white" onclick="saveInvoiceItemEdit(${index})" title="Save"><i class="bi bi-check-lg"></i></button>
            <button type="button" class="btn btn-sm" style="background: #f3f4f6;" onclick="renderInvoiceItems()" title="Cancel"><i class="bi bi-x-lg"></i></button>
        `;
    };

    // === INLINE EDIT: save edited values ===
    window.saveInvoiceItemEdit = function (index) {
        const qtyInput = document.getElementById(`editQty_${index}`);
        const priceInput = document.getElementById(`editPrice_${index}`);
        if (!qtyInput || !priceInput) return;

        const newQty = parseInt(qtyInput.value) || 0;
        const newPrice = parseFloat(priceInput.value) || 0;

        if (newQty <= 0) { alert('Quantity must be greater than 0'); return; }
        if (newPrice <= 0) { alert('Price must be greater than 0'); return; }

        invoiceItems[index].quantity = newQty;
        invoiceItems[index].unitPrice = newPrice;
        invoiceItems[index].total = newQty * newPrice;

        // Re-render the cards to show updated values
        renderInvoiceItems();
    };

    function renderInvoiceItems() {
        const container = document.getElementById('invoiceItemsContainer');
        const countBadge = document.getElementById('itemCount');

        if (invoiceItems.length === 0) {
            container.innerHTML = '<div class="text-center text-muted p-4">No products added yet</div>';
            if (countBadge) countBadge.textContent = '0';

            updateTotals(0, 0);
            return;
        }

        if (countBadge) countBadge.textContent = invoiceItems.length;

        container.innerHTML = invoiceItems.map((item, index) => {
            const isSale = item.type === 'sale';
            const badgeClass = isSale ? 'badge-sale' : 'badge-return';
            const badgeText = isSale ? 'SALE' : 'RETURN';
            // Display negatives for returns to match image
            const displayTotal = isSale ? item.total.toFixed(2) : `(${item.total.toFixed(2)})`;
            const colorStyle = isSale ? '' : 'color: #991b1b;';

            return `
            <div class="product-item-card" data-index="${index}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-start gap-2">
                        <span class="badge ${badgeClass} mt-1">${badgeText}</span>
                        <h6 class="mb-0 text-dark fw-bold" style="font-size: 0.95rem;">${item.productName}</h6>
                    </div>
                    <div class="fw-bold" style="font-size: 1.05rem; ${colorStyle}">${displayTotal}</div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="d-flex gap-3 text-muted" style="font-size: 0.85rem;">
                        <span>Qty: <strong class="text-dark inv-qty">${item.quantity}</strong></span>
                        <span>Price: <strong class="text-dark inv-price">${item.unitPrice.toFixed(2)}</strong></span>
                    </div>
                    <div class="inv-actions d-flex gap-3">
                        <button type="button" class="btn btn-link p-0 text-success text-decoration-none" style="font-size: 0.75rem; font-weight: 700;" onclick="editInvoiceItem(${index})"><i class="bi bi-pencil-fill"></i> EDIT</button>
                        <button type="button" class="btn btn-link p-0 text-danger text-decoration-none" onclick="removeInvoiceItem(${index})"><i class="bi bi-trash-fill"></i></button>
                    </div>
                </div>
            </div>`;
        }).join('');

        const saleTotal = invoiceItems.filter(item => item.type === 'sale').reduce((sum, item) => sum + item.total, 0);
        const returnTotal = invoiceItems.filter(item => item.type === 'return').reduce((sum, item) => sum + item.total, 0);
        updateTotals(saleTotal, returnTotal);
    }

    function updateTotals(saleTotal, returnTotal) {
        const netTotal = saleTotal - returnTotal;
        const previousCredit = parseFloat(document.getElementById('previousCredit').value) || 0;
        const grandTotal = netTotal + previousCredit;

        // Note: Using AED to match your mockup. Change to Rs. if needed!
        document.getElementById('saleTotal').textContent = 'AED ' + saleTotal.toFixed(2);
        document.getElementById('returnTotal').textContent = 'AED (' + returnTotal.toFixed(2) + ')';
        document.getElementById('invoiceTotal').textContent = 'AED ' + netTotal.toFixed(2);

        const grandTotalFormatted = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('grandTotal').textContent = grandTotalFormatted;

        // Update sticky footer
        const footerNet = document.getElementById('footerNetSale');
        const footerPayable = document.getElementById('footerTotalPayable');
        if (footerNet) footerNet.textContent = 'AED ' + netTotal.toFixed(2);
        if (footerPayable) footerPayable.textContent = grandTotalFormatted;

        // NEW: Update Mobile Footer IDs
        const footerNetMobile = document.getElementById('footerNetSaleMobile');
        const footerPayableMobile = document.getElementById('footerTotalPayableMobile');
        if (footerNetMobile) footerNetMobile.textContent = 'AED ' + netTotal.toFixed(2);
        if (footerPayableMobile) footerPayableMobile.textContent = grandTotalFormatted;
    }


    const salesForm = document.getElementById('salesForm');
    if (salesForm) {
        salesForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            if (invoiceItems.length === 0) {
                alert('Please add at least one product');
                return;
            }

            const salesItems = invoiceItems.filter(item => item.type === 'sale');
            const returnItems = invoiceItems.filter(item => item.type === 'return');

            const formData = new FormData(this);
            formData.append('sales_items', JSON.stringify(salesItems));
            formData.append('return_items', JSON.stringify(returnItems));
            formData.append('action', 'create_with_returns');

            try {
                const response = await fetch('api/invoices-api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    window.location = 'invoices.php';
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Error creating invoice: ' + error.message);
            }
        });
    }
}


// ==========================================
// Products Module
// ==========================================
function initProducts() {
    function attachButtonListeners() {
        // Remove old listeners
        document.querySelectorAll('.editBtn').forEach(btn => {
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
        });
        document.querySelectorAll('.deleteBtn').forEach(btn => {
            const newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);
        });

        // Attach Edit
        document.querySelectorAll('.editBtn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('edit_product_id').value = this.dataset.id;
                document.getElementById('edit_product_name').value = this.dataset.name;
                document.getElementById('edit_unit_price').value = this.dataset.price;
                document.getElementById('edit_stock_qty').value = this.dataset.stock;

                if (typeof bootstrap !== 'undefined') {
                    new bootstrap.Modal(document.getElementById('editProductModal')).show();
                }
            });
        });

        // Attach Delete
        document.querySelectorAll('.deleteBtn').forEach(btn => {
            btn.addEventListener('click', async function () {
                if (!confirm('Delete this product?')) return;
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('product_id', this.dataset.id);

                try {
                    const response = await fetch('api/products-api.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success) location.reload();
                    else alert('Error: ' + (result.error || 'Failed to delete'));
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            });
        });
    }

    const addProductForm = document.getElementById('addProductForm');
    if (addProductForm) {
        addProductForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'add');

            try {
                const response = await fetch('api/products-api.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) location.reload();
                else alert('Error: ' + (result.error || 'Failed to add product'));
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });
    }

    const editProductForm = document.getElementById('editProductForm');
    if (editProductForm) {
        editProductForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'update');

            try {
                const response = await fetch('api/products-api.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) location.reload();
                else alert('Error: ' + (result.error || 'Failed to update product'));
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });
    }

    attachButtonListeners();

    // Search functionality
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    const idOnlyCheckbox = document.getElementById('idOnly');
    const sortSelect = document.getElementById('sortSelect');

    function performSearch() {
        if (!searchInput || !idOnlyCheckbox || !sortSelect) return;

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const searchValue = searchInput.value.trim();
            const idOnly = idOnlyCheckbox.checked;
            const sortBy = sortSelect.value;

            const url = new URL(window.location.href);
            if (searchValue) {
                url.searchParams.set('search', searchValue);
                if (idOnly) url.searchParams.set('idOnly', '1');
                else url.searchParams.delete('idOnly');
            } else {
                url.searchParams.delete('search');
                url.searchParams.delete('idOnly');
            }
            url.searchParams.set('sort', sortBy);

            fetch(url.toString())
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');

                    const newTableBody = newDoc.querySelector('#productsTable');
                    const currentTableBody = document.querySelector('#productsTable');
                    if (newTableBody && currentTableBody) currentTableBody.innerHTML = newTableBody.innerHTML;

                    const newCardView = newDoc.querySelector('#productsCardView');
                    const currentCardView = document.querySelector('#productsCardView');
                    if (newCardView && currentCardView) currentCardView.innerHTML = newCardView.innerHTML;

                    attachButtonListeners();
                    window.history.pushState({ search: searchValue, idOnly: idOnly, sort: sortBy }, '', url.toString());
                });
        }, 300);
    }

    if (searchInput) searchInput.addEventListener('input', performSearch);
    if (idOnlyCheckbox) idOnlyCheckbox.addEventListener('change', performSearch);
    if (sortSelect) sortSelect.addEventListener('change', performSearch);
    const searchForm = document.getElementById('searchForm');
    if (searchForm) searchForm.addEventListener('submit', (e) => e.preventDefault());
}


// ==========================================
// Vendors Module
// ==========================================
function initVendors() {
    const addVendorForm = document.getElementById('addVendorForm');
    if (addVendorForm) {
        addVendorForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Adding...';

            const formData = new FormData(this);
            formData.append('action', 'add');

            try {
                const response = await fetch('api/vendors-api.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Unknown error'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Add Vendor';
                }
            } catch (error) {
                alert('Network error. Please check your connection.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Add Vendor';
            }
        });
    }

    document.querySelectorAll('.editVendorBtn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('edit_vendor_id').value = this.dataset.id;
            document.getElementById('edit_vendor_name').value = this.dataset.name;
            document.getElementById('edit_phone').value = this.dataset.phone;
            document.getElementById('edit_credit').value = this.dataset.credit;
            document.getElementById('edit_notes').value = this.dataset.notes;
            if (typeof bootstrap !== 'undefined') new bootstrap.Modal(document.getElementById('editVendorModal')).show();
        });
    });

    const editVendorForm = document.getElementById('editVendorForm');
    if (editVendorForm) {
        editVendorForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating...';

            const formData = new FormData(this);
            formData.append('action', 'update');
            try {
                const response = await fetch('api/vendors-api.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) location.reload();
                else {
                    alert('Error: ' + (result.error || 'Unknown error'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Update Vendor';
                }
            } catch (error) {
                alert('Network error. Please check your connection.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update Vendor';
            }
        });
    }

    document.querySelectorAll('.deleteVendorBtn').forEach(btn => {
        btn.addEventListener('click', async function () {
            if (!confirm('Are you sure you want to delete this vendor?')) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('vendor_id', this.dataset.id);
            try {
                const response = await fetch('api/vendors-api.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) location.reload();
                else alert('Error: ' + (result.error || 'Unknown error'));
            } catch (error) {
                alert('Network error.');
            }
        });
    });

    document.querySelectorAll('.payVendorBtn').forEach(btn => {
        btn.addEventListener('click', function () {
            const vendorId = this.dataset.id;
            const vendorName = this.dataset.name;
            const amount = parseFloat(this.dataset.amount);

            document.getElementById('pay_vendor_id').value = vendorId;
            document.getElementById('pay_vendor_name').textContent = vendorName;
            document.getElementById('pay_vendor_amount').textContent = 'Rs. ' + amount.toFixed(2);
            document.getElementById('pay_amount_input').value = amount.toFixed(2);
            document.getElementById('pay_amount_input').max = amount;

            if (typeof bootstrap !== 'undefined') new bootstrap.Modal(document.getElementById('payVendorModal')).show();
        });
    });

    const payVendorForm = document.getElementById('payVendorForm');
    if (payVendorForm) {
        payVendorForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

            const formData = new FormData(e.target);
            formData.append('action', 'pay_vendor');
            try {
                const response = await fetch('api/vendors-api.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) location.reload();
                else {
                    alert('Error: ' + result.error);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-cash-coin"></i> Record Payment';
                }
            } catch (error) {
                alert('Error: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-cash-coin"></i> Record Payment';
            }
        });
    }
}


// ==========================================
// Payments Module
// ==========================================
function initPayments() {
    const paymentInvoiceId = document.getElementById('payment_invoice_id');
    if (paymentInvoiceId) {
        paymentInvoiceId.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            const balance = selected.dataset.balance || 0;
            const outBal = document.getElementById('outstanding_balance');
            if (outBal) outBal.value = 'Rs. ' + parseFloat(balance).toFixed(2);

            const payAmt = document.getElementById('payment_amount');
            if (payAmt) payAmt.max = balance;
        });
    }

    window.addPaymentToInvoice = function (invoiceId) {
        const sel = document.getElementById('payment_invoice_id');
        if (sel) {
            sel.value = invoiceId;
            sel.dispatchEvent(new Event('change'));
        }
        if (typeof bootstrap !== 'undefined') {
            new bootstrap.Modal(document.getElementById('addPaymentModal')).show();
        }
    };

    const addPaymentForm = document.getElementById('addPaymentForm');
    if (addPaymentForm) {
        addPaymentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

            const formData = new FormData(e.target);
            formData.append('action', 'add');

            try {
                const response = await fetch('api/payments-api.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Record Payment';
                }
            } catch (error) {
                alert('Error recording payment: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Record Payment';
            }
        });
    }
}


// ==========================================
// Invoices Module
// ==========================================
function initInvoices() {
    // View invoice details
    document.querySelectorAll('.viewInvoiceBtn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const invoiceId = btn.dataset.id;
            if (typeof bootstrap !== 'undefined') {
                new bootstrap.Modal(document.getElementById('invoiceModal')).show();
            }
            try {
                const response = await fetch(`api/invoices-api.php?action=details&invoice_id=${invoiceId}`);
                const result = await response.json();
                if (result.success) await displayInvoiceDetails(result);
            } catch (error) {
                document.getElementById('invoiceDetails').innerHTML = '<div class="alert alert-danger">Error loading invoice</div>';
            }
        });
    });

    async function displayInvoiceDetails(data) {
        const invoice = data.invoice;
        const items = data.items;
        const payments = data.payments;

        let returns = [];
        try {
            const returnResponse = await fetch(`api/returns-api.php?action=list&invoice_id=${invoice.InvoiceID}`);
            const returnResult = await returnResponse.json();
            if (returnResult.success) returns = returnResult.returns;
        } catch (e) {
            console.log('No returns found');
        }

        let html = `
            <div class="row mb-3">
                <div class="col-md-6">
                    <h6>Invoice #${invoice.InvoiceID}</h6>
                    <p><strong>Date:</strong> ${invoice.InvoiceDate}<br>
                    <strong>Vendor:</strong> ${invoice.VendorName}<br>
                    <strong>Phone:</strong> ${invoice.VendorPhone || '-'}</p>
                </div>
                <div class="col-md-6 text-end">
                    <p><strong>Previous Credit:</strong> Rs. ${parseFloat(invoice.PreviousCredit).toFixed(2)}<br>
                    <strong>Invoice Total:</strong> Rs. ${parseFloat(invoice.TotalAmount).toFixed(2)}<br>
                    <strong>Total Paid:</strong> Rs. ${parseFloat(invoice.PaidAmount).toFixed(2)}<br>
                    <strong>Balance:</strong> <span class="text-danger">Rs. ${(parseFloat(invoice.TotalAmount) + parseFloat(invoice.PreviousCredit) - parseFloat(invoice.PaidAmount)).toFixed(2)}</span></p>
                </div>
            </div>
            <h6>Products</h6>
            <table class="table table-sm table-bordered">
                <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
                <tbody>
        `;

        items.forEach(item => {
            html += `<tr><td>${item.ProductName}</td><td>${item.InvoiceQty}</td><td>Rs. ${parseFloat(item.InvoiceUnitPrice).toFixed(2)}</td><td>Rs. ${parseFloat(item.InvoiceTotalPrice).toFixed(2)}</td></tr>`;
        });
        html += '</tbody></table>';

        if (returns.length > 0) {
            let totalReturns = returns.reduce((sum, r) => sum + parseFloat(r.TotalPrice), 0);
            html += '<h6 class="mt-3 text-warning"><i class="bi bi-arrow-return-left"></i> Product Returns</h6>';
            html += '<table class="table table-sm table-bordered table-warning">';
            html += '<thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Date</th><th>Reason</th></tr></thead><tbody>';
            returns.forEach(r => {
                html += `<tr><td>${r.ProductName}</td><td>${r.Quantity}</td><td>Rs. ${parseFloat(r.UnitPrice).toFixed(2)}</td><td>Rs. ${parseFloat(r.TotalPrice).toFixed(2)}</td><td>${r.ReturnDate}</td><td>${r.Reason || '-'}</td></tr>`;
            });
            html += `</tbody><tfoot><tr><th colspan="3" class="text-end">Total Returns:</th><th colspan="3" class="text-danger">Rs. ${totalReturns.toFixed(2)}</th></tr></tfoot></table>`;
        }

        if (payments.length > 0) {
            html += '<h6 class="mt-3">Payment History</h6><table class="table table-sm table-bordered">';
            html += '<thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Notes</th></tr></thead><tbody>';
            payments.forEach(p => {
                html += `<tr><td>${p.PaymentDate}</td><td>Rs. ${parseFloat(p.Amount).toFixed(2)}</td><td>${p.PaymentMethod}</td><td>${p.Notes || '-'}</td></tr>`;
            });
            html += '</tbody></table>';
        }

        const detailsContainer = document.getElementById('invoiceDetails');
        if (detailsContainer) detailsContainer.innerHTML = html;
    }

    window.addPayment = function (invoiceId, balance) {
        document.getElementById('payment_invoice_id').value = invoiceId;
        const amtInput = document.getElementById('payment_amount_input');
        if (amtInput && balance !== undefined) {
            amtInput.value = parseFloat(balance).toFixed(2);
            amtInput.max = parseFloat(balance).toFixed(2);
        }
        if (typeof bootstrap !== 'undefined') new bootstrap.Modal(document.getElementById('paymentModal')).show();
    };

    document.querySelectorAll('.returnProductBtn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const invoiceId = this.dataset.id;
            document.getElementById('return_invoice_id').value = invoiceId;
            try {
                const response = await fetch(`api/invoices-api.php?action=details&invoice_id=${invoiceId}`);
                const result = await response.json();
                if (result.success) {
                    const select = document.getElementById('return_product_select');
                    select.innerHTML = '<option value="">-- Select Product --</option>';

                    let hasReturnableItems = false;
                    result.items.forEach(item => {
                        const remaining = parseInt(item.RemainingReturnableQty);
                        // Only show products that still have remaining qty to return
                        if (remaining <= 0) return;
                        hasReturnableItems = true;

                        const option = document.createElement('option');
                        option.value = item.ProductID;
                        // Store remaining (not total) as the max returnable quantity
                        option.dataset.quantity = remaining;
                        option.dataset.invoiceQty = item.InvoiceQty;
                        option.dataset.alreadyReturned = item.AlreadyReturnedQty;
                        // CRITICAL: always use InvoiceUnitPrice (the price at time of sale), NOT catalog price
                        option.dataset.invoicePrice = item.InvoiceUnitPrice;
                        option.dataset.catalogPrice = item.CatalogUnitPrice;
                        option.textContent = `${item.ProductName} — ${remaining} remaining (${item.AlreadyReturnedQty} already returned of ${item.InvoiceQty})`;
                        select.appendChild(option);
                    });

                    if (!hasReturnableItems) {
                        select.innerHTML = '<option value="" disabled>All products fully returned</option>';
                    }
                    if (typeof bootstrap !== 'undefined') new bootstrap.Modal(document.getElementById('returnProductModal')).show();
                }
            } catch (error) {
                alert('Error loading invoice items');
            }
        });
    });

    const returnProductSelect = document.getElementById('return_product_select');
    if (returnProductSelect) {
        returnProductSelect.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            if (selected.value) {
                // dataset.quantity is already RemainingReturnableQty from the API
                const remainingQty = parseInt(selected.dataset.quantity);
                const invoiceQty = parseInt(selected.dataset.invoiceQty || remainingQty);
                const alreadyReturned = parseInt(selected.dataset.alreadyReturned || 0);

                // Always pull from InvoiceUnitPrice — the locked price from time of sale
                const invoicePrice = parseFloat(selected.dataset.invoicePrice || 0);
                const catalogPrice = parseFloat(selected.dataset.catalogPrice || 0);

                // Update quantity field
                document.getElementById('max_return_qty').textContent =
                    `${remainingQty} (${alreadyReturned} of ${invoiceQty} already returned)`;
                document.getElementById('return_quantity').max = remainingQty;
                document.getElementById('return_quantity').value = 1;

                // Display the locked invoice price
                document.getElementById('return_unit_price').value = invoicePrice.toFixed(2);

                // Show a visual indicator if catalog price differs from invoice price
                const priceNote = document.getElementById('return_price_note');
                if (priceNote) {
                    if (invoicePrice !== catalogPrice && catalogPrice > 0) {
                        priceNote.innerHTML = `<i class="bi bi-lock-fill"></i> Locked to invoice price. Current catalog price: Rs. ${catalogPrice.toFixed(2)}`;
                        priceNote.className = 'form-text text-warning fw-semibold';
                    } else {
                        priceNote.innerHTML = `<i class="bi bi-lock-fill"></i> Price locked to original invoice`;
                        priceNote.className = 'form-text text-muted';
                    }
                }
            }
        });
    }

    const returnProductForm = document.getElementById('returnProductForm');
    if (returnProductForm) {
        returnProductForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
            const formData = new FormData(e.target);
            formData.append('action', 'add');
            try {
                const response = await fetch('api/returns-api.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) location.reload();
                else {
                    alert('Error: ' + result.error);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Process Return';
                }
            } catch (error) {
                alert('Error processing return: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Process Return';
            }
        });
    }

    document.querySelectorAll('.deleteInvoiceBtn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const invoiceId = this.dataset.id;
            const vendorName = this.dataset.vendor;
            if (!confirm(`Are you sure you want to delete Invoice #${invoiceId} for ${vendorName}?\n\nThis will:\n- Delete the invoice\n- Restore product quantities\n- Remove all payments\n- Update vendor credit`)) return;
            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('invoice_id', invoiceId);
                const response = await fetch('api/invoices-api.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) location.reload();
                else alert('Error: ' + result.error);
            } catch (error) {
                alert('Error deleting invoice: ' + error.message);
            }
        });
    });
}


// ==========================================
// Reports Module
// ==========================================
function initReports() {
    const canvas = document.getElementById('salesChart');
    if (!canvas) return;

    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        return;
    }

    try {
        const rawLabels = canvas.getAttribute('data-labels');
        const rawSales = canvas.getAttribute('data-sales');

        if (rawLabels && rawSales) {
            const labelsArray = JSON.parse(rawLabels);
            const salesArray = JSON.parse(rawSales);

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: labelsArray,
                    datasets: [{
                        label: 'Daily Sales (Rs.)',
                        data: salesArray,
                        borderColor: 'rgb(33, 150, 243)',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: true } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
    } catch (e) {
        console.error("Error parsing or rendering chart data:", e);
    }
}
