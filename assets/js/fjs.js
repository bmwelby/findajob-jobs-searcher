(function () {
  function qs(sel, root) { return (root || document).querySelector(sel); }

  function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }

  function buildUrl(params) {
    const url = new URL(FJS.endpoint, window.location.origin);
    Object.entries(params).forEach(([k, v]) => {
      if (v === '' || v === null || v === undefined) return;
      const s = String(v).trim();
      if (!s) return;
      if (k === 'sf' && (s === '0' || s === '0.0')) return;
      url.searchParams.set(k, s);
    });
    return url.toString();
  }

  function renderResults(container, data, append) {
    const pager = data.pager || { total_entries: 0, pages: 0, current_page: 1 };
    const jobs = data.jobs || [];

    if (!append) container.innerHTML = '';

    if (!append) {
      const summary = document.createElement('p');
      summary.className = 'fjs__summary';
      summary.textContent = `${pager.total_entries || 0} jobs found.`;
      container.appendChild(summary);
    }

    if (!append && jobs.length === 0) {
      const p = document.createElement('p');
      p.textContent = 'No results found.';
      container.appendChild(p);
      return;
    }

    let cards = qs('.fjs__cards', container);
    if (!cards) {
      cards = document.createElement('div');
      cards.className = 'fjs__cards';
      container.appendChild(cards);
    }

    jobs.forEach(j => {
      const card = document.createElement('article');
      card.className = 'fjs__card';

      const title = escapeHtml(j.title || 'Untitled');
      const company = escapeHtml(j.company || '');
      const loc = escapeHtml(j.location || j.postcode || '');
      const salary = escapeHtml(j.salary || '');
      const cty = escapeHtml(j.contract_type || '');
      const cti = escapeHtml(j.contract_time || '');
      const permalink = j.local_permalink || '#';
      const apply = j.apply_url || '#';

      card.innerHTML = `
        <h3 class="fjs__title"><a href="${permalink}">${title}</a></h3>
        ${company ? `<p class="fjs__meta"><strong>${company}</strong></p>` : ''}
        ${loc ? `<p class="fjs__meta">${loc}</p>` : ''}
        ${(cty || cti) ? `<p class="fjs__meta">${(cty + (cti ? ' • ' + cti : '')).trim()}</p>` : ''}
        ${salary ? `<p class="fjs__meta">${salary}</p>` : ''}
        <p class="fjs__links"><a href="${permalink}">View details</a> · <a href="${apply}" target="_blank" rel="nofollow noopener">Apply</a></p>
      `;

      cards.appendChild(card);
    });

    const existingPager = qs('.fjs__pager', container);
    if (existingPager) existingPager.remove();

    if (pager.pages && pager.current_page < pager.pages) {
      const pagerEl = document.createElement('div');
      pagerEl.className = 'fjs__pager';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'fjs__loadmore';
      btn.textContent = 'Load more';
      btn.dataset.nextPage = String((pager.current_page || 1) + 1);

      btn.addEventListener('click', () => {
        const wrapper = container.closest('.fjs');
        const form = qs('.fjs__form', wrapper);
        doSearch(form, parseInt(btn.dataset.nextPage, 10), true);
      });

      pagerEl.appendChild(btn);
      container.appendChild(pagerEl);
    }
  }

  async function doSearch(form, page, append) {
    const status = qs('.fjs__status', form);
    const wrapper = form.closest('.fjs');
    const results = qs('.fjs__results', wrapper);

    results.setAttribute('aria-busy', 'true');
    status.textContent = 'Searching…';

    try {
      const fd = new FormData(form);

      const params = {
        w: fd.get('w') || '',
        d: fd.get('d') || '',
        cti: fd.get('cti') || '',
        cty: fd.get('cty') || '',
        q: fd.get('q') || '',
        sf: fd.get('sf') || '',
        p: page || 1,
      };

      if (!params.w && !params.q) {
        status.textContent = 'Please provide a location or keywords.';
        results.setAttribute('aria-busy', 'false');
        return;
      }

      const url = buildUrl(params);
      const res = await fetch(url, { method: 'GET', headers: { 'Accept': 'application/json' } });
      const data = await res.json().catch(() => ({}));

      if (res.status === 429) {
        const retry = data.retry_after_seconds || parseInt(res.headers.get('Retry-After') || '0', 10);
        status.textContent = retry ? `Rate limit reached. Try again in ~${retry}s.` : 'Rate limit reached. Try again shortly.';
        results.setAttribute('aria-busy', 'false');
        return;
      }

      if (!res.ok || data.error) {
        status.textContent = 'Sorry—there was a problem fetching results.';
        results.setAttribute('aria-busy', 'false');
        return;
      }

      renderResults(results, data, append);
      status.textContent = '';
    } catch (e) {
      console.error(e);
      status.textContent = 'Sorry—there was a problem fetching results.';
    } finally {
      results.setAttribute('aria-busy', 'false');
    }
  }

  document.addEventListener('submit', (e) => {
    const form = e.target.closest('.fjs__form');
    if (!form) return;
    e.preventDefault();
    doSearch(form, 1, false);
  });
})();
