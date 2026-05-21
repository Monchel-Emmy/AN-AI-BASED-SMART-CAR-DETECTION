// Live clock
function updateClock() {
    const el = document.getElementById('clock');
    if (el) {
        const now = new Date();
        el.textContent = now.toLocaleString('en-US', {
            weekday: 'short', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit'
        });
    }
}
updateClock();
setInterval(updateClock, 1000);

// Image modal
function openImage(src) {
    const overlay = document.getElementById('imgModal');
    const img     = document.getElementById('modalImg');
    if (overlay && img) {
        img.src = src;
        overlay.classList.add('open');
    }
}

function closeModal() {
    const overlay = document.getElementById('imgModal');
    if (overlay) overlay.classList.remove('open');
}

document.addEventListener('click', function(e) {
    if (e.target.id === 'imgModal') closeModal();
});

// Auto-dismiss alerts
document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(function() {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(function() { el.remove(); }, 500);
    }, 4000);
});

// Confirm delete
document.querySelectorAll('.confirm-delete').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to delete this record?')) {
            e.preventDefault();
        }
    });
});
