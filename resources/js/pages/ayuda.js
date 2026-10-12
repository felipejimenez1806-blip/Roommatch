document.addEventListener('DOMContentLoaded', () => {

  // ---------- ACORDEÓN ----------
  document.querySelectorAll('.help-question').forEach((btn) => {
    btn.addEventListener('click', () => {
      btn.closest('.help-item').classList.toggle('open');
    });
  });

  // ---------- CHIP ACTIVO SEGÚN SCROLL ----------
  const chips = document.querySelectorAll('.help-cat-chip');
  const groups = document.querySelectorAll('.help-group');

  chips.forEach((chip) => {
    chip.addEventListener('click', () => {
      chips.forEach((c) => c.classList.remove('active'));
      chip.classList.add('active');
    });
  });

  if ('IntersectionObserver' in window && groups.length) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          chips.forEach((c) => c.classList.remove('active'));
          const match = document.querySelector(`.help-cat-chip[data-cat="${entry.target.id}"]`);
          if (match) match.classList.add('active');
        }
      });
    }, { rootMargin: '-100px 0px -70% 0px' });

    groups.forEach((g) => observer.observe(g));
  }

  // ---------- BÚSQUEDA ----------
  const input = document.getElementById('helpSearchInput');
  const searchBtn = document.getElementById('helpSearchBtn');
  const noResults = document.getElementById('helpNoResults');
  const items = document.querySelectorAll('.help-item');

  function normalizar(texto) {
    return texto
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  function filtrar() {
    const termino = normalizar(input.value.trim());
    let visibles = 0;

    items.forEach((item) => {
      const texto = normalizar(item.textContent);
      const coincide = termino === '' || texto.includes(termino);
      item.classList.toggle('help-hidden', !coincide);
      if (coincide) visibles++;
      if (termino !== '' && coincide) item.classList.add('open');
      if (termino === '') item.classList.remove('open');
    });

    document.querySelectorAll('.help-group').forEach((group) => {
      const algunaVisible = group.querySelectorAll('.help-item:not(.help-hidden)').length > 0;
      group.style.display = algunaVisible ? '' : 'none';
    });

    noResults.style.display = visibles === 0 ? 'block' : 'none';
  }

  input.addEventListener('input', filtrar);
  searchBtn.addEventListener('click', filtrar);
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); filtrar(); }
  });
});
