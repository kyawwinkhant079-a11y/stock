class Store {
    static state = {
        currentUser: null,
        activeBranchId: 'b1',
        cart: [],
        categories: [
            { id: 'c1', name: 'Beverages' },
            { id: 'c2', name: 'Snacks' },
            { id: 'c3', name: 'Electronics' }
        ],
        products: [
            { id: 'p1', category_id: 'c1', name: 'Coca Cola 330ml', barcode: '885012301', cost_price: 0.50, sale_price: 1.00, stock: 120 },
            { id: 'p2', category_id: 'c2', name: 'Potato Chips BBQ', barcode: '885012303', cost_price: 0.80, sale_price: 1.50, stock: 45 }
        ],
        sales: [],
        suppliers: [
            { id: 's1', name: 'Global Foods Co.', contact_person: 'John Smith', phone: '+1 555-0192', email: 'john@globalfoods.com' }
        ]
    };

    static init() {
        const saved = localStorage.getItem('nexuspos_store');
        if (saved) {
            try { Store.state = JSON.parse(saved); } catch(e) {}
        }
    }

    static persist() {
        localStorage.setItem('nexuspos_store', JSON.stringify(Store.state));
    }
}
Store.init();