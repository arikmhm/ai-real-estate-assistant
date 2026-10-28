import { CalculationInputError, calculateInstallment, synchronizeDownPayment } from './mortgage-calculator';

document.addEventListener('DOMContentLoaded', () => {
    const section = document.querySelector('[data-mortgage-calculator]');
    if (!section) return;

    const form = section.querySelector('[data-mortgage-form]');
    const fields = {
        price: section.querySelector('[data-mortgage-price]'),
        downPayment: section.querySelector('[data-mortgage-amount]'),
        downPaymentPercent: section.querySelector('[data-mortgage-percent]'),
        tenorYears: section.querySelector('[data-mortgage-years]'),
        annualRatePercent: section.querySelector('[data-mortgage-rate]'),
    };
    const errorElement = section.querySelector('[data-mortgage-error]');
    const resultElement = section.querySelector('[data-mortgage-result]');
    const outputs = Object.fromEntries(
        [...section.querySelectorAll('[data-mortgage-output]')].map(element => [element.dataset.mortgageOutput, element]),
    );
    const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
    const percent = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
    let lastEdited = null;
    let attempted = false;

    const hideFeedback = () => {
        errorElement.textContent = '';
        errorElement.hidden = true;
        resultElement.hidden = true;
    };

    const synchronize = () => {
        if (!lastEdited) return;
        const sourceField = lastEdited === 'amount' ? fields.downPayment : fields.downPaymentPercent;
        const targetField = lastEdited === 'amount' ? fields.downPaymentPercent : fields.downPayment;
        if (sourceField.value === '') {
            targetField.value = '';
            return;
        }
        const pair = synchronizeDownPayment({ price: fields.price.value, source: lastEdited, value: sourceField.value });
        const targetValue = pair && (lastEdited === 'amount' ? pair.percent : pair.amount);
        targetField.value = targetValue === null || targetValue === false ? '' : String(targetValue);
    };

    const render = (focusInvalid = false) => {
        hideFeedback();
        if (!attempted) return;
        try {
            if (lastEdited === 'percent' && fields.downPaymentPercent.value !== '' && !synchronizeDownPayment({ price: fields.price.value, source: 'percent', value: fields.downPaymentPercent.value })) {
                throw new CalculationInputError('downPaymentPercent', 'Uang muka persen harus 0–99,99% dengan maksimal dua desimal dan tetap di bawah harga.');
            }
            const result = calculateInstallment({
                price: fields.price.value,
                downPayment: fields.downPayment.value,
                tenorYears: fields.tenorYears.value,
                annualRatePercent: fields.annualRatePercent.value,
            });
            const downPaymentPercent = result.downPaymentPercent > 99.99 && result.downPaymentPercent < 100
                ? '>99,99' : percent.format(result.downPaymentPercent);
            outputs.monthlyPayment.textContent = rupiah.format(result.monthlyPayment);
            outputs.price.textContent = rupiah.format(result.price);
            outputs.downPayment.textContent = `${rupiah.format(result.downPayment)} (${downPaymentPercent}%)`;
            outputs.principal.textContent = rupiah.format(result.principal);
            outputs.terms.textContent = `${result.tenorYears} tahun · ${percent.format(result.annualRatePercent)}% per tahun`;
            resultElement.hidden = false;
        } catch (error) {
            if (!(error instanceof CalculationInputError)) throw error;
            errorElement.textContent = error.message;
            errorElement.hidden = false;
            if (focusInvalid) fields[error.field]?.focus();
        }
    };

    form.addEventListener('input', event => {
        if (event.target === fields.downPayment) lastEdited = 'amount';
        if (event.target === fields.downPaymentPercent) lastEdited = 'percent';
        if ([fields.price, fields.downPayment, fields.downPaymentPercent].includes(event.target)) synchronize();
        render();
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        attempted = true;
        synchronize();
        render(true);
    });
    form.addEventListener('reset', () => {
        queueMicrotask(() => {
            lastEdited = null;
            attempted = false;
            hideFeedback();
        });
    });
});
