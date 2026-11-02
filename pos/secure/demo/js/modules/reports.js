class ReportsModule {
    static render(container) {
        const rev = Store.state.sales.reduce((a, b) => a + b.final_amount, 0);
        container.innerHTML = `<div class="glass-panel p-6 rounded-2xl border border-slate-800"><p class="text-xs text-slate-400">Total Revenue</p><h3 class="text-3xl font-bold text-cyan-400 mt-2">$${rev.toFixed(2)}</h3></div>`;
    }
}