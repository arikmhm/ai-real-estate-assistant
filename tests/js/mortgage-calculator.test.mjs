import test from 'node:test';
import assert from 'node:assert/strict';
import { calculateInstallment, synchronizeDownPayment } from '../../resources/js/mortgage-calculator.js';

test('anuitas tetap menghitung cicilan bulanan dan asumsi', () => {
    assert.deepEqual(calculateInstallment({
        price: '1000000000', downPayment: '200000000', tenorYears: '20', annualRatePercent: '8',
    }), {
        price: 1000000000, downPayment: 200000000, downPaymentPercent: 20,
        principal: 800000000, tenorYears: 20, annualRatePercent: 8, monthlyPayment: 6691521,
    });
});

test('bunga nol membagi pokok secara merata dan batas maksimum tetap terhingga', () => {
    assert.equal(calculateInstallment({ price: 1000000000, downPayment: 200000000, tenorYears: 20, annualRatePercent: 0 }).monthlyPayment, 3333333);
    const maximum = calculateInstallment({ price: 100000000000, downPayment: 0, tenorYears: 30, annualRatePercent: 30 });
    assert.equal(Number.isSafeInteger(maximum.monthlyPayment), true);
    assert.ok(maximum.monthlyPayment > 0);
});

test('batas dan format input ditolak dengan penanda field', () => {
    const valid = { price: 1000000000, downPayment: 200000000, tenorYears: 20, annualRatePercent: 8 };
    for (const [field, value] of [
        ['price', 0], ['price', 100000000001], ['price', '1e9'], ['price', '100.5'],
        ['downPayment', -1], ['downPayment', 1000000000],
        ['tenorYears', 0], ['tenorYears', 31], ['tenorYears', 1.5],
        ['annualRatePercent', -1], ['annualRatePercent', 30.01], ['annualRatePercent', '8.123'],
    ]) {
        assert.throws(() => calculateInstallment({ ...valid, [field]: value }), error => error.field === field);
    }
});

test('nominal dan persentase uang muka mengikuti sumber terakhir ketika harga berubah', () => {
    assert.deepEqual(synchronizeDownPayment({ price: 1000000000, source: 'amount', value: '200000000' }), { amount: 200000000, percent: 20 });
    assert.deepEqual(synchronizeDownPayment({ price: 1000000000, source: 'percent', value: '20' }), { amount: 200000000, percent: 20 });
    assert.deepEqual(synchronizeDownPayment({ price: 1200000000, source: 'amount', value: '200000000' }), { amount: 200000000, percent: 16.67 });
    assert.deepEqual(synchronizeDownPayment({ price: 1200000000, source: 'percent', value: '20' }), { amount: 240000000, percent: 20 });
    assert.deepEqual(synchronizeDownPayment({ price: 3, source: 'percent', value: '33.33' }), { amount: 1, percent: 33.33 });
    assert.equal(synchronizeDownPayment({ price: 0, source: 'percent', value: '20' }), null);
    assert.equal(synchronizeDownPayment({ price: 1000000000, source: 'percent', value: '20.123' }), null);
    assert.deepEqual(synchronizeDownPayment({ price: 100000000000, source: 'amount', value: '99999999999' }), { amount: 99999999999, percent: null });
    assert.ok(calculateInstallment({ price: 100000000000, downPayment: 99999999999, tenorYears: 30, annualRatePercent: 0 }).downPaymentPercent < 100);
});
