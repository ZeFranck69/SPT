const initMobileNavigation = () => {
  const toggles = document.querySelectorAll('[data-tf-menu-toggle]');
  const panel = document.querySelector('.tf-site-header [data-tf-menu-panel]');
  const mobileViewport = window.matchMedia('(max-width: 640px)');

  if (!toggles.length || !panel) {
    return;
  }

  panel.id ||= 'tf-primary-menu';
  toggles.forEach((toggle) => toggle.setAttribute('aria-controls', panel.id));

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
      const isOpen = !document.documentElement.classList.contains('tf-menu-is-open');
      setOpen(isOpen);
      if (isOpen) panel.querySelector('a')?.focus();
    });
  });

  document.addEventListener('pointerdown', (event) => {
    if (!panel.contains(event.target) && ![...toggles].some((toggle) => toggle.contains(event.target))) {
      setOpen(false);
    }
  });

  document.addEventListener('focusin', (event) => {
    if (!panel.contains(event.target) && ![...toggles].some((toggle) => toggle.contains(event.target))) {
      setOpen(false);
    }
  });

  mobileViewport.addEventListener('change', () => setOpen(false));

  panel.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setOpen(false));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && document.documentElement.classList.contains('tf-menu-is-open')) {
      setOpen(false);
      toggles[0].focus();
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

const initApp = () => {
  initMobileNavigation();
  initHistoryBackButtons();
  initQuantityControls();
  initScrollReveals();
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp, { once: true });
} else {
  initApp();
}
