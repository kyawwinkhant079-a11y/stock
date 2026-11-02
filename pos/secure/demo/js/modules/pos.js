class POSModule {
    static selectedCategory = 'all';
    static searchQuery = '';
    static paymentMethod = 'Cash';

    static render(container) {
        container.innerHTML = `
            <div class="h-full flex flex-col lg:flex-row gap-6">
                <div class="flex-1 flex flex-col h-full overflow-hidden">
                    <div class="flex flex-col sm:flex-row gap-3 mb-4">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input type="text" id="pos-search" oninput="POSModule.filterSearch(this.value)" placeholder="Search products..." class="w-full pl-10 pr-4 py-2.5 bg-slate-900/70 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-cyan-500">
                        </div>
                        <div id="pos-categories" class="flex gap-2 overflow-x-auto pb-1"></div>
                    </div>
                    <div class="flex-1 overflow-y-auto pr-1">
                        <div id="pos-product-grid" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4"></div>
                    </div>
                </div>
                <div class="w-full lg:w-96 glass-panel rounded-2xl flex flex-col h-auto lg:h-full border border-slate-800 shadow-xl overflow-hidden">
                    <div class="p-4 border-b border-slate-800 flex justify-between items-center">
                        <h3 class="font-bold text-sm text-white"><i class="fa-solid fa-cart-shopping text-cyan-400 mr-2"></i> Current Order</h3>
                        <button onclick="POSModule.clearCart()" class="text-xs text-red-400 hover:text-red-300"><i class="fa-solid fa-trash mr-1"></i> Clear</button>
                    </div>
                    <div id="cart-items-list" class="flex-1 overflow-y-auto p-4 space-y-3 divide-y divide-slate-800/40"></div>
                    <div class="p-4 border-t border-slate-800 bg-slate-900/40 space-y-3">
                        <div class="space-y-1.5 text-xs text-slate-300">
                            <div class="flex justify-between"><span>Subtotal</span><span id="cart-subtotal" class="font-semibold text-white">$0.00</span></div>
                            <div class="flex justify-between items-center">
                                <span>Discount ($)</span>
                                <input type="number" id="cart-discount" value="0" min="0" oninput="POSModule.calc()" class="w-20 px-2 py-1 bg-slate-900 border border-slate-700 rounded-lg text-right text-xs text-white">
                            </div>
                            <div class="flex justify-between text-sm font-bold text-white pt-2 border-t border-slate-800">
                                <span>Total</span><span id="cart-total" class="text-cyan-400 text-base">$0.00</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <button onclick="POSModule.setPM('Cash')" id="pm-Cash" class="pm-btn py-2 bg-cyan-500 text-slate-950 font-bold text-xs rounded-xl">Cash</button>
                            <button onclick="POSModule.setPM('KBZPay')" id="pm-KBZPay" class="pm-btn py-2 bg-slate-800 text-slate-300 font-bold text-xs rounded-xl">KBZPay</button>
                            <button onclick="POSModule.setPM('Card')" id="pm-Card" class="pm-btn py-2 bg-slate-800 text-slate-300 font-bold text-xs rounded-xl">Card</button>
                        </div>
                        <button onclick="POSModule.checkout()" class="w-full py-3 bg-gradient-to-r from-cyan-500 to-amber-500 text-slate-950 font-bold text-sm rounded-xl shadow-lg">Complete Checkout</button>
                    </div>
                </div>
            </div>`;
        POSModule.renderProducts();
        POSModule.renderCategories();
        POSModule.renderCart();
    }

    static renderProducts() {
        const grid = document.getElementById('pos-product-grid');
        if (!grid) return;
        grid.innerHTML = Store.state.products.filter(p => {
            const matchesCat = POSModule.selectedCategory === 'all' || p.category_id === POSModule.selectedCategory;
            const matchesSearch = p.name.toLowerCase().includes(POSModule.searchQuery.toLowerCase()) || p.barcode.includes(POSModule.searchQuery);
            return matchesCat && matchesSearch;
        }).map(p => `
            <div onclick="POSModule.addToCart('${p.id}')" class="glass-card p-4 rounded-2xl border border-slate-800 hover:border-cyan-500 cursor-pointer transition flex flex-col justify-between group">
                <div>
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-[10px] font-bold px-2 py-0.5 bg-slate-800 text-cyan-400 rounded-lg">Stock: ${p.stock}</span>
                        <span class="text-xs text-slate-400 font-mono">${p.barcode}</span>
                    </div>
                    <h4 class="font-bold text-sm text-white group-hover:text-cyan-300 line-clamp-2">${p.name}</h4>
                </div>
                <div class="flex items-center justify-between mt-4 pt-2 border-t border-slate-800">
                    <span class="text-base font-extrabold text-cyan-400">$${p.sale_price.toFixed(2)}</span>
                    <div class="w-8 h-8 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center text-xs group-hover:bg-cyan-500 group-hover:text-slate-950 transition"><i class="fa-solid fa-plus"></i></div>
                </div>
            </div>
        `).join('') || '<div class="col-span-full text-center py-12 text-slate-500 text-xs">No products available.</div>';
    }

    static renderCategories() {
        const container = document.getElementById('pos-categories');
        if (!container) return;
        container.innerHTML = `<button onclick="POSModule.filterCat('all')" class="px-4 py-2 ${POSModule.selectedCategory === 'all' ? 'bg-cyan-500 text-slate-950 font-semibold' : 'bg-slate-900 text-slate-300'} text-xs rounded-xl whitespace-nowrap">All</button>` +
            Store.state.categories.map(c => `
                <button onclick="POSModule.filterCat('${c.id}')" class="px-4 py-2 ${POSModule.selectedCategory === c.id ? 'bg-cyan-500 text-slate-950 font-semibold' : 'bg-slate-900 text-slate-300'} text-xs rounded-xl whitespace-nowrap">${c.name}</button>
            `).join('');
    }

    static filterCat(catId) { POSModule.selectedCategory = catId; POSModule.renderProducts(); POSModule.renderCategories(); }
    static filterSearch(q) { POSModule.searchQuery = q; POSModule.renderProducts(); }

    static addToCart(productId) {
        const product = Store.state.products.find(p => p.id === productId);
        if (!product) return;
        const existing = Store.state.cart.find(item => item.id === productId);
        if (existing) {
            if (existing.quantity < product.stock) { 
                existing.quantity++; 
                existing.subtotal = existing.quantity * existing.price; 
            }
        } else {
            Store.state.cart.push({ id: product.id, name: product.name, price: product.sale_price, quantity: 1, subtotal: product.sale_price });
        }
        Store.persist();
        POSModule.renderCart();
    }

    static renderCart() {
        const list = document.getElementById('cart-items-list');
        if (!list) return;
        if (Store.state.cart.length === 0) {
            list.innerHTML = `<div class="text-center py-12 text-slate-500"><i class="fa-solid fa-basket-shopping text-3xl mb-2 text-slate-600"></i><p class="text-xs">Cart is empty.</p></div>`;
            POSModule.calc();
            return;
        }
        list.innerHTML = Store.state.cart.map((item, idx) => `
            <div class="flex items-center justify-between py-2">
                <div class="flex-1 pr-2"><h5 class="text-xs font-bold text-white truncate">${item.name}</h5><p class="text-[10px] text-cyan-400">$${item.price.toFixed(2)}</p></div>
                <div class="flex items-center gap-2">
                    <button onclick="Store.state.cart[${idx}].quantity--; if(Store.state.cart[${idx}].quantity<=0) Store.state.cart.splice(${idx},1); else Store.state.cart[${idx}].subtotal=Store.state.cart[${idx}].quantity*Store.state.cart[${idx}].price; POSModule.renderCart();" class="w-6 h-6 rounded-lg bg-slate-800 text-slate-300">-</button>
                    <span class="text-xs font-bold text-white w-5 text-center">${item.quantity}</span>
                    <button onclick="Store.state.cart[${idx}].quantity++; Store.state.cart[${idx}].subtotal=Store.state.cart[${idx}].quantity*Store.state.cart[${idx}].price; POSModule.renderCart();" class="w-6 h-6 rounded-lg bg-slate-800 text-slate-300">+</button>
                </div>
            </div>
        `).join('');
        POSModule.calc();
    }

    static setPM(method) {
        POSModule.paymentMethod = method;
        document.querySelectorAll('.pm-btn').forEach(b => b.className = "pm-btn py-2 bg-slate-800 text-slate-300 font-bold text-xs rounded-xl");
        document.getElementById(`pm-${method}`).className = "pm-btn py-2 bg-cyan-500 text-slate-950 font-bold text-xs rounded-xl";
    }

    static calc() {
        const sub = Store.state.cart.reduce((s, i) => s + i.subtotal, 0);
        const disc = parseFloat(document.getElementById('cart-discount')?.value || 0);
        const total = Math.max(0, sub - disc);
        if (document.getElementById('cart-subtotal')) document.getElementById('cart-subtotal').innerText = `$${sub.toFixed(2)}`;
        if (document.getElementById('cart-total')) document.getElementById('cart-total').innerText = `$${total.toFixed(2)}`;
    }

    static clearCart() { Store.state.cart = []; POSModule.renderCart(); }

    static checkout() {
        if (Store.state.cart.length === 0) return alert('Cart is empty!');
        const sub = Store.state.cart.reduce((s, i) => s + i.subtotal, 0);
        const disc = parseFloat(document.getElementById('cart-discount').value || 0);
        const total = Math.max(0, sub - disc);

        const record = {
            id: 'INV-' + Math.floor(100000 + Math.random() * 900000),
            created_at: new Date().toISOString(),
            payment_method: POSModule.paymentMethod,
            final_amount: total,
            items: [...Store.state.cart]
        };

        Store.state.sales.unshift(record);
        Store.state.cart.forEach(ci => {
            const p = Store.state.products.find(x => x.id === ci.id);
            if (p) p.stock = Math.max(0, p.stock - ci.quantity);
        });
        Store.persist();
        alert(`Checkout Successful! Invoice: ${record.id}`);
        POSModule.clearCart();
    }
}