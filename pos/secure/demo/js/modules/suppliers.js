class SuppliersModule {
    static render(container) {
        container.innerHTML = `<div class="grid grid-cols-1 md:grid-cols-3 gap-4">${Store.state.suppliers.map(s => `<div class="glass-card p-5 rounded-2xl border border-slate-800"><h4 class="font-bold text-white">${s.name}</h4><p class="text-xs text-slate-400 mt-1">${s.phone}</p></div>`).join('')}</div>`;
    }
}