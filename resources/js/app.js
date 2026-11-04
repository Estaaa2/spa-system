import './bootstrap';
import Toastify from 'toastify-js';
import "toastify-js/src/toastify.css";
import '@fortawesome/fontawesome-free/css/all.min.css';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
// Leaflet guesses its marker image folder from CSS, which a Vite build breaks,
// so every default pin rendered as a broken image (UI rework Phase 4). Point it
// at the bundled files instead — the fix from Leaflet issue #4968.
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconRetinaUrl: markerIcon2x, iconUrl: markerIcon, shadowUrl: markerShadow });
window.L = L;

window.Toastify = Toastify;

window.showToast = function (message, type = 'success') {

    const isSuccess = type === 'success';

    Toastify({
        text: `
            <div class="flex items-center gap-3">
                <i class="${isSuccess
                    ? 'text-green-600 fa-solid fa-check-circle'
                    : 'text-red-600 fa-solid fa-circle-xmark'}">
                </i>
                <span class="${isSuccess
                    ? 'text-green-600'
                    : 'text-red-600'}">
                    ${message}
                </span>
            </div>
        `,
        duration: 3000,
        gravity: "top",
        position: "right",
        close: true,
        escapeMarkup: false,
        style: {
            background: "#ffffff",
            border: isSuccess
                ? "1px solid #16a34a"
                : "1px solid #dc2626",
            borderRadius: "10px",
            minWidth: "300px",
            display: "flex",
            alignItems: "center",
            boxShadow: "0 8px 20px rgba(0,0,0,0.08)"
        }
    }).showToast();
};
