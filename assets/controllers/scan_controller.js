import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'form',
        'device',
        'resolution',
        'filename',
        'submitButton',
        'preview',
        'previewImage',
        'status',
        'format',
        'downloadButton',
    ];

    static values = {
        downloadTemplate: String,
        optionsUrl: String,
    };

    scanId = null;

    async changeDevice() {
        if (!this.hasDeviceTarget || !this.hasResolutionTarget) {
            return;
        }

        const device = this.deviceTarget.value;
        const url = `${this.optionsUrlValue}?device=${encodeURIComponent(device)}`;

        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || data.error) {
                this.showStatus(data.error || 'Could not load scanner options', true);
                return;
            }

            const current = this.resolutionTarget.value;
            this.resolutionTarget.innerHTML = (data.resolutions || [])
                .map((resolution) => {
                    const selected = String(resolution) === String(current) ? ' selected' : '';
                    return `<option value="${resolution}"${selected}>${resolution}</option>`;
                })
                .join('');
        } catch (error) {
            this.showStatus('Could not load scanner options', true);
        }
    }

    async submit(event) {
        event.preventDefault();
        if (!this.hasFormTarget) {
            return;
        }

        this.setBusy(true);
        this.showStatus('Scanning…');

        try {
            const response = await fetch(this.formTarget.action, {
                method: 'POST',
                body: new FormData(this.formTarget),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json();
            if (!response.ok || data.error) {
                this.showStatus(data.error || 'Scan failed', true);
                return;
            }

            this.scanId = data.scanId;
            if (this.hasPreviewImageTarget) {
                this.previewImageTarget.src = data.previewUrl;
            }
            if (this.hasPreviewTarget) {
                this.previewTarget.classList.remove('d-none');
                this.previewTarget.hidden = false;
            }
            this.showStatus('Scan complete. Choose a format and download.', false);
        } catch (error) {
            this.showStatus('Scan failed', true);
        } finally {
            this.setBusy(false);
        }
    }

    download(event) {
        event.preventDefault();
        if (!this.scanId) {
            this.showStatus('Scan an image first', true);
            return;
        }

        const formatInput = this.formatTargets.find((input) => input.checked);
        const format = formatInput ? formatInput.value : 'jpeg';
        const filename = this.hasFilenameTarget ? this.filenameTarget.value : '';
        const url = this.downloadTemplateValue.replace('00000000000000000000000000000000', this.scanId);
        const params = new URLSearchParams({ format, filename });
        window.location.href = `${url}?${params.toString()}`;
    }

    showStatus(message, isError = false) {
        if (!this.hasStatusTarget) {
            return;
        }

        this.statusTarget.textContent = message;
        this.statusTarget.classList.remove('d-none', 'alert-danger', 'alert-info', 'alert-success');
        this.statusTarget.classList.add(isError ? 'alert-danger' : 'alert-info');
    }

    setBusy(busy) {
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = busy;
        }
        if (this.hasDownloadButtonTarget) {
            this.downloadButtonTarget.disabled = busy;
        }
    }
}
