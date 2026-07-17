function registerCheckoutScroll() {
    Livewire.hook('morphed', ({ component, el }) => {
        if (component.name !== 'checkout-page') return;

        const error = el.querySelector('.border-red-500');
        if (error) {
            setTimeout(() => {
                error.scrollIntoView({ behavior: 'smooth', block: 'center' });
                error.focus();
            }, 50);
        }
    });
}

if (window.Livewire) {
    registerCheckoutScroll();
} else {
    document.addEventListener('livewire:initialized', () => registerCheckoutScroll());
}
