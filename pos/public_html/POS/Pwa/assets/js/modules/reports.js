export const ReportsModule = {
    update(sales) {
        const total = sales.reduce((sum, s) => sum + s.total, 0);
        const el = document.getElementById('report-today-sales');
        if(el) el.innerText = total.toLocaleString() + ' Ks';
    }
};