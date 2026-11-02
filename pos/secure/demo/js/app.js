class App {
    static currentModule = 'pos';

    static init() {
        window.addEventListener('online', () => document.getElementById('offline-banner').classList.add('hidden'));
        window.addEventListener('offline', () => document.getElementById('offline-banner').classList.remove('hidden'));
        
        App.renderNavigation();
    }

    static renderNavigation() {
        const navItems = [
            { id: 'pos', label: 'POS Terminal', icon: 'fa-cash-register' },
            { id: 'products', label: 'Products & Stock', icon: 'fa-boxes-stacked' },
            { id: 'sales', label: 'Sales History', icon: 'fa-receipt' },
            { id: 'suppliers', label: 'Suppliers', icon: 'fa-truck-field' },
            { id: 'reports', label: 'Reports', icon: 'fa-chart-pie' },
            { id: 'shops', label: 'Branches', icon: 'fa-store' },
            { id: 'users', label: 'Staff Users', icon: 'fa-users' }
        ];

        const sidebar = document.getElementById('sidebar-nav');
        sidebar.innerHTML = navItems.map(i => `
            <button onclick="App.switchTab('${i.id}')" class="nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition text-slate-300 hover:bg-slate-800/50 hover:text-cyan-400 ${App.currentModule === i.id ? 'active-nav' : ''}" data-tab="${i.id}">
                <i class="fa-solid ${i.icon} w-5"></i> ${i.label}
            </button>
        `).join('');

        const mobNav = document.getElementById('mobile-nav');
        mobNav.innerHTML = navItems.slice(0, 4).map(i => `
            <button onclick="App.switchTab('${i.id}')" class="flex flex-col items-center ${App.currentModule === i.id ? 'text-cyan-400' : 'text-slate-400'} text-[10px] gap-1">
                <i class="fa-solid ${i.icon} text-base"></i> ${i.label}
            </button>
        `).join('');
    }

    static switchTab(moduleId) {
        App.currentModule = moduleId;
        App.renderNavigation();

        const container = document.getElementById('main-content-area');
        container.innerHTML = '';

        switch(moduleId) {
            case 'pos': POSModule.render(container); break;
            case 'products': ProductsModule.render(container); break;
            case 'sales': SalesModule.render(container); break;
            case 'suppliers': SuppliersModule.render(container); break;
            case 'reports': ReportsModule.render(container); break;
            case 'shops': ShopsModule.render(container); break;
            case 'users': UsersModule.render(container); break;
        }
    }

    static changeBranch(branchId) {
        Store.state.activeBranchId = branchId;
        document.getElementById('sidebar-branch-name').innerText = branchId === 'b1' ? 'Downtown Branch' : 'City Mall Branch';
        App.switchTab(App.currentModule);
    }

    static syncWithServer() {
        const icon = document.getElementById('sync-icon');
        icon.classList.add('fa-spin');
        setTimeout(() => {
            icon.classList.remove('fa-spin');
            alert('Local data successfully synchronized with server backend!');
        }, 800);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Check initial auth state or start at login
});