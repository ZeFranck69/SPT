const initMobileNavigation = () => {
  const toggles = document.querySelectorAll('[data-tf-menu-toggle]');
  const panel = document.querySelector('.tf-site-header [data-tf-menu-panel]');
  const closeButtons = document.querySelectorAll('[data-tf-menu-close]');

  if (!toggles.length || !panel) {
    return;
  }

  const setOpen = (isOpen) => {
    document.documentElement.classList.toggle('tf-menu-is-open', isOpen);
    toggles.forEach((toggle) => {
      toggle.setAttribute('aria-expanded', String(isOpen));
      toggle.setAttribute(
        'aria-label',
        isOpen ? toggle.dataset.tfMenuCloseLabel : toggle.dataset.tfMenuOpenLabel,
      );
    });
  };

  toggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      setOpen(!document.documentElement.classList.contains('tf-menu-is-open'));
    });
  });

  closeButtons.forEach((button) => {
    button.addEventListener('click', () => setOpen(false));
  });

  panel.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setOpen(false));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setOpen(false);
    }
  });
};

const initHistoryBackButtons = () => {
  const buttons = document.querySelectorAll('[data-tf-history-back]');

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      if (window.history.length > 1) {
        window.history.back();
        return;
      }

      window.location.href = '/';
    });
  });
};

const initQuantityControls = () => {
  const controls = document.querySelectorAll('[data-tf-quantity]');

  controls.forEach((control) => {
    const input = control.querySelector('input[type="number"]');
    const decrease = control.querySelector('[data-tf-quantity-decrease]');
    const increase = control.querySelector('[data-tf-quantity-increase]');

    if (!input || !decrease || !increase) {
      return;
    }

    const min = Number(input.min) || 1;
    const max = Number(input.max) || Number.MAX_SAFE_INTEGER;

    const setValue = (value) => {
      const nextValue = Math.min(max, Math.max(min, Math.round(value) || min));
      input.value = String(nextValue);
      decrease.disabled = nextValue <= min;
      increase.disabled = nextValue >= max;
    };

    decrease.addEventListener('click', () => setValue(Number(input.value) - 1));
    increase.addEventListener('click', () => setValue(Number(input.value) + 1));
    input.addEventListener('change', () => setValue(Number(input.value)));

    setValue(Number(input.value));
  });
};

const initScrollReveals = () => {
  const elements = document.querySelectorAll('[data-tf-reveal]');

  if (!elements.length) {
    return;
  }

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
    elements.forEach((element) => element.classList.add('tf-reveal--visible'));
    return;
  }

  elements.forEach((element) => element.classList.add('tf-reveal'));

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) {
        return;
      }

      entry.target.classList.add('tf-reveal--visible');
      observer.unobserve(entry.target);
    });
  }, {
    threshold: 0.12,
    rootMargin: '0px 0px -48px',
  });

  elements.forEach((element) => observer.observe(element));
};

const initHeroParallax = () => {
  const hero = document.querySelector('[data-tf-parallax-hero]');

  if (!hero || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  let frame = null;

  const updatePosition = () => {
    frame = null;
    const bounds = hero.getBoundingClientRect();
    const viewportHeight = window.innerHeight;

    if (bounds.bottom <= 0 || bounds.top >= viewportHeight) {
      return;
    }

    const progress = (viewportHeight - bounds.top) / (viewportHeight + bounds.height);
    const offset = (progress - 0.5) * 64;
    hero.style.setProperty('--tf-hero-parallax-y', `${offset.toFixed(2)}px`);
  };

  const requestUpdate = () => {
    if (frame === null) {
      frame = window.requestAnimationFrame(updatePosition);
    }
  };

  window.addEventListener('scroll', requestUpdate, { passive: true });
  window.addEventListener('resize', requestUpdate);
  requestUpdate();
};

document.addEventListener('DOMContentLoaded', () => {
  initMobileNavigation();
  initHistoryBackButtons();
  initQuantityControls();
  initScrollReveals();
  initHeroParallax();
});
