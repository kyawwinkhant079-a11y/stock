class AuthModule {
    static init() {
        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            Store.state.currentUser = { name: 'Admin User', email: document.getElementById('login-email').value, role: 'super_admin' };
            document.getElementById('auth-view').classList.add('hidden');
            document.getElementById('app-wrapper').classList.remove('hidden');
            App.switchTab('pos');
        });
    }

    static logout() {
        Store.state.currentUser = null;
        document.getElementById('app-wrapper').classList.add('hidden');
        document.getElementById('auth-view').classList.remove('hidden');
    }
}
document.addEventListener('DOMContentLoaded', () => AuthModule.init());

