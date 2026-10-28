const panel = document.querySelector('[data-assistant-chat]');

if (panel) {
    const storageKey = 'property-assistant-demo-v1';
    const form = panel.querySelector('[data-assistant-form]');
    const input = panel.querySelector('[data-assistant-input]');
    const send = panel.querySelector('[data-assistant-send]');
    const messages = panel.querySelector('[data-assistant-messages]');
    let state = { history: [], lastListingIds: [] };

    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
        if (Array.isArray(saved.history)) state.history = saved.history.filter(item => typeof item === 'string').slice(-4);
        if (Array.isArray(saved.lastListingIds)) state.lastListingIds = saved.lastListingIds.filter(Number.isInteger).slice(0, 3);
    } catch { /* A blocked or invalid tab store starts a fresh conversation. */ }

    const save = () => {
        try { sessionStorage.setItem(storageKey, JSON.stringify(state)); } catch { /* Chat still works without tab storage. */ }
    };
    const line = (label, value) => {
        const p = document.createElement('p');
        const strong = document.createElement('strong');
        strong.textContent = `${label}: `;
        p.append(strong, document.createTextNode(value));
        messages.append(p);
        messages.scrollTop = messages.scrollHeight;
    };
    const link = (label, url) => {
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.textContent = label;
        anchor.className = 'font-medium text-emerald-800 underline';
        anchor.rel = 'noopener';
        return anchor;
    };
    const showResults = data => {
        line('Asisten', data.answer);
        if (data.mode === 'compare' && data.listings?.length) {
            const wrapper = document.createElement('div');
            wrapper.className = 'overflow-x-auto';
            const table = document.createElement('table');
            table.className = 'w-full min-w-[32rem] border-collapse text-left';
            const head = document.createElement('thead');
            const row = document.createElement('tr');
            for (const label of ['Properti', 'Harga', 'Lokasi', 'Tanah', 'Bangunan', 'Kamar']) {
                const cell = document.createElement('th');
                cell.scope = 'col'; cell.className = 'border-b p-2'; cell.textContent = label; row.append(cell);
            }
            head.append(row); table.append(head);
            const body = document.createElement('tbody');
            for (const item of data.listings) {
                const tr = document.createElement('tr');
                const name = document.createElement('td'); name.className = 'border-b p-2'; name.append(link(item.title, item.url)); tr.append(name);
                for (const value of [item.price_formatted, item.district || item.city || 'Belum dicatat', item.land_area_m2 ? `${item.land_area_m2} m²` : 'Belum dicatat', item.building_area_m2 ? `${item.building_area_m2} m²` : 'Belum dicatat', item.rooms ?? 'Belum dicatat']) {
                    const td = document.createElement('td'); td.className = 'border-b p-2'; td.textContent = value; tr.append(td);
                }
                body.append(tr);
            }
            table.append(body); wrapper.append(table); messages.append(wrapper);
            messages.scrollTop = messages.scrollHeight;
            return;
        }
        for (const listing of data.listings || []) {
            const card = document.createElement('div');
            card.className = 'rounded-lg border border-stone-200 p-3';
            card.append(link(listing.title, listing.url));
            const detail = document.createElement('p');
            detail.textContent = `${listing.price_formatted} · ${listing.district || listing.city || 'Lokasi belum dicatat'} · ${listing.reason}`;
            card.append(detail);
            messages.append(card);
        }
        messages.scrollTop = messages.scrollHeight;
    };

    panel.querySelector('[data-assistant-clear]').addEventListener('click', () => {
        state = { history: [], lastListingIds: [] };
        try { sessionStorage.removeItem(storageKey); } catch { /* Storage may be unavailable. */ }
        messages.replaceChildren();
        line('Asisten', 'Percakapan di tab ini telah dihapus.');
        input.focus();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || message.length > 500) return;
        line('Anda', message);
        input.value = '';
        input.disabled = true;
        send.disabled = true;
        try {
            const response = await fetch(panel.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ message, history: state.history, last_listing_ids: state.lastListingIds }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Asisten tidak tersedia.');
            showResults(data);
            state.history = [...state.history, message].slice(-4);
            state.lastListingIds = (data.listings || []).map(item => item.id).slice(0, 3);
            save();
        } catch (error) {
            line('Asisten', error.message || 'Asisten tidak tersedia.');
            messages.append(link('Gunakan filter katalog', panel.querySelector('a[href]').href));
        } finally {
            input.disabled = false;
            send.disabled = false;
            input.focus();
        }
    });
}
