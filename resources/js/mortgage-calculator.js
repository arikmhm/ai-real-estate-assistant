const MAX_PRICE = 100_000_000_000;

export class CalculationInputError extends Error {
    constructor(field, message) {
        super(message);
        this.name = 'CalculationInputError';
        this.field = field;
    }
}

function integer(value, field, minimum, maximum, message) {
    const text = String(value ?? '').trim();
    const number = Number(text);
    if (!/^\d+$/.test(text) || !Number.isSafeInteger(number) || number < minimum || number > maximum) {
        throw new CalculationInputError(field, message);
    }
    return number;
}

function decimal(value, field, minimum, maximum, message) {
    const text = String(value ?? '').trim().replace(',', '.');
    const number = Number(text);
    if (!/^\d+(?:\.\d{1,2})?$/.test(text) || !Number.isFinite(number) || number < minimum || number > maximum) {
        throw new CalculationInputError(field, message);
    }
    return number;
}

export function synchronizeDownPayment({ price, source, value }) {
    try {
        const currentPrice = integer(price, 'price', 1, MAX_PRICE, 'Harga tidak valid.');
        if (source === 'amount') {
            const amount = integer(value, 'downPayment', 0, currentPrice - 1, 'Uang muka tidak valid.');
            const roundedPercent = Math.round(amount / currentPrice * 10_000) / 100;
            return { amount, percent: roundedPercent < 100 ? roundedPercent : null };
        }
        if (source === 'percent') {
            const percent = decimal(value, 'downPaymentPercent', 0, 99.99, 'Persentase uang muka tidak valid.');
            const amount = Math.round(currentPrice * percent / 100);
            return amount < currentPrice ? { amount, percent } : null;
        }
    } catch (error) {
        if (!(error instanceof CalculationInputError)) throw error;
    }
    return null;
}

export function calculateInstallment({ price, downPayment, tenorYears, annualRatePercent }) {
    const currentPrice = integer(price, 'price', 1, MAX_PRICE, 'Harga harus Rp1 sampai Rp100 miliar tanpa desimal.');
    const amount = integer(downPayment, 'downPayment', 0, currentPrice - 1, 'Uang muka harus Rp0 sampai kurang dari harga.');
    const years = integer(tenorYears, 'tenorYears', 1, 30, 'Tenor harus 1 sampai 30 tahun bulat.');
    const annualRate = decimal(annualRatePercent, 'annualRatePercent', 0, 30, 'Bunga tahunan harus 0–30% dengan maksimal dua desimal.');
    const principal = currentPrice - amount;
    const months = years * 12;
    const monthlyRate = annualRate / 100 / 12;
    const rawPayment = monthlyRate === 0
        ? principal / months
        : principal * monthlyRate / -Math.expm1(-months * Math.log1p(monthlyRate));

    return {
        price: currentPrice,
        downPayment: amount,
        downPaymentPercent: amount / currentPrice * 100,
        principal,
        tenorYears: years,
        annualRatePercent: annualRate,
        monthlyPayment: Math.round(rawPayment),
    };
}
