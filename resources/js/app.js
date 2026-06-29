import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const status = document.getElementById('status');
    if (status) {
        status.textContent = 'Laravel backend siap digunakan';
    }
});
