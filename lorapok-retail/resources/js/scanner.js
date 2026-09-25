/**
 * Camera barcode scanning.
 *
 * A fallback, not the primary path. Real shops use a USB scanner, which
 * behaves as a keyboard and types straight into the search box — that needs
 * no JavaScript at all and is far faster than pointing a phone at a label.
 * The camera exists for a cashier working from a tablet, or when the gun
 * breaks.
 *
 * ZXing is loaded lazily so the ~200KB decoder is only fetched by someone
 * who actually presses the button.
 */

async function start(videoEl, onResult) {
    const { BrowserMultiFormatReader } = await import('@zxing/browser');
    const reader = new BrowserMultiFormatReader();

    const controls = await reader.decodeFromVideoDevice(undefined, videoEl, (result) => {
        if (!result) {
            return;
        }

        onResult(result.getText());
    });

    return controls;
}

document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-scan-button]');

        if (!button) {
            return;
        }

        const video = document.querySelector('[data-scan-video]');
        const target = document.querySelector(button.dataset.scanTarget || '#scan');

        if (!video || !target) {
            return;
        }

        // getUserMedia needs a secure context and explicit permission, and
        // either can be refused. Say so plainly rather than failing silently.
        if (!navigator.mediaDevices?.getUserMedia) {
            button.textContent = 'Camera unavailable — type the code instead';

            return;
        }

        video.hidden = false;

        try {
            const controls = await start(video, (text) => {
                target.value = text;
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));

                controls.stop();
                video.hidden = true;
            });
        } catch (e) {
            video.hidden = true;
            button.textContent = 'Camera blocked — type the code instead';
        }
    });
});
