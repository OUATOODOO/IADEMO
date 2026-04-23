/* ============================================================
   Atelier du Guidon — main.js
   Menu mobile · Simulateur Bonus · Fade-in · Cookie banner
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

  /* ── Menu mobile ─────────────────────────────────────────── */
  const hamburger  = document.querySelector('.nav-hamburger');
  const mobileMenu = document.querySelector('.mobile-menu');

  if (hamburger && mobileMenu) {
    hamburger.addEventListener('click', () => {
      const isOpen = mobileMenu.classList.toggle('open');
      hamburger.classList.toggle('open', isOpen);
      hamburger.setAttribute('aria-expanded', isOpen);
    });
    // Fermer le menu au clic sur un lien
    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mobileMenu.classList.remove('open');
        hamburger.classList.remove('open');
        hamburger.setAttribute('aria-expanded', false);
      });
    });
  }

  /* ── Simulateur Bonus Réparation ─────────────────────────── */
  const simOptions = document.querySelectorAll('.sim-option');
  const simResult  = document.getElementById('sim-result');

  const bonusData = {
    'petit'    : { label: 'Crevaison / câble / petite réparation', min: 30,  bonus: 0,  msg: 'Pas d\'aide applicable — montant < 65 €. Nos prix restent transparents !' },
    'moyen'    : { label: 'Révision Essentielle (~65 €)',           min: 65,  bonus: 15, msg: null },
    'complet'  : { label: 'Révision Complète (~90 €)',              min: 90,  bonus: 15, msg: null },
    'integral' : { label: 'Révision Intégrale (~120 €)',            min: 120, bonus: 30, msg: null },
  };

  function updateSimResult(value) {
    if (!simResult) return;
    const data = bonusData[value];
    if (!data) return;
    if (data.bonus === 0) {
      simResult.innerHTML = `<span>ℹ️</span> <span>${data.msg}</span>`;
    } else {
      simResult.innerHTML = `
        <span>🎉</span>
        <span>
          Vous bénéficiez de <strong class="sim-result-amount">-${data.bonus} €</strong>
          grâce au Bonus Réparation — soit environ
          <strong>${data.min - data.bonus} € TTC</strong> pour vous.
        </span>`;
    }
  }

  simOptions.forEach(option => {
    option.addEventListener('click', () => {
      simOptions.forEach(o => o.classList.remove('selected'));
      option.classList.add('selected');
      const radio = option.querySelector('input[type=radio]');
      if (radio) {
        radio.checked = true;
        updateSimResult(radio.value);
      }
    });
  });

  // Initialiser avec le premier choix
  if (simOptions.length > 0) {
    simOptions[0].click();
  }

  /* ── Formulaire RDV ──────────────────────────────────────── */
  const rdvForm    = document.getElementById('rdv-form');
  const formCard   = rdvForm ? rdvForm.closest('.rdv-form-card') : null;
  const formSucess = document.getElementById('form-success');

  if (rdvForm) {
    rdvForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = rdvForm.querySelector('.form-submit-btn');
      btn.disabled = true;
      btn.textContent = 'Envoi en cours…';

      try {
        const res = await fetch('send.php', {
          method: 'POST',
          body: new FormData(rdvForm),
        });
        const json = await res.json();
        if (json.ok) {
          rdvForm.style.display = 'none';
          if (formSucess) formSucess.style.display = 'block';
        } else {
          throw new Error(json.error || 'Erreur serveur');
        }
      } catch (err) {
        btn.disabled = false;
        btn.textContent = 'Envoyer ma demande';
        alert('Une erreur est survenue. Merci de nous appeler directement : 05 56 XX XX XX');
      }
    });
  }

  /* ── Fade-in au scroll ───────────────────────────────────── */
  const fadeEls = document.querySelectorAll('.fade-in');
  if (fadeEls.length && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    fadeEls.forEach(el => io.observe(el));
  } else {
    fadeEls.forEach(el => el.classList.add('visible'));
  }

  /* ── Cookie banner ───────────────────────────────────────── */
  const banner   = document.getElementById('cookie-banner');
  const btnOk    = document.getElementById('cookie-accept');
  const btnRefus = document.getElementById('cookie-refuse');

  if (banner && !localStorage.getItem('cookie-consent')) {
    setTimeout(() => banner.classList.add('visible'), 1200);
  }
  [btnOk, btnRefus].forEach(btn => {
    if (btn) btn.addEventListener('click', () => {
      localStorage.setItem('cookie-consent', btn.dataset.value);
      banner.classList.remove('visible');
    });
  });

  /* ── Nav active link au scroll ───────────────────────────── */
  const sections  = document.querySelectorAll('section[id]');
  const navLinks  = document.querySelectorAll('.nav-links a[href^="#"]');
  const scrollSpy = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        navLinks.forEach(a => a.removeAttribute('aria-current'));
        const active = document.querySelector(`.nav-links a[href="#${entry.target.id}"]`);
        if (active) active.setAttribute('aria-current', 'page');
      }
    });
  }, { rootMargin: '-40% 0px -55% 0px' });
  sections.forEach(s => scrollSpy.observe(s));

});
