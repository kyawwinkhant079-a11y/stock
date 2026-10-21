import { StorageService } from '../services/storage.service.js';

export const PosModule = {
    cart: [],
    
    init() {
        this.cart = StorageService.get('pos_cart') || [];
        this.renderCart();
    },

    addToCart(product) {
        const existing = this.cart.find(item => item.id === product.id);
        if (existing) {
            existing.qty++;
        } else {
            this.cart.push({ ...product, qty: 1 });
        }
        StorageService.save('pos_cart', this.cart);
        this.renderCart();
    },

    renderCart() {
        const container = document.getElementById('cart-items-container');
        if (!container) return;
        
        if (this.cart.length === 0) {
            container.innerHTML = `<div class="text-center text-slate-500 py-16"><p class="text-sm">ခြင်းတောင်းထဲတွင် ပစ္စည်းမရှိသေးပါ</p></div>`;
            return;
        }
        
        container.innerHTML = this.cart.map(item => `
            <div class="bg-dark-800/50 border border-slate-800 rounded-xl p-3 flex items-center justify-between">
                <span class="text-white text-xs">${item.name} x${item.qty}</span>
                <span class="text-indigo-400 text-xs">${(item.price * item.qty).toLocaleString()} Ks</span>
            </div>
        `).join('');
    }
};