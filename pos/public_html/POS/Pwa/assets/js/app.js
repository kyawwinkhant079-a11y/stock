import { ApiService } from './services/api.service.js';
import { PosModule } from './modules/pos.js';
import { ProductsModule } from './modules/products.js';
import { CategoriesModule } from './modules/categories.js';
import { SalesModule } from './modules/sales.js';
import { ReportsModule } from './modules/reports.js';

window.addEventListener('DOMContentLoaded', async () => {
    PosModule.init();

    // Mock data သို့မဟုတ် API မှ ခေါ်ယူခြင်း
    try {
        const products = await ApiService.get('products').catch(() => [
            { id: 1, name: 'ရေသန့် (Alpha)', price: 500, stock: 100 },
            { id: 2, name: 'ကိုကာကိုလာ', price: 1200, stock: 50 }
        ]);
        ProductsModule.setProducts(products);
        CategoriesModule.init([{ id: 1, name: 'အားလုံး' }, { id: 2, name: 'သောက်စရာ' }]);
    } catch (e) {
        console.log('App initialized in offline mode.');
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js');
    }
});