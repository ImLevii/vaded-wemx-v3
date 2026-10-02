import './bootstrap';
import { initFlowbite } from 'flowbite';

import toastr from "toastr";
import "toastr/build/toastr.min.css";

// make it global so inline scripts/blade can access it
window.toastr = toastr;

document.addEventListener('livewire:navigated', () => {
    initFlowbite();

    window.dispatchEvent(new CustomEvent('vaded-nav-close'));
});

document.addEventListener('click', (event) => {
    const navigationLink = event.target.closest('#client-main-navigation a');
    if (navigationLink) {
        window.dispatchEvent(new CustomEvent('vaded-nav-close'));
    }
});

document.addEventListener('livewire:navigating', () => {
    window.dispatchEvent(new CustomEvent('vaded-nav-close'));
});

// default options
toastr.options = {
    closeButton: true,
    progressBar: true,
    newestOnTop: true,
    preventDuplicates: false,
    timeOut: 4000,
    extendedTimeOut: 2000,
    positionClass: 'toast-bottom-right'
};

// listen for Livewire events
window.addEventListener('toast', (e) => {
    const { type = 'info', message = '', title = '' } = e.detail || {};
    toastr[type](message, title);
});
