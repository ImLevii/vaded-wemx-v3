(() => {
    if (window.VadedImageLibrary) { window.VadedImageLibrary.mount(); return; }
    const instances = new Set();
    const formatSize = bytes => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
    const createElement = (tag, className, text) => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    };
    class ImageLibrary {
        constructor(root) {
            this.root = root;
            this.form = root.querySelector('[data-upload-form]');
            this.input = root.querySelector('[data-file-input]');
            this.dropzone = root.querySelector('[data-dropzone]');
            this.queueElement = root.querySelector('[data-upload-queue]');
            this.gallery = root.querySelector('[data-image-gallery]');
            this.submit = root.querySelector('[data-upload-button]');
            this.filename = root.querySelector('[data-file-name]');
            this.queue = [];
            this.busy = false;
            this.destroyed = false;
            this.dragDepth = 0;
            this.maxSize = Number(root.dataset.maxSize);
            this.maxFiles = Number(root.dataset.maxFiles);
            this.input.addEventListener('change', () => { this.addFiles(this.input.files); this.input.value = ''; });
            this.dropzone.addEventListener('keydown', event => {
                if (['Enter', ' '].includes(event.key)) { event.preventDefault(); if (!this.busy) this.input.click(); }
            });
            this.form.addEventListener('dragenter', event => {
                if (!this.isFileDrag(event)) return;
                event.preventDefault(); this.dragDepth++; this.dropzone.classList.add('is-dragging');
            });
            this.form.addEventListener('dragover', event => { if (this.isFileDrag(event)) { event.preventDefault(); event.dataTransfer.dropEffect = this.busy ? 'none' : 'copy'; } });
            this.form.addEventListener('dragleave', event => { event.preventDefault(); if (--this.dragDepth <= 0) { this.dragDepth = 0; this.dropzone.classList.remove('is-dragging'); } });
            this.form.addEventListener('drop', event => { event.preventDefault(); this.dragDepth = 0; this.dropzone.classList.remove('is-dragging'); this.addFiles(event.dataTransfer?.files || []); });
            this.preventFileNavigation = event => { if (this.isFileDrag(event) && this.root.isConnected) event.preventDefault(); };
            document.addEventListener('dragover', this.preventFileNavigation);
            document.addEventListener('drop', this.preventFileNavigation);
            this.form.addEventListener('submit', event => { event.preventDefault(); this.upload(); });
            root.addEventListener('click', event => this.onClick(event));
            root.addEventListener('error', event => {
                if (!event.target.matches('.vh-media-thumbnail img')) return;
                event.target.hidden = true;
                event.target.parentElement.querySelector('.vh-media-image-fallback').hidden = false;
            }, true);
            root.querySelector('[data-image-search]').addEventListener('input', () => this.filterGallery());
            root.querySelector('[data-image-sort]').addEventListener('change', () => this.filterGallery());
            try { root.querySelector('[data-picker-notice]').hidden = !window.opener?.imagePickerTarget; } catch {}
            this.refreshQueue(); this.filterGallery();
        }
        isFileDrag(event) { return Array.from(event.dataTransfer?.types || []).includes('Files'); }
        feedback(message, error = false) {
            const element = this.root.querySelector('[data-upload-feedback]');
            element.hidden = !message; element.textContent = message; element.classList.toggle('is-error', error);
        }
        addFiles(files) {
            if (this.busy) return;
            const messages = [];
            for (const file of Array.from(files)) {
                if (!/\.(png|jpe?g|gif|webp)$/i.test(file.name) || (file.type && !['image/png', 'image/jpeg', 'image/gif', 'image/webp'].includes(file.type))) { messages.push(`${file.name}: use PNG, JPG, GIF or WebP.`); continue; }
                if (file.size > this.maxSize) { messages.push(`${file.name}: exceeds the ${formatSize(this.maxSize)} limit.`); continue; }
                if (file.size === 0) { messages.push(`${file.name}: this file is empty.`); continue; }
                if (this.queue.some(entry => entry.file.name === file.name && entry.file.size === file.size && entry.file.lastModified === file.lastModified)) continue;
                if (this.queue.length >= this.maxFiles) { messages.push(`Choose up to ${this.maxFiles} files. Clear the queue to add more.`); break; }
                this.queue.push({ file, preview: URL.createObjectURL(file), status: 'ready', error: '' });
            }
            this.feedback(messages.join(' '), messages.length > 0); this.refreshQueue();
        }
        refreshQueue() {
            this.queueElement.replaceChildren();
            this.queue.forEach((entry, index) => {
                const row = createElement('div', `vh-media-queue-item is-${entry.status}`);
                const image = createElement('img'); image.src = entry.preview; image.alt = ''; image.onerror = () => { image.hidden = true; };
                const details = createElement('div', 'vh-media-queue-details');
                details.append(createElement('strong', '', entry.file.name), createElement('small', '', entry.error || `${formatSize(entry.file.size)} · ${entry.status === 'done' ? 'Uploaded' : entry.status === 'uploading' ? 'Uploading…' : 'Ready to upload'}`));
                const remove = createElement('button', 'vh-media-queue-remove'); remove.type = 'button'; remove.dataset.removeUpload = String(index); remove.setAttribute('aria-label', `Remove ${entry.file.name}`); remove.disabled = this.busy;
                remove.append(createElement('i', 'ti ti-x'));
                row.append(image, details, remove); this.queueElement.append(row);
            });
            const pending = this.queue.filter(entry => entry.status !== 'done');
            this.submit.disabled = this.busy || pending.length === 0;
            this.input.disabled = this.busy;
            this.dropzone.setAttribute('aria-disabled', String(this.busy));
            this.filename.disabled = this.busy || pending.length > 1;
            this.root.querySelector('[data-clear-queue]').hidden = this.queue.length === 0;
            this.root.querySelector('[data-clear-queue]').disabled = this.busy;
            this.root.querySelector('[data-queue-summary]').textContent = pending.length ? `${pending.length} ${pending.length === 1 ? 'image' : 'images'} selected · ${formatSize(pending.reduce((total, entry) => total + entry.file.size, 0))}` : this.queue.length ? 'All selected images uploaded' : 'No files selected';
            this.submit.querySelector('span').textContent = this.busy ? 'Uploading…' : pending.some(entry => entry.status === 'error') ? 'Retry remaining images' : 'Upload images';
        }
        async upload() {
            if (this.busy) return;
            const pending = this.queue.filter(entry => entry.status !== 'done');
            if (!pending.length) { this.feedback('Choose or drop an image first.', true); return; }
            this.busy = true; this.feedback(''); this.refreshQueue();
            const progress = this.root.querySelector('[data-upload-progress]'); progress.hidden = false;
            const total = pending.reduce((sum, entry) => sum + entry.file.size, 0); let completed = 0, uploaded = 0;
            for (let index = 0; index < pending.length && !this.destroyed; index++) {
                const entry = pending[index]; entry.status = 'uploading'; entry.error = ''; this.refreshQueue();
                this.root.querySelector('[data-progress-label]').textContent = `Uploading ${index + 1} of ${pending.length}`;
                try {
                    const result = await this.uploadFile(entry.file, pending.length === 1 ? this.filename.value : '', fraction => this.setProgress((completed + entry.file.size * fraction) / total));
                    if (this.destroyed) break;
                    for (const image of result.images) this.insertImage(image);
                    entry.status = 'done'; uploaded++;
                } catch (error) { if (this.destroyed) break; entry.status = 'error'; entry.error = error.message; }
                completed += entry.file.size; this.setProgress(completed / total); this.refreshQueue();
            }
            this.busy = false;
            if (this.destroyed) return;
            const failed = pending.filter(entry => entry.status === 'error').length;
            this.feedback(failed ? `${uploaded} uploaded; ${failed} couldn't be uploaded. Check the messages below and retry.` : `${uploaded} ${uploaded === 1 ? 'image' : 'images'} added to your library.`, failed > 0);
            progress.hidden = true; this.refreshQueue(); this.filterGallery();
        }
        uploadFile(file, filename, onProgress) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest(); this.request = xhr;
                xhr.open('POST', this.form.action); xhr.timeout = 120000; xhr.setRequestHeader('Accept', 'application/json'); xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                const data = new FormData(); data.append('_token', this.form.querySelector('[name="_token"]').value); data.append('image', file); if (filename.trim()) data.append('file_name', filename.trim());
                xhr.upload.onprogress = event => { if (event.lengthComputable) onProgress(event.loaded / event.total); };
                xhr.onload = () => {
                    let result; try { result = JSON.parse(xhr.responseText); } catch {}
                    if (xhr.status >= 200 && xhr.status < 300 && Array.isArray(result?.images)) { resolve(result); return; }
                    const validation = result?.errors ? Object.values(result.errors).flat().join(' ') : '';
                    reject(new Error(validation || ([401, 419].includes(xhr.status) ? 'Your session expired. Refresh the page and try again.' : xhr.status === 413 ? 'This upload exceeds the server limit.' : xhr.status === 403 ? 'You do not have permission to upload images.' : 'Upload failed. Please try again.')));
                };
                xhr.onerror = () => reject(new Error('Connection interrupted. Check your connection and retry.'));
                xhr.ontimeout = () => reject(new Error('Upload timed out. Please retry.'));
                xhr.onabort = () => reject(new Error('Upload cancelled.'));
                xhr.send(data);
            });
        }
        setProgress(fraction) {
            const percent = Math.min(100, Math.round(fraction * 100));
            this.root.querySelector('progress').value = percent;
            this.root.querySelector('[data-progress-percent]').textContent = `${percent}%`;
        }
        insertImage(image) {
            const template = document.createElement('template');
            template.innerHTML = '<article class="vh-media-card" data-image-card><a class="vh-media-thumbnail" target="_blank" rel="noopener noreferrer"><img loading="lazy" decoding="async"><span class="vh-media-format"></span><span class="vh-media-image-fallback" hidden><i class="ti ti-photo-off"></i>Preview unavailable</span><span class="vh-media-selected" hidden><i class="ti ti-check"></i></span></a><div class="vh-media-card-body"><h4></h4><p></p><input class="form-control vh-media-url" readonly data-image-url-input><div class="vh-media-card-actions"><button type="button" class="btn btn-primary" data-select-image aria-pressed="false"><i class="ti ti-pointer icon"></i><span>Select</span></button><button type="button" class="btn btn-outline-secondary" data-copy-image><i class="ti ti-copy icon"></i><span>Copy URL</span></button></div></div></article>';
            const card = template.content.firstElementChild;
            Object.assign(card.dataset, { imageName: image.name, imageUrl: image.url, imageSize: image.size, imageModified: image.modified });
            const link = card.querySelector('a'); link.href = image.url; link.setAttribute('aria-label', `View ${image.name} at full size`);
            const thumbnail = card.querySelector('img'); thumbnail.src = image.url; thumbnail.alt = image.name;
            card.querySelector('.vh-media-format').textContent = image.format;
            const title = card.querySelector('h4'); title.textContent = image.name; title.title = image.name;
            card.querySelector('p').textContent = image.size_label;
            const url = card.querySelector('[data-image-url-input]'); url.value = image.url; url.setAttribute('aria-label', `URL for ${image.name}`);
            this.gallery.prepend(card);
        }
        filterGallery() {
            const query = this.root.querySelector('[data-image-search]').value.trim().toLocaleLowerCase();
            const sort = this.root.querySelector('[data-image-sort]').value;
            const cards = Array.from(this.gallery.querySelectorAll('[data-image-card]'));
            cards.sort((a, b) => sort === 'name' ? a.dataset.imageName.localeCompare(b.dataset.imageName, undefined, { numeric: true }) : Number(b.dataset[sort === 'largest' ? 'imageSize' : 'imageModified']) - Number(a.dataset[sort === 'largest' ? 'imageSize' : 'imageModified']) || a.dataset.imageName.localeCompare(b.dataset.imageName));
            let visible = 0;
            cards.forEach(card => { card.hidden = !card.dataset.imageName.toLocaleLowerCase().includes(query); if (!card.hidden) visible++; this.gallery.append(card); });
            document.querySelectorAll('[data-library-total]').forEach(count => { count.textContent = cards.length; });
            this.root.querySelector('[data-library-count]').textContent = query ? `${visible} of ${cards.length} images` : `${cards.length} ${cards.length === 1 ? 'image' : 'images'} ready to use`;
            const empty = this.root.querySelector('[data-library-empty]'); empty.hidden = visible > 0;
            empty.querySelector('[data-empty-title]').textContent = cards.length ? 'No matching images' : 'Your library starts here';
            empty.querySelector('[data-empty-description]').textContent = cards.length ? 'Try another filename or clear your search to see every image.' : 'Upload your first image to make it available across the site.';
            empty.querySelector('[data-empty-action]').textContent = cards.length ? 'Clear search' : 'Choose images';
        }
        onClick(event) {
            const previewToggle = event.target.closest('[data-preview-toggle]');
            if (previewToggle) {
                const dark = this.root.dataset.previewBackground !== 'dark';
                this.root.dataset.previewBackground = dark ? 'dark' : 'light';
                previewToggle.setAttribute('aria-pressed', String(dark));
                previewToggle.setAttribute('aria-label', `Use ${dark ? 'light' : 'dark'} image preview backgrounds`);
                previewToggle.querySelector('span').textContent = dark ? 'Dark previews' : 'Light previews';
                previewToggle.querySelector('i').className = `ti ti-${dark ? 'moon' : 'sun'} icon`;
                return;
            }
            const remove = event.target.closest('[data-remove-upload]');
            if (remove && !this.busy) { const [entry] = this.queue.splice(Number(remove.dataset.removeUpload), 1); URL.revokeObjectURL(entry.preview); this.refreshQueue(); return; }
            if (event.target.closest('[data-clear-queue]') && !this.busy) { this.queue.forEach(entry => URL.revokeObjectURL(entry.preview)); this.queue = []; this.feedback(''); this.refreshQueue(); return; }
            if (event.target.closest('[data-empty-action]')) { if (this.gallery.children.length) { this.root.querySelector('[data-image-search]').value = ''; this.filterGallery(); } else this.input.click(); return; }
            const card = event.target.closest('[data-image-card]'); if (!card) return;
            if (event.target.matches('[data-image-url-input]')) { event.target.select(); return; }
            const copy = event.target.closest('[data-copy-image]'); if (copy) { this.copyUrl(card, copy); return; }
            if (!event.target.closest('[data-select-image]')) return;
            this.gallery.querySelectorAll('[data-image-card]').forEach(item => {
                const selected = item === card; item.classList.toggle('is-selected', selected);
                item.querySelector('[data-select-image]').setAttribute('aria-pressed', String(selected));
                item.querySelector('[data-select-image] span').textContent = selected ? 'Selected' : 'Select';
                item.querySelector('.vh-media-selected').hidden = !selected;
            });
            try {
                const target = window.opener?.imagePickerTarget;
                if (target?.isConnected) { target.value = card.dataset.imageUrl; for (const type of ['input', 'change']) target.dispatchEvent(new target.ownerDocument.defaultView.Event(type, { bubbles: true })); window.close(); return; }
            } catch {}
            const input = card.querySelector('[data-image-url-input]'); input.focus(); input.select(); this.toast('Image selected. Copy its URL to reuse it.');
        }
        async copyUrl(card, button) {
            const value = card.dataset.imageUrl;
            try {
                if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(value);
                else {
                    const input = card.querySelector('[data-image-url-input]'); input.focus(); input.select();
                    if (!document.execCommand('copy')) throw new Error('Clipboard unavailable');
                }
                button.querySelector('span').textContent = 'Copied'; this.toast('Image URL copied.');
                setTimeout(() => { button.querySelector('span').textContent = 'Copy URL'; }, 2000);
            } catch { const input = card.querySelector('[data-image-url-input]'); input.focus(); input.select(); this.toast('Copy unavailable. The URL is selected for manual copying.'); }
        }
        toast(message) {
            clearTimeout(this.toastTimeout); const toast = this.root.querySelector('[data-image-toast]'); toast.hidden = false; toast.textContent = message;
            this.toastTimeout = setTimeout(() => { toast.hidden = true; }, 4500);
        }
        destroy() {
            this.destroyed = true; this.request?.abort(); this.queue.forEach(entry => URL.revokeObjectURL(entry.preview)); clearTimeout(this.toastTimeout);
            document.removeEventListener('dragover', this.preventFileNavigation); document.removeEventListener('drop', this.preventFileNavigation);
            delete this.root.dataset.libraryMounted;
        }
    }
    const mount = () => document.querySelectorAll('[data-image-library]:not([data-library-mounted])').forEach(root => { root.dataset.libraryMounted = 'true'; instances.add(new ImageLibrary(root)); });
    window.VadedImageLibrary = { mount };
    document.addEventListener('DOMContentLoaded', mount);
    document.addEventListener('livewire:navigated', mount);
    document.addEventListener('livewire:navigating', () => { instances.forEach(instance => instance.destroy()); instances.clear(); });
    mount();
})();
