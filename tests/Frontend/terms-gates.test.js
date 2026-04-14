import test from 'node:test';
import assert from 'node:assert/strict';

import { createTermsGateState } from '../../resources/js/terms-gates.js';

test('terms gate unlocks locally after the modal stays open for ten real seconds', () => {
    const gate = createTermsGateState({ requiredSeconds: 10 });

    gate.setModalVisible(true, 0);

    assert.equal(gate.tick(9_000), false);
    assert.equal(gate.ready, false);
    assert.equal(gate.isUnlocked(9_000), false);
    assert.equal(gate.secondsRemaining(9_000), 1);

    assert.equal(gate.tick(10_000), true);
    assert.equal(gate.ready, false);
    assert.equal(gate.isUnlocked(10_000), true);
    assert.equal(gate.secondsRemaining(10_000), 0);
});

test('terms gate warning appears only on early attempt and dismisses automatically', () => {
    const gate = createTermsGateState({
        requiredSeconds: 10,
        warningDurationMs: 3_200,
    });

    gate.showWarning('Please read the terms before continuing.', 1_000);

    assert.equal(gate.warning, 'Please read the terms before continuing.');

    gate.dismissWarningIfNeeded(3_000);
    assert.equal(gate.warning, 'Please read the terms before continuing.');

    gate.dismissWarningIfNeeded(4_300);
    assert.equal(gate.warning, null);
});

test('terms gate does not create a warning automatically when the timer expires', () => {
    const gate = createTermsGateState({ requiredSeconds: 10 });

    gate.setModalVisible(true, 0);
    assert.equal(gate.tick(10_000), true);

    assert.equal(gate.warning, null);
    assert.equal(gate.isUnlocked(10_000), true);
});

test('closing the modal pauses progress instead of resetting it unless the user starts again', () => {
    const gate = createTermsGateState({ requiredSeconds: 10 });

    gate.setModalVisible(true, 0);
    gate.tick(4_000);
    gate.setModalVisible(false, 4_000);

    assert.equal(gate.secondsRemaining(4_000), 6);

    gate.setModalVisible(true, 4_000);
    assert.equal(gate.tick(10_000), true);
});

test('valid post-timer acceptance stays checked and does not create a warning', () => {
    const gate = createTermsGateState({ requiredSeconds: 10 });

    gate.setModalVisible(true, 0);
    assert.equal(gate.tick(10_000), true);

    gate.setAccepted(true);
    gate.setCompleting(true);

    assert.equal(gate.accepted, true);
    assert.equal(gate.warning, null);

    gate.setCompleting(false);
    gate.setReady(true);

    assert.equal(gate.ready, true);
    assert.equal(gate.accepted, true);
    assert.equal(gate.warning, null);
    assert.equal(gate.secondsRemaining(10_000), 0);
});
