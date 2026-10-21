export const ProductsModule = {
    products: [],
    
    setProducts(data) {
        this.products = data;
        this.renderGrid();
    },

    renderGrid() {
        const grid = document.getElementById('pos-product-grid');
        if(!grid) return;
        
        grid.innerHTML = this.products.map(p => `
            <div class="bg-dark-900 border border-slate-800 rounded-2xl p-3 cursor-pointer">
                <h4 class="font-medium text-sm text-white">${p.name}</h4>
                <p class="text-indigo-400 text-xs mt-2">${p.price} Ks</p>
            </div>
        `).join('');
    }
};