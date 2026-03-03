import './bootstrap';
import Alpine from 'alpinejs';
import Toastify from 'toastify-js';
import "toastify-js/src/toastify.css";

window.Alpine = Alpine;
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

Alpine.start();
