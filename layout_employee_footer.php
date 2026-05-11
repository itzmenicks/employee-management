</div>
</main>

<div class="modal-bg" id="deleteModal">
    <div class="modal">
        <h3>Confirm Delete</h3>
        <p id="deleteModalMsg">Are you sure?</p>
        <div class="modal-actions">
            <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            <a href="#" id="deleteConfirmBtn" class="btn btn-danger">Delete</a>
        </div>
    </div>
</div>

<script>
// Sound function
function playSound() {
    try {
        const audio = new Audio('notif.mp3.ogg');
        audio.volume = 1.0;
        audio.play();
    } catch(e) {}
}

function confirmDelete(url, name) {
    document.getElementById('deleteModalMsg').textContent = 'Delete "' + name + '"?';
    document.getElementById('deleteConfirmBtn').href = url;
    document.getElementById('deleteModal').classList.add('open');
}
function closeModal() {
    document.getElementById('deleteModal').classList.remove('open');
}

// Notification Bell
function toggleNotif() {
    const dd = document.getElementById('notifDropdown');
    dd.classList.toggle('open');
}
document.addEventListener('click', function(e) {
    const bell = document.getElementById('notifBell');
    const dd   = document.getElementById('notifDropdown');
    if (bell && dd && !bell.contains(e.target)) {
        dd.classList.remove('open');
    }
});

// Browser Push Notification
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}

function showBrowserNotif(title, body) {
    playSound();
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, { body: body });
    }
}

// Page load pe sound bajao agar unread hain
let lastCount = <?= $unreadCount ?? 0 ?>;
<?php if (($unreadCount ?? 0) > 0): ?>
window.addEventListener('load', function() {
    setTimeout(function() {
        playSound();
    }, 500);
});
<?php endif; ?>

// Har 15 second me check karo
setInterval(function() {
    fetch('notif_check.php')
        .then(r => r.json())
        .then(data => {
            if (data.count > lastCount) {
                showBrowserNotif('EMS Notification 🔔', data.latest);
                lastCount = data.count;
                let badge = document.querySelector('.notif-badge');
                if (badge) {
                    badge.textContent = data.count > 9 ? '9+' : data.count;
                } else {
                    const bell = document.getElementById('notifBell');
                    if (bell) {
                        const newBadge = document.createElement('div');
                        newBadge.className = 'notif-badge';
                        newBadge.textContent = data.count;
                        bell.appendChild(newBadge);
                    }
                }
            }
        }).catch(() => {});
}, 15000);
</script>
</body>
</html>