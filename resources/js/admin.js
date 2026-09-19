import './bootstrap';
import './terms-gates';
import { destroyCameraCaptures, initializeCameraCaptures } from './camera-capture';

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
    sidebar.setAttribute('aria-hidden', 'true');
    sidebar.closest('[data-admin-shell]')?.querySelectorAll('[data-admin-sidebar-open]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
    overlay.classList.add('hidden');
    document.body.classList.remove('admin-mobile-drawer-open');
};

const openSidebar = (sidebar, overlay) => {
    if (!sidebar || !overlay || desktopBreakpoint.matches) {
        return;
    }

    sidebar.classList.remove('-translate-x-full');
    sidebar.classList.add('translate-x-0');
    sidebar.setAttribute('aria-hidden', 'false');
    sidebar.closest('[data-admin-shell]')?.querySelectorAll('[data-admin-sidebar-open]').forEach((button) => button.setAttribute('aria-expanded', 'true'));
    overlay.classList.remove('hidden');
    document.body.classList.add('admin-mobile-drawer-open');
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

        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented) {
                return;
            }

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

const initializeActiveSupportDrawers = () => {
    document.querySelectorAll('[data-active-support-drawer]').forEach((drawer) => {
        if (drawer.dataset.activeSupportBound === 'true') return;

        drawer.dataset.activeSupportBound = 'true';
        const modal = drawer.querySelector('[data-active-support-modal]');
        const open = drawer.querySelector('[data-active-support-open]');
        const close = () => {
            modal?.classList.add('hidden');
            modal?.setAttribute('aria-hidden', 'true');
            open?.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('admin-mobile-drawer-open');
        };
        const show = () => {
            modal?.classList.remove('hidden');
            modal?.setAttribute('aria-hidden', 'false');
            open?.setAttribute('aria-expanded', 'true');
            document.body.classList.add('admin-mobile-drawer-open');
        };

        open?.addEventListener('click', show);
        drawer.querySelectorAll('[data-active-support-close]').forEach((button) => button.addEventListener('click', close));
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
        document.addEventListener('livewire:navigating', close);
    });
};

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('[data-admin-shell]');

    initializeCameraCaptures();
    initializeLandlordDocumentUploads();
    initializeProcessingForms();
    initializeActiveSupportDrawers();
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

    let collapsedPreference = readCollapsedState(collapsedStorageKey);
    const initialCollapsedState = shell.getAttribute('data-admin-sidebar-collapsed') === 'true';

    const syncCollapsedState = () => {
        if (!desktopBreakpoint.matches) {
            applyCollapsedState(shell, false);
            sidebar?.setAttribute('aria-hidden', 'true');

            return;
        }

        applyCollapsedState(shell, collapsedPreference);
        sidebar?.setAttribute('aria-hidden', 'false');
    };

    if (desktopBreakpoint.matches && collapsedPreference !== initialCollapsedState) {
        applyCollapsedState(shell, collapsedPreference);
        writeCollapsedState(collapsedStorageKey, collapsedCookieName, collapsedPreference);
    } else {
        syncCollapsedState();
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

    overlay?.addEventListener('click', () => closeSidebar(sidebar, overlay));

    sidebar?.querySelectorAll('nav a[href]').forEach((link) => {
        link.addEventListener('click', () => closeSidebar(sidebar, overlay));
    });

    collapseButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!desktopBreakpoint.matches) {
                return;
            }

            const collapsed = shell.getAttribute('data-admin-sidebar-collapsed') === 'true';
            const nextState = !collapsed;

            collapsedPreference = nextState;
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
        closeSidebar(sidebar, overlay);
        syncCollapsedState();
    });

    document.addEventListener('livewire:navigating', () => {
        destroyCameraCaptures();
        closeSidebar(sidebar, overlay);
    });

    document.addEventListener('livewire:navigated', () => {
        closeSidebar(sidebar, overlay);
        initializeCameraCaptures();
        initializeLandlordDocumentUploads();
        initializeProcessingForms();
        initializeActiveSupportDrawers();
        resetProcessingButtons();
    });
});

window.addEventListener('pageshow', resetProcessingButtons);
