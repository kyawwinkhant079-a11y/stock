class ProductsModule {
    static render(container) {
        container.innerHTML = `
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <input type="text" placeholder="Search products..." oninput="ProductsModule.filter(this.value)" class="w-80 px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white">
                    <button onclick="alert('Open add product modal')" class="px-4 py-2.5 bg-cyan-500 text-slate-950 font-semibold text-xs rounded-xl"><i class="fa-solid fa-plus mr-1"></i> Add Product</button>
                </div>
                <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-900 text-slate-400 border-b border-slate-800">
                            <tr><th class="p-4">Barcode</th><th class="p-4">Name</th><th class="p-4">Cost</th><th class="p-4">Price</th><th class="p-4">Stock</th></tr>
                        </thead>
                        <tbody id="prod-tbody" class="divide-y divide-slate-800/60"></tbody>
                    </table>
                </div>
            </div>`;
        ProductsModule.renderTable();
    }
    static renderTable() {
        const tbody = document.getElementById('prod-tbody');
        if (!tbody) return;
        tbody.innerHTML = Store.state.products.map(p => `
            <tr class="hover:bg-slate-900/40">
                <td class="p-4 font-mono text-slate-300">${p.barcode}</td>
                <td class="p-4 font-bold text-white">${p.name}</td>
                <td class="p-4 text-slate-300">$${p.cost_price.toFixed(2)}</td>
                <td class="p-4 text-cyan-400">$${p.sale_price.toFixed(2)}</td>
                <td class="p-4 font-bold text-white">${p.stock} units</td>
            </tr>
        `).join('');
    }
    static filter(q) { /* Filter implementation */ }
}