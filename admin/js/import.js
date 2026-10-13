/* Drag-and-drop plus keyboard file zone for admin import pages. */
(function () {
    'use strict';

    var zone = document.getElementById('file-upload-area');
    var input = document.getElementById('import_file');
    var form = document.getElementById('upload-form');
    var info = document.getElementById('file-info');
    var nameEl = document.getElementById('file-name');
    var sizeEl = document.getElementById('file-size');
    if (!zone || !input) return;

    function formatSize(bytes) {
        if (!bytes) return '0 Bytes';
        var units = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(i ? 1 : 0) + ' ' + units[i];
    }

    function acceptOk(file) {
        var accept = (input.getAttribute('accept') || '').toLowerCase();
        if (!accept) return true;
        var name = (file.name || '').toLowerCase();
        return accept.split(',').some(function (part) {
            part = part.trim();
            return part && name.slice(-part.length) === part;
        });
    }

    function useFile(file) {
        if (!file) return;
        if (!acceptOk(file)) {
            if (window.xpmAlert) window.xpmAlert('Choose a file in one of the supported formats.');
            else if (window.xpmToast) window.xpmToast('Choose a supported file type.', 'error');
            return;
        }
        if (nameEl) nameEl.textContent = file.name;
        if (sizeEl) sizeEl.textContent = formatSize(file.size);
        if (info) info.hidden = false;
        if (form) form.submit();
    }

    zone.addEventListener('click', function (event) {
        if (event.target.closest('button, a, input')) return;
        input.click();
    });
    zone.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        input.click();
    });
    zone.addEventListener('dragover', function (event) {
        event.preventDefault();
        zone.classList.add('is-dragover');
    });
    zone.addEventListener('dragleave', function () {
        zone.classList.remove('is-dragover');
    });
    zone.addEventListener('drop', function (event) {
        event.preventDefault();
        zone.classList.remove('is-dragover');
        var file = event.dataTransfer.files && event.dataTransfer.files[0];
        if (!file) return;
        try {
            var transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
        } catch (err) {
            useFile(file);
            return;
        }
        useFile(file);
    });
    input.addEventListener('change', function () {
        useFile(input.files[0]);
    });
})();
