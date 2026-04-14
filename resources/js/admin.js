import './bootstrap';
import './terms-gates';

const desktopBreakpoint = window.matchMedia('(min-width: 1024px)');

const applyCollapsedState = (shell, collapsed) => {
    if (!shell) {
        return;
    }

    shell.setAttribute('data-admin-sidebar-collapsed', collapsed ? 'true' : 'false');
};

const readCollapsedState = (collapsedStorageKey) => {
    try {
        return window.localStorage.getItem(collapsedStorageKey) === 'true';
    } catch {
        return false;
    }
};

const writeCollapsedState = (collapsedStorageKey, collapsedCookieName, collapsed) => {
    try {
        window.localStorage.setItem(collapsedStorageKey, collapsed ? 'true' : 'false');
    } catch {
        // Ignore storage failures and keep the current in-memory state.
    }

    document.cookie = `${collapsedCookieName}=${collapsed ? 'true' : 'false'}; path=/; max-age=31536000; SameSite=Lax`;
};

const closeSidebar = (sidebar, overlay) => {
    if (!sidebar || !overlay) {
        return;
    }

    sidebar.classList.remove('translate-x-0');
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
};

const openSidebar = (sidebar, overlay) => {
    if (!sidebar || !overlay || desktopBreakpoint.matches) {
        return;
    }

    sidebar.classList.remove('-translate-x-full');
    sidebar.classList.add('translate-x-0');
    overlay.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
};

const initializeProfileCamera = () => {
    const cameraRoots = document.querySelectorAll('[data-profile-camera-root]');

    cameraRoots.forEach((root) => {
        if (root.dataset.cameraInitialized === 'true') {
            return;
        }

        root.dataset.cameraInitialized = 'true';

        let stream = null;

        const status = root.querySelector('[data-profile-camera-status]');
        const startButton = root.querySelector('[data-profile-camera-start]');
        const captureButton = root.querySelector('[data-profile-camera-capture]');
        const stopButton = root.querySelector('[data-profile-camera-stop]');
        const video = root.querySelector('[data-profile-camera-preview]');
        const canvas = root.querySelector('[data-profile-camera-canvas]');

        const updateStatus = (message) => {
            if (status) {
                status.textContent = message;
            }
        };

        const stopStream = () => {
            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
                stream = null;
            }

            if (video) {
                video.pause();
                video.srcObject = null;
                video.classList.add('hidden');
            }

            captureButton?.classList.add('hidden');
            stopButton?.classList.add('hidden');
        };

        const supportsCamera = Boolean(
            navigator.mediaDevices?.getUserMedia
            && window.DataTransfer
            && canvas?.getContext
        );

        if (!supportsCamera) {
            updateStatus('Camera capture is not supported in this browser. Upload a profile picture from your device instead.');

            if (startButton) {
                startButton.disabled = true;
                startButton.classList.add('opacity-60', 'cursor-not-allowed');
            }

            return;
        }

        startButton?.addEventListener('click', async () => {
            try {
                stopStream();

                stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user' },
                    audio: false,
                });

                if (video) {
                    video.srcObject = stream;
                    video.classList.remove('hidden');
                    await video.play();
                }

                captureButton?.classList.remove('hidden');
                stopButton?.classList.remove('hidden');
                updateStatus('Camera is ready. Capture a still photo when you are satisfied with the frame.');
            } catch {
                updateStatus('Camera access was denied or unavailable. Upload a profile picture from your device instead.');
                stopStream();
            }
        });

        captureButton?.addEventListener('click', () => {
            const fileInput = document.querySelector('[data-profile-picture-input]');

            if (!fileInput || !video || !canvas || !video.videoWidth || !video.videoHeight) {
                updateStatus('We could not capture a photo from the camera preview.');

                return;
            }

            const context = canvas.getContext('2d');

            if (!context) {
                updateStatus('This browser could not prepare a camera capture image.');

                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                if (!blob) {
                    updateStatus('We could not capture a photo from the camera preview.');

                    return;
                }

                const capturedFile = new File([blob], 'landlord-profile-camera.jpg', { type: 'image/jpeg' });
                const transfer = new DataTransfer();

                transfer.items.add(capturedFile);
                fileInput.files = transfer.files;
                fileInput.dispatchEvent(new Event('change', { bubbles: true }));

                updateStatus('Captured photo is ready. Save the profile form to keep it.');
                stopStream();
            }, 'image/jpeg', 0.92);
        });

        stopButton?.addEventListener('click', () => {
            stopStream();
            updateStatus('Camera stopped. You can start it again or upload a picture from your device.');
        });

        window.addEventListener('beforeunload', stopStream);
    });
};

