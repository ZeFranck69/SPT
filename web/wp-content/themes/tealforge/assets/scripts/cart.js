const checkoutFilters = window.wc?.blocksCheckout;

checkoutFilters?.registerCheckoutFilters('tealforge-cart', {
  proceedToCheckoutButtonLabel: () => 'Passer au paiement',
});

const initCartCount = () => {
  const counters = document.querySelectorAll('[data-tf-cart-count]');
  const cartStore = window.wc?.wcBlocksData?.cartStore;
  const data = window.wp?.data;
  const i18n = window.wp?.i18n;

  if (!counters.length || !cartStore || !data || !i18n) {
    return;
  }

  let previousCount;

  const updateCount = () => {
    const store = data.select(cartStore);
    const { itemsCount } = store.getCartData();

    if (!store.hasFinishedResolution('getCartData') || !Number.isInteger(itemsCount)
      || itemsCount < 0 || itemsCount === previousCount) {
      return;
    }

    previousCount = itemsCount;
    const label = i18n.sprintf(
      i18n._n('%d article dans le panier', '%d articles dans le panier', itemsCount, 'tealforge'),
      itemsCount,
    );

    counters.forEach((counter) => {
      counter.textContent = String(itemsCount);
      counter.hidden = itemsCount === 0;
      counter.setAttribute('aria-label', label);
    });
  };

  data.subscribe(updateCount, cartStore);
  updateCount();
};

const initProductLinks = () => {
  const review = document.querySelector('[data-tf-purchase-review]');

  if (!review) {
    return;
  }

  const removeLinks = () => {
    review.querySelectorAll(
      'a.wc-block-components-product-name[href], .wc-block-cart-item__image a[href], .wc-block-components-order-summary-item__image a[href]',
    ).forEach((link) => {
      link.removeAttribute('href');
      link.removeAttribute('target');
      link.removeAttribute('aria-label');
    });
  };

  removeLinks();
  const observer = new MutationObserver(removeLinks);
  observer.observe(review, { childList: true, subtree: true, attributes: true, attributeFilter: ['href'] });
};

const formatRegionalPhone = (phone, country, replacePrefix = false) => {
  const prefix = { NC: '+687', WF: '+681' }[country];
  const compact = phone.replace(/[\s().-]/g, '').replace(/^00/, '+');

  if (!prefix) return phone;
  if (!compact) return `${prefix} `;
  if (compact.startsWith(prefix)) return `${prefix} ${compact.slice(4)}`;
  if (replacePrefix && /^\+(687|681)/.test(compact)) return `${prefix} ${compact.slice(4)}`;
  if (/^\d+$/.test(compact)) return `${prefix} ${compact}`;
  return phone;
};

const initRegionalPhone = () => {
  const checkout = document.querySelector('[data-tf-checkout-contact]');
  const cartStore = window.wc?.wcBlocksData?.cartStore;
  const data = window.wp?.data;

  if (!checkout || !cartStore || !data) return;

  let previousCountry;
  const syncCountry = () => {
    const store = data.select(cartStore);
    const { billingAddress } = store.getCartData();
    if (!store.hasFinishedResolution('getCartData') || billingAddress.country === previousCountry) return;

    const replacePrefix = previousCountry !== undefined;
    previousCountry = billingAddress.country;
    const phone = formatRegionalPhone(billingAddress.phone || '', billingAddress.country, replacePrefix);
    if (phone !== billingAddress.phone) data.dispatch(cartStore).setBillingAddress({ phone });
  };

  checkout.addEventListener('focusout', (event) => {
    if (!event.target.matches('.wc-block-components-address-form__phone input')) return;
    const { billingAddress } = data.select(cartStore).getCartData();
    const phone = formatRegionalPhone(event.target.value, billingAddress.country);
    if (phone !== billingAddress.phone) data.dispatch(cartStore).setBillingAddress({ phone });
  });

  data.subscribe(syncCountry, cartStore);
  syncCountry();
};

const initPurchaseReview = () => {
  initCartCount();
  initProductLinks();
  initRegionalPhone();
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPurchaseReview, { once: true });
} else {
  initPurchaseReview();
}
