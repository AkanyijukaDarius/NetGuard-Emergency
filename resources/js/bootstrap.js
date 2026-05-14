import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const API_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000';

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER, 
    forceTLS: true,
    disableStats: true,
    
    authEndpoint: `${API_URL}/api/broadcasting/auth`,
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('token')}`,
            Accept: 'application/json',
        },
    },
});