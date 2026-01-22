import './bootstrap';
import Alpine from 'alpinejs';
import Collapse from '@alpinejs/collapse';
import Toastify from 'toastify-js';
import "toastify-js/src/toastify.css";

Alpine.plugin(Collapse);
window.Alpine = Alpine;
window.Toastify = Toastify;

Alpine.start();
