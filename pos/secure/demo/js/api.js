class ApiService {
    static async request(actionCode, payload = {}) {
        try {
            const response = await fetch('./index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: actionCode, ...payload })
            });
            return await response.json();
        } catch (error) {
            console.warn('Network offline. Switching to local state store.', error);
            return { status: 503, message: 'Offline mode active' };
        }
    }
}