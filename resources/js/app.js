document.addEventListener('DOMContentLoaded', () => {
    const city = document.querySelector('#filter-city');
    const district = document.querySelector('#filter-district');

    if (!city || !district) return;

    const updateDistricts = () => {
        for (const option of district.querySelectorAll('option[data-city]')) {
            option.hidden = option.dataset.city !== city.value;
            option.disabled = option.dataset.city !== city.value;
        }
        district.disabled = city.value === '';
    };

    city.addEventListener('change', () => {
        district.value = '';
        updateDistricts();
    });
    updateDistricts();
});
import './assistant';
import './mortgage-ui';
