import test from 'node:test';
import assert from 'node:assert/strict';

import { createCameraCaptureController } from '../../resources/js/camera-capture.js';

class FakeElement {
    constructor() {
        this.listeners = new Map();
        this.hidden = false;
        this.textContent = '';
        this.videoWidth = 1920;
        this.videoHeight = 1080;
        this.srcObject = null;
        this.events = [];
    }

    addEventListener(name, listener) { this.listeners.set(name, listener); }
    removeEventListener(name) { this.listeners.delete(name); }
    dispatchEvent(event) { this.events.push(event.type); return true; }
    toggleAttribute(name, force) { if (name === 'hidden') this.hidden = force; }
    removeAttribute() {}
    pause() {}
    async play() {}
}

const createHarness = ({ getUserMedia } = {}) => {
    const elements = Object.fromEntries([
        'open', 'modal', 'backdrop', 'cancel', 'capture', 'retake', 'use', 'switch', 'video', 'image', 'canvas', 'status',
    ].map((name) => [name, new FakeElement()]));
    elements.canvas.getContext = () => ({ drawImage() {} });
    elements.canvas.toBlob = (callback) => callback(new Blob(['camera-image'], { type: 'image/jpeg' }));
    const selectors = {
        '[data-camera-open]': elements.open,
        '[data-camera-modal]': elements.modal,
        '[data-camera-backdrop]': elements.backdrop,
        '[data-camera-cancel]': elements.cancel,
        '[data-camera-capture]': elements.capture,
        '[data-camera-retake]': elements.retake,
        '[data-camera-use]': elements.use,
        '[data-camera-switch]': elements.switch,
        '[data-camera-video]': elements.video,
        '[data-camera-image]': elements.image,
        '[data-camera-canvas]': elements.canvas,
        '[data-camera-status]': elements.status,
    };
    const root = { dataset: { cameraTarget: 'photo-input', cameraKind: 'profile' }, querySelector: (selector) => selectors[selector] };
    const input = new FakeElement();
    const document = { activeElement: null, addEventListener() {}, removeEventListener() {}, getElementById: () => input };
    const mediaDevices = {
        getUserMedia: getUserMedia ?? (async () => ({ getTracks: () => [] })),
        enumerateDevices: async () => [{ kind: 'videoinput' }, { kind: 'videoinput' }],
    };
    const transfer = { items: { add(file) { transfer.files = [file]; } }, files: [] };
    const controller = createCameraCaptureController(root, {
        document,
        input,
        mediaDevices,
        isSecureContext: true,
        createObjectURL: () => 'blob:camera',
        revokeObjectURL() {},
        createFile: (blob, name) => ({ blob, name, type: 'image/jpeg' }),
        createTransfer: () => transfer,
    });

    return { controller, elements, input, mediaDevices };
};

test('requests the front camera, captures a file, and uses the normal file input path', async () => {
    const track = { stopped: false, stop() { this.stopped = true; } };
    const calls = [];
    const { controller, input } = createHarness({ getUserMedia: async (constraints) => {
        calls.push(constraints);
        return { getTracks: () => [track] };
    } });

    await controller.open();
    assert.deepEqual(calls[0], { video: { facingMode: { ideal: 'user' } }, audio: false });

    await controller.capture();
    assert.equal(track.stopped, true);
    assert.equal(controller.capturedFile.name, 'profile-camera.jpg');

    controller.useCapture();
    assert.equal(input.files[0].name, 'profile-camera.jpg');
    assert.deepEqual(input.events, ['change']);
});

test('permission denial preserves file-upload fallback and cancel stops active tracks', async () => {
    const denied = createHarness({ getUserMedia: async () => { throw Object.assign(new Error('denied'), { name: 'NotAllowedError' }); } });
    await denied.controller.open();
    assert.match(denied.elements.status.textContent, /blocked/i);

    const track = { stopped: false, stop() { this.stopped = true; } };
    const active = createHarness({ getUserMedia: async () => ({ getTracks: () => [track] }) });
    await active.controller.open();
    active.controller.close();
    assert.equal(track.stopped, true);
});

test('missing camera support leaves the file-upload fallback available', async () => {
    const { controller, elements } = createHarness();
    await controller.open();
    controller.close();

    const unsupported = createHarness();
    unsupported.mediaDevices.getUserMedia = undefined;
    await unsupported.controller.open();
    assert.match(unsupported.elements.status.textContent, /upload a photo/i);
});

test('retake starts a fresh front-facing stream and stops the previous stream', async () => {
    const tracks = [];
    const { controller } = createHarness({ getUserMedia: async () => {
        const track = { stopped: false, stop() { this.stopped = true; } };
        tracks.push(track);
        return { getTracks: () => [track] };
    } });

    await controller.open();
    await controller.capture();
    await controller.startCamera();

    assert.equal(tracks[0].stopped, true);
    assert.equal(tracks.length, 2);
    controller.destroy();
    assert.equal(tracks[1].stopped, true);
});
