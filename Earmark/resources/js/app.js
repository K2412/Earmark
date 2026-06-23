import liteparse from './alpine/liteparse.js';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('liteparse', liteparse);
});
