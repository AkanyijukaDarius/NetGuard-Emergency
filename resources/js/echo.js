import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const API_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000';

window.Echo = new Echo({
    broadcaster: 'pusher', 
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: API_URL.replace(/https?:\/\//, '').split(':')[0], 
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${API_URL}/api/broadcasting/auth`,
    
    authorizer: (channel, options) => {
        return {
            authorize: (socketId, callback) => {
                axios.post(`${API_URL}/api/broadcasting/auth`, {
                    socket_id: socketId,
                    channel_name: channel.name
                }, {
                    headers: {
                        Authorization: `Bearer ${localStorage.getItem('token')}`,
                        Accept: 'application/json',
                    }
                })
                .then(response => {
                    callback(false, response.data);
                })
                .catch(error => {
                    callback(true, error);
                });
            }
        };
    },
});