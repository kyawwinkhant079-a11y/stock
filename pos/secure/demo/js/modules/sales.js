class SalesModule {
    static render(container) {
        container.innerHTML = `
            <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-900 text-slate-400 border-b border-slate-800">
                        <tr><th class="p-4">Invoice No</th><th class="p-4">Date</th><th class="p-4">Method</th><th class="p-4">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        ${Store.state.sales.map(s => `<tr><td class="p-4 font-mono text-cyan-400 font-bold">${s.id}</td><td class="p-4">${new Date(s.created_at).toLocaleString()}</td><td class="p-4">${s.payment_method}</td><td class="p-4 font-bold text-white">$${s.final_amount.toFixed(2)}</td></tr>`).join('') || '<tr><td colspan="4" class="p-6 text-center text-slate-500">No sales transactions.</td></tr>'}
                    </tbody>
                </table>
            </div>`;
    }
}