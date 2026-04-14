import './bootstrap';

import Alpine from 'alpinejs';
import './terms-gates';

window.Alpine = Alpine;

Alpine.start();

const initializeProcessingForms = () => {
    const forms = document.querySelectorAll('[data-processing-form]');

    forms.forEach((form) => {
        if (form.dataset.processingBound === 'true') {
            return;
        }

        form.dataset.processingBound = 'true';

        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-processing-button]');

            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.classList.add('is-processing');

            const idleText = button.querySelector('[data-button-idle]');
            const processingText = button.querySelector('[data-button-processing]');

            idleText?.classList.add('hidden');
            processingText?.classList.remove('hidden');
        });
    });
};

const resetProcessingButtons = () => {
    const buttons = document.querySelectorAll('[data-processing-button].is-processing');

    buttons.forEach((button) => {
        button.disabled = false;
        button.classList.remove('is-processing');

        const idleText = button.querySelector('[data-button-idle]');
        const processingText = button.querySelector('[data-button-processing]');

        idleText?.classList.remove('hidden');
        processingText?.classList.add('hidden');
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initializeProcessingForms();
    resetProcessingButtons();
});
document.addEventListener('livewire:navigated', () => {
    initializeProcessingForms();
    resetProcessingButtons();
});
window.addEventListener('pageshow', resetProcessingButtons);
