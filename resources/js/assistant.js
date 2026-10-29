const root = document.querySelector('[data-assistant-chat]');

if (root) {
    const storageKey = 'property-assistant-demo-v1';
    const uiKey = 'property-assistant-ui-v1';
    const toggle = root.querySelector('[data-assistant-toggle]');
    const panel = root.querySelector('[data-assistant-panel]');
    const form = root.querySelector('[data-assistant-form]');
    const input = root.querySelector('[data-assistant-input]');
    const send = root.querySelector('[data-assistant-send]');
    const messages = root.querySelector('[data-assistant-messages]');
    let state = { history: [], lastListingIds: [] };
    let ui = { open: false, expanded: false };

    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey) || '{}');
        if (Array.isArray(saved.history)) state.history = saved.history.filter(item => typeof item === 'string').slice(-4);
        if (Array.isArray(saved.lastListingIds)) state.lastListingIds = saved.lastListingIds.filter(Number.isInteger).slice(0, 3);
        const savedUi = JSON.parse(sessionStorage.getItem(uiKey) || '{}');
        ui = { open: savedUi.open === true, expanded: savedUi.expanded === true };
    } catch { /* A blocked or invalid tab store starts a fresh conversation. */ }

    const save = () => {
        try { sessionStorage.setItem(storageKey, JSON.stringify(state)); } catch { /* Chat still works without tab storage. */ }
    };
    const saveUi = () => {
        try { sessionStorage.setItem(uiKey, JSON.stringify(ui)); } catch { /* Window state is only a convenience. */ }
    };
    const scrollDown = () => { messages.scrollTop = messages.scrollHeight; };
    const bubble = (role, text) => {
        const div = document.createElement('div');
        div.className = role === 'user'
            ? 'max-w-[85%] self-end whitespace-pre-line rounded-2xl rounded-br-sm bg-stone-950 px-3.5 py-2.5 text-white'
            : 'max-w-[85%] self-start whitespace-pre-line rounded-2xl rounded-bl-sm bg-stone-100 px-3.5 py-2.5';
        div.textContent = text;
        messages.append(div);
        scrollDown();
        return div;
    };
    const link = (label, url) => {
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.textContent = label;
        anchor.className = 'font-medium text-stone-950 underline underline-offset-2';
        anchor.rel = 'noopener';
        return anchor;
    };
    const showResults = data => {
        bubble('assistant', data.answer);
        if (data.mode === 'compare' && data.listings?.length) {
            const wrapper = document.createElement('div');
            wrapper.className = 'max-w-full shrink-0 self-start overflow-x-auto rounded-2xl border border-stone-200';
            const table = document.createElement('table');
            table.className = 'w-full min-w-[32rem] border-collapse text-left text-xs';
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
        } else {
            for (const listing of data.listings || []) {
                const card = document.createElement('div');
                card.className = 'max-w-[85%] self-start rounded-2xl border border-stone-200 px-3.5 py-2.5';
                card.append(link(listing.title, listing.url));
                const detail = document.createElement('p');
                detail.className = 'mt-1 text-xs text-stone-600';
                detail.textContent = `${listing.price_formatted} · ${listing.district || listing.city || 'Lokasi belum dicatat'} · ${listing.code}`;
                card.append(detail);
                messages.append(card);
            }
        }
        if (data.listings?.length) messages.append(moreLink('Lihat semua di katalog', data.catalog_url));
        for (const contact of data.contacts || []) {
            const card = document.createElement('div');
            card.className = 'flex max-w-[85%] flex-wrap items-center gap-x-3 gap-y-1.5 self-start rounded-2xl border border-stone-200 px-3.5 py-2.5';
            const name = document.createElement('p');
            name.className = 'w-full font-medium';
            name.textContent = contact.name;
            const whatsapp = link(`WhatsApp ${contact.name}`, contact.whatsapp_url);
            whatsapp.className = 'rounded-full bg-brand px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-strong';
            whatsapp.target = '_blank';
            whatsapp.rel = 'noopener noreferrer';
            card.append(name, whatsapp);
            if (contact.email) {
                const email = link(`Email ${contact.name}`, `mailto:${contact.email}`);
                email.className = 'text-xs text-stone-600 underline underline-offset-2';
                card.append(email);
            }
            messages.append(card);
        }
        if (data.contacts?.length) messages.append(moreLink('Lihat semua sales', data.contacts_url));
        scrollDown();
    };
    const moreLink = (label, url) => {
        const anchor = link(label, url);
        anchor.className = 'self-start text-xs text-stone-600 underline underline-offset-2 hover:text-stone-950';
        return anchor;
    };
    const render = () => {
        panel.hidden = !ui.open;
        panel.toggleAttribute('data-expanded', ui.expanded);
        toggle.setAttribute('aria-expanded', String(ui.open));
        toggle.setAttribute('aria-label', ui.open ? 'Tutup asisten properti' : 'Buka asisten properti');
    };
    const setOpen = open => {
        ui.open = open;
        render();
        saveUi();
        if (open) { scrollDown(); input.focus(); } else { toggle.focus(); }
    };

    if (state.history.length) bubble('assistant', 'Pertanyaan sebelumnya masih diingat untuk pertanyaan lanjutan.');
    render();

    toggle.addEventListener('click', () => setOpen(!ui.open));
    root.querySelector('[data-assistant-close]').addEventListener('click', () => setOpen(false));
    root.querySelector('[data-assistant-expand]').addEventListener('click', () => {
        ui.expanded = !ui.expanded;
        render();
        saveUi();
    });
    // Listen on the document: a panel restored open after navigation does not hold focus yet.
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && ui.open) setOpen(false);
    });
    for (const chip of root.querySelectorAll('[data-assistant-suggestion]')) {
        chip.addEventListener('click', () => { input.value = chip.textContent.trim(); input.focus(); });
    }

    root.querySelector('[data-assistant-clear]').addEventListener('click', () => {
        state = { history: [], lastListingIds: [] };
        try { sessionStorage.removeItem(storageKey); } catch { /* Storage may be unavailable. */ }
        messages.replaceChildren();
        bubble('assistant', 'Percakapan di tab ini telah dihapus.');
        input.focus();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || message.length > 500) return;
        root.querySelector('[data-assistant-suggestions]')?.remove();
        bubble('user', message);
        input.value = '';
        input.disabled = true;
        send.disabled = true;
        const typing = bubble('assistant', '');
        typing.setAttribute('aria-label', 'Asisten sedang mengetik');
        typing.innerHTML = '<span class="inline-flex gap-1"><span class="size-1.5 animate-bounce rounded-full bg-stone-400"></span><span class="size-1.5 animate-bounce rounded-full bg-stone-400 [animation-delay:150ms]"></span><span class="size-1.5 animate-bounce rounded-full bg-stone-400 [animation-delay:300ms]"></span></span>';
        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ message, history: state.history, last_listing_ids: state.lastListingIds }),
            });
            const data = await response.json();
            typing.remove();
            if (!response.ok) throw new Error(data.error || 'Asisten tidak tersedia.');
            showResults(data);
            state.history = [...state.history, message].slice(-4);
            // Only a new search replaces the positions that "pilihan 1–3" refer to.
            if (data.mode === 'search' && data.listings?.length) state.lastListingIds = data.listings.map(item => item.id).slice(0, 3);
            save();
        } catch (error) {
            typing.remove();
            const reply = bubble('assistant', `${error.message || 'Asisten tidak tersedia.'} `);
            reply.append(link('Gunakan filter katalog', root.dataset.catalogUrl));
        } finally {
            input.disabled = false;
            send.disabled = false;
            input.focus();
        }
    });
}
