export const SalesModule = {
    history: [],
    init(sales) {
        this.history = sales;
        this.renderTable();
    },
    renderTable() {
        const tbody = document.getElementById('sales-table-body');
        if(!tbody) return;
        tbody.innerHTML = this.history.map(s => `
            <tr><td class="p-4 text-indigo-400">${s.id}</td><td class="p-4 text-white">${s.total} Ks</td></tr>
        `).join('');
    }
};