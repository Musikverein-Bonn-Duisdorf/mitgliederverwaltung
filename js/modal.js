/**
 * Shared modal host helpers (Melde modal.js core + entity-open, Archiv types).
 */
var modalCache = {};
var modalLoadingKey = null;

function closeModal() {
    var host = document.getElementById('ajaxModalHost');
    if(host) host.style.display = 'none';
}

function openModal(type, id, register) {
    var host = document.getElementById('ajaxModalHost');
    var content = document.getElementById('ajaxModalContent');
    if(!host || !content) return;

    var key = type + ':' + id;
    if(register) key += ':' + register;
    if(modalCache[key]) {
        content.innerHTML = modalCache[key];
        host.style.display = 'block';
        return;
    }

    content.innerHTML = '<div class="w3-container w3-padding w3-center"><p>Lade…</p></div>';
    host.style.display = 'block';
    modalLoadingKey = key;

    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if(xhr.readyState !== 4) return;
        if(modalLoadingKey !== key) return;
        if(xhr.status >= 200 && xhr.status < 300 && xhr.responseText) {
            modalCache[key] = xhr.responseText;
            content.innerHTML = xhr.responseText;
        }
        else if(xhr.responseText) {
            content.innerHTML = xhr.responseText;
        }
        else {
            content.innerHTML = '<div class="w3-container w3-padding"><header class="w3-container"><span onclick="closeModal()" class="w3-button w3-display-topright">&times;</span><h2>Fehler</h2></header><p>Modal konnte nicht geladen werden (HTTP '+xhr.status+').</p></div>';
        }
    };
    var url = 'getModal.php?type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id);
    if(register) url += '&register=' + encodeURIComponent(register);
    xhr.open('GET', url, true);
    xhr.send();
}

document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') closeModal();
});

/** Entity chips (.entity-open) → async openModal (UI-SHELL / Melde MELD-167). */
document.addEventListener('click', function(e) {
    var ent = e.target.closest ? e.target.closest('.entity-open') : null;
    if(!ent) return;
    e.preventDefault();
    e.stopPropagation();
    var type = ent.getAttribute('data-entity-type') || '';
    var id = parseInt(ent.getAttribute('data-entity-id') || '0', 10);
    if(!type || !(id > 0)) return;
    openModal(type, id);
}, true);

document.addEventListener('keydown', function(e) {
    if(e.key !== 'Enter' && e.key !== ' ') return;
    var ent = e.target.closest ? e.target.closest('.entity-open') : null;
    if(!ent) return;
    e.preventDefault();
    var type = ent.getAttribute('data-entity-type') || '';
    var id = parseInt(ent.getAttribute('data-entity-id') || '0', 10);
    if(!type || !(id > 0)) return;
    openModal(type, id);
});

/** Safety-net: page-local .w3-modal must sit outside .app-main. */
function liftPageModalsOutOfAppMain() {
    var main = document.querySelector('.app-main');
    var host = document.getElementById('ajaxModalHost');
    if(!main || !host || !host.parentNode) return;
    var modals = main.querySelectorAll('.w3-modal');
    for(var i = 0; i < modals.length; i++) {
        host.parentNode.insertBefore(modals[i], host);
    }
}
document.addEventListener('DOMContentLoaded', liftPageModalsOutOfAppMain);

/** Toggle masked IBAN ↔ full (SEPA list + person modal). */
document.addEventListener('click', function(e) {
    var btn = e.target.closest ? e.target.closest('.iban-reveal') : null;
    if(!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var full = btn.getAttribute('data-iban-full') || '';
    var masked = btn.getAttribute('data-iban-masked') || '';
    var revealed = btn.getAttribute('data-revealed') === '1';
    if(revealed) {
        btn.textContent = masked;
        btn.setAttribute('data-revealed', '0');
        btn.setAttribute('aria-label', 'IBAN anzeigen');
    }
    else {
        btn.textContent = full;
        btn.setAttribute('data-revealed', '1');
        btn.setAttribute('aria-label', 'IBAN verbergen');
    }
}, true);