const initializeLandlordDocumentUploads = () => {
    const documentRoots = document.querySelectorAll('[data-landlord-document-root]');

    documentRoots.forEach((root) => {
        if (root.dataset.landlordDocumentInitialized === 'true') {
            return;
        }

        root.dataset.landlordDocumentInitialized = 'true';

        const input = root.querySelector('[data-landlord-document-input]');
        const clientError = root.querySelector('[data-landlord-document-client-error]');
        const uploadStatus = root.querySelector('[data-landlord-document-upload-status]');

        if (!input || !clientError || !uploadStatus) {
            return;
        }

        const setClientError = (message) => {
            clientError.textContent = message;
            clientError.classList.toggle('hidden', message === '');
        };

        const setUploadStatus = (message) => {
            uploadStatus.textContent = message;
            uploadStatus.classList.toggle('hidden', message === '');
        };

        const clearMessages = () => {
            setClientError('');
            setUploadStatus('');
        };

        input.addEventListener('change', (event) => {
            clearMessages();

            const maxBytes = Number.parseInt(input.dataset.maxBytes || '0', 10);
            const maxLabel = input.dataset.maxLabel || 'the allowed limit';
            const selectedFile = input.files?.[0];

            if (!selectedFile || !Number.isFinite(maxBytes) || maxBytes <= 0) {
                return;
            }

            if (selectedFile.size > maxBytes) {
                event.stopImmediatePropagation();
                input.value = '';
                setClientError(`This file exceeds the current server upload limit of ${maxLabel}. Choose a smaller file and try again.`);
            }
        }, true);

        input.addEventListener('livewire-upload-start', () => {
            setClientError('');
            setUploadStatus('Preparing upload...');
        });

        input.addEventListener('livewire-upload-progress', (event) => {
            setUploadStatus(`Preparing upload... ${event.detail.progress}%`);
        });

        input.addEventListener('livewire-upload-finish', () => {
            setUploadStatus('File is ready. Click Upload Document to save it.');
        });

        input.addEventListener('livewire-upload-error', () => {
            const maxLabel = input.dataset.maxLabel || 'the current server limit';
            setUploadStatus('');
            setClientError(`The file could not be prepared for upload. Files above ${maxLabel} are rejected before the document can be saved.`);
        });

        input.addEventListener('livewire-upload-cancel', () => {
            setUploadStatus('');
        });
    });
};

const initializeProcessingForms = () => {
    const forms = document.querySelectorAll('[data-processing-form]');

    forms.forEach((form) => {
        if (form.dataset.processingBound === 'true') {
            return;
        }

        form.dataset.processingBound = 'true';

        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-processing-button]');

            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.classList.add('is-processing');

            const idleText = button.querySelector('[data-button-idle]');
            const processingText = button.querySelector('[data-button-processing]');

            idleText?.classList.add('hidden');
            processingText?.classList.remove('hidden');
        });
    });
};

