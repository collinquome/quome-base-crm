import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

const echoInstance = new Echo({
    broadcaster: "pusher",
    key: import.meta.env.VITE_PUSHER_APP_KEY || "crm-key",
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || "mt1",
    wsHost: import.meta.env.VITE_PUSHER_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_PUSHER_PORT || 6001,
    wssPort: import.meta.env.VITE_PUSHER_PORT || 6001,
    forceTLS: (import.meta.env.VITE_PUSHER_SCHEME || "http") === "https",
    enabledTransports: ["ws", "wss"],
    disableStats: true,
    authEndpoint: "/broadcasting/auth",
});

window.Echo = echoInstance;

export default {
    install(app) {
        app.config.globalProperties.$echo = echoInstance;

        app.provide("echo", echoInstance);
    },
};
