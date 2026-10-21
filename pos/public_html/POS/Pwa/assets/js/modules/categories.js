export const CategoriesModule = {
    categories: [],
    init(data) {
        this.categories = data;
        this.render();
    },
    render() {
        const container = document.getElementById('pos-categories');
        if(!container) return;
        container.innerHTML = this.categories.map(c => `
            <button class="px-4 py-2 rounded-xl text-xs bg-dark-900 text-slate-300">${c.name}</button>
        `).join('');
    }
};