const resetProcessingButtons = () => {
    const buttons = document.querySelectorAll('[data-processing-button].is-processing');

    buttons.forEach((button) => {
        button.disabled = false;
        button.classList.remove('is-processing');

        const idleText = button.querySelector('[data-button-idle]');
        const processingText = button.querySelector('[data-button-processing]');

        idleText?.classList.remove('hidden');
        processingText?.classList.add('hidden');
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('[data-admin-shell]');

    initializeProfileCamera();
    initializeLandlordDocumentUploads();
    initializeProcessingForms();
    resetProcessingButtons();

    if (!shell) {
        return;
    }

    const shellKey = shell.getAttribute('data-admin-shell-key') || 'admin';
    const collapsedStorageKey = `${shellKey}.sidebar.collapsed`;
    const collapsedCookieName = `${shellKey}.sidebar.collapsed`;

    const sidebar = shell.querySelector('[data-admin-sidebar]');
    const overlay = shell.querySelector('[data-admin-overlay]');
    const openButtons = shell.querySelectorAll('[data-admin-sidebar-open]');
    const closeButtons = shell.querySelectorAll('[data-admin-sidebar-close]');
    const collapseButtons = shell.querySelectorAll('[data-admin-sidebar-toggle]');
    const profile = shell.querySelector('[data-admin-profile]');
    const profileToggle = shell.querySelector('[data-admin-profile-toggle]');
    const profileMenu = shell.querySelector('[data-admin-profile-menu]');
    const notifications = shell.querySelector('[data-admin-notifications]');
    const notificationsToggle = shell.querySelector('[data-admin-notifications-toggle]');
    const notificationsMenu = shell.querySelector('[data-admin-notifications-menu]');

    const persistedCollapsedState = readCollapsedState(collapsedStorageKey);
    const initialCollapsedState = shell.getAttribute('data-admin-sidebar-collapsed') === 'true';

    if (persistedCollapsedState !== initialCollapsedState) {
        applyCollapsedState(shell, persistedCollapsedState);
        writeCollapsedState(collapsedStorageKey, collapsedCookieName, persistedCollapsedState);
    } else {
        applyCollapsedState(shell, initialCollapsedState);
    }

    const closeProfileMenu = () => {
        if (!profileToggle || !profileMenu) {
            return;
        }

        profileMenu.classList.add('hidden');
        profileToggle.setAttribute('aria-expanded', 'false');
    };

    const openProfileMenu = () => {
        if (!profileToggle || !profileMenu) {
            return;
        }

        profileMenu.classList.remove('hidden');
        profileToggle.setAttribute('aria-expanded', 'true');
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => openSidebar(sidebar, overlay));
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => closeSidebar(sidebar, overlay));
    });

    collapseButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const collapsed = shell.getAttribute('data-admin-sidebar-collapsed') === 'true';
            const nextState = !collapsed;

            applyCollapsedState(shell, nextState);
            writeCollapsedState(collapsedStorageKey, collapsedCookieName, nextState);
        });
    });

    if (profile && profileToggle && profileMenu) {
        profileToggle.addEventListener('click', () => {
            const expanded = profileToggle.getAttribute('aria-expanded') === 'true';

            if (expanded) {
                closeProfileMenu();
            } else {
                openProfileMenu();
            }
        });

        document.addEventListener('click', (event) => {
            if (!profile.contains(event.target)) {
                closeProfileMenu();
            }
        });
    }

    const closeNotificationsMenu = () => {
        if (!notificationsToggle || !notificationsMenu) {
            return;
        }

        notificationsMenu.classList.add('hidden');
        notificationsToggle.setAttribute('aria-expanded', 'false');
    };

    const openNotificationsMenu = () => {
        if (!notificationsToggle || !notificationsMenu) {
            return;
        }

        notificationsMenu.classList.remove('hidden');
        notificationsToggle.setAttribute('aria-expanded', 'true');
    };

    if (notifications && notificationsToggle && notificationsMenu) {
        notificationsToggle.addEventListener('click', () => {
            const expanded = notificationsToggle.getAttribute('aria-expanded') === 'true';

            if (expanded) {
                closeNotificationsMenu();
            } else {
                openNotificationsMenu();
                closeProfileMenu();
            }
        });

        document.addEventListener('click', (event) => {
            if (!notifications.contains(event.target)) {
                closeNotificationsMenu();
            }
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar(sidebar, overlay);
            closeProfileMenu();
            closeNotificationsMenu();
        }
    });

    desktopBreakpoint.addEventListener('change', (event) => {
        if (event.matches) {
            closeSidebar(sidebar, overlay);
        }
    });

    document.addEventListener('livewire:navigated', () => {
        initializeProfileCamera();
        initializeLandlordDocumentUploads();
        initializeProcessingForms();
        resetProcessingButtons();
    });
});

window.addEventListener('pageshow', resetProcessingButtons);
