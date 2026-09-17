const controllers = new Map();

const cameraMessage = (error) => {
    if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
        return 'Camera access was blocked. You can still upload a photo from your device.';
    }

    if (error?.name === 'NotFoundError') {
        return 'No camera was found on this device. You can still upload a photo from your device.';
    }

    if (error?.name === 'NotReadableError') {
        return 'Your camera is already in use by another application. Close it there or upload a photo instead.';
    }

    return 'We could not start the camera. You can still upload a photo from your device.';
};

const stopTracks = (stream) => stream?.getTracks().forEach((track) => track.stop());

export const createCameraCaptureController = (root, dependencies = {}) => {
    const documentRef = dependencies.document ?? document;
    const mediaDevices = dependencies.mediaDevices ?? navigator.mediaDevices;
    const isSecureContext = dependencies.isSecureContext ?? window.isSecureContext;
    const createObjectURL = dependencies.createObjectURL ?? URL.createObjectURL.bind(URL);
    const revokeObjectURL = dependencies.revokeObjectURL ?? URL.revokeObjectURL.bind(URL);
    const createFile = dependencies.createFile ?? ((blob, name) => new File([blob], name, { type: 'image/jpeg' }));
    const createTransfer = dependencies.createTransfer ?? (() => new DataTransfer());
    const configuredInput = dependencies.input;
    const currentInput = () => configuredInput ?? documentRef.getElementById(root.dataset.cameraTarget);
    const openButton = root.querySelector('[data-camera-open]');
    const modal = root.querySelector('[data-camera-modal]');
    const backdrop = root.querySelector('[data-camera-backdrop]');
    const cancelButton = root.querySelector('[data-camera-cancel]');
    const captureButton = root.querySelector('[data-camera-capture]');
    const retakeButton = root.querySelector('[data-camera-retake]');
    const useButton = root.querySelector('[data-camera-use]');
    const switchButton = root.querySelector('[data-camera-switch]');
    const video = root.querySelector('[data-camera-video]');
    const image = root.querySelector('[data-camera-image]');
    const canvas = root.querySelector('[data-camera-canvas]');
    const status = root.querySelector('[data-camera-status]');
    const kind = root.dataset.cameraKind || 'photo';
    let stream = null;
    let capturedFile = null;
    let capturedUrl = null;
    let facingMode = root.dataset.cameraFacingMode || 'user';
    let active = false;
    let returnFocus = null;

    const setStatus = (message) => {
        if (status) status.textContent = message;
    };

    const setVisible = (element, visible) => element?.toggleAttribute('hidden', !visible);

    const clearCapture = () => {
        capturedFile = null;
        if (capturedUrl) revokeObjectURL(capturedUrl);
        capturedUrl = null;
        if (image) image.removeAttribute('src');
        setVisible(image, false);
    };

    const stopCamera = () => {
        stopTracks(stream);
        stream = null;
        if (video) {
            video.pause?.();
            video.srcObject = null;
        }
    };

    const setPreviewMode = (captured) => {
        setVisible(video, !captured);
        setVisible(image, captured);
        setVisible(captureButton, !captured);
        setVisible(retakeButton, captured);
        setVisible(useButton, captured);
        setVisible(switchButton, !captured && Boolean(stream));
    };

    const startCamera = async () => {
        if (!mediaDevices?.getUserMedia || !isSecureContext) {
            setStatus('Camera capture needs a supported secure browser connection. You can still upload a photo from your device.');
            return false;
        }

        stopCamera();
        clearCapture();

        try {
            const constraints = { video: { facingMode: { ideal: facingMode } }, audio: false };
            try {
                stream = await mediaDevices.getUserMedia(constraints);
            } catch (error) {
                if (error?.name !== 'OverconstrainedError') throw error;
                stream = await mediaDevices.getUserMedia({ video: true, audio: false });
            }

            if (video) {
                video.srcObject = stream;
                await video.play?.();
            }

            const devices = await mediaDevices.enumerateDevices?.() ?? [];
            const cameraCount = devices.filter((device) => device.kind === 'videoinput').length;
            setVisible(switchButton, cameraCount > 1);
            setPreviewMode(false);
            setStatus('Camera is ready. Capture a still photo when you are satisfied with the frame.');

            return true;
        } catch (error) {
            stopCamera();
            setVisible(switchButton, false);
            setStatus(cameraMessage(error));

            return false;
        }
    };

    const capture = async () => {
        if (!video?.videoWidth || !video?.videoHeight || !canvas?.getContext) {
            setStatus('The camera preview is not ready yet. Please try again.');
            return;
        }

        const longestSide = Math.max(video.videoWidth, video.videoHeight);
        const scale = Math.min(1, 1600 / longestSide);
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        const context = canvas.getContext('2d');

        if (!context) {
            setStatus('This browser could not prepare your captured photo.');
            return;
        }

        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));

        if (!blob) {
            setStatus('We could not capture a photo from the camera preview.');
            return;
        }

        capturedFile = createFile(blob, `${kind}-camera.jpg`);
        capturedUrl = createObjectURL(blob);
        if (image) image.src = capturedUrl;
        stopCamera();
        setPreviewMode(true);
        setStatus('Review your captured photo. Retake it or use it with the upload form.');
    };

    const useCapture = () => {
        const input = currentInput();

        if (!capturedFile || !input) return;

        const transfer = createTransfer();
        transfer.items.add(capturedFile);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    };

    const close = (discard = true) => {
        stopCamera();
        if (discard) clearCapture();
        active = false;
        setVisible(modal, false);
        if (returnFocus?.focus) returnFocus.focus();
        returnFocus = null;
    };

    const open = async () => {
        returnFocus = documentRef.activeElement;
        active = true;
        setVisible(modal, true);
        setPreviewMode(false);
        await startCamera();
    };

    const switchCamera = async () => {
        facingMode = facingMode === 'user' ? 'environment' : 'user';
        await startCamera();
    };

    const onEscape = (event) => {
        if (active && event.key === 'Escape') close();
    };

    openButton?.addEventListener('click', open);
    captureButton?.addEventListener('click', capture);
    retakeButton?.addEventListener('click', startCamera);
    useButton?.addEventListener('click', useCapture);
    switchButton?.addEventListener('click', switchCamera);
    cancelButton?.addEventListener('click', () => close());
    backdrop?.addEventListener('click', () => close());
    documentRef.addEventListener('keydown', onEscape);

    return {
        open,
        capture,
        close,
        startCamera,
        switchCamera,
        useCapture,
        get stream() { return stream; },
        get capturedFile() { return capturedFile; },
        destroy() {
            close();
            openButton?.removeEventListener('click', open);
            captureButton?.removeEventListener('click', capture);
            retakeButton?.removeEventListener('click', startCamera);
            useButton?.removeEventListener('click', useCapture);
            switchButton?.removeEventListener('click', switchCamera);
            documentRef.removeEventListener('keydown', onEscape);
        },
    };
};

export const initializeCameraCaptures = () => {
    controllers.forEach((controller, root) => {
        if (!document.contains(root)) {
            controller.destroy();
            controllers.delete(root);
        }
    });

    document.querySelectorAll('[data-camera-capture]').forEach((root) => {
        if (!controllers.has(root)) controllers.set(root, createCameraCaptureController(root));
    });
};

export const destroyCameraCaptures = () => {
    controllers.forEach((controller) => controller.destroy());
    controllers.clear();
};
