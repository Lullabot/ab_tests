(function (document, Drupal, once) {
  'use strict';

  const mParticle = (Drupal.mParticle = Drupal.mParticle || {});

  const getBlockHeading = (clickedElement) =>
    clickedElement
      .closest('.block__ab-testable-block')
      ?.querySelector('h2')
      ?.innerText?.trim();
  const getContentPosition = (clickedElement) => {
    const siblingAncestor = clickedElement.closest('article').parentElement;
    const index = Array.from(siblingAncestor.parentNode.children).indexOf(
      siblingAncestor,
    );
    return index === -1 ? null : index + 1;
  };
  const getLinkedText = (clickedElement) =>
    mParticle.trackingTextForAnchor(clickedElement.closest('a'));

  Drupal.behaviors.trackingClickHelper = {
    trackLinks(
      element,
      target,
      flagName,
      flagIndex,
      blockPluginLabel,
      contentType,
    ) {
      if (!target) {
        // Cannot track links if there is no block to track.
        return;
      }
      const selector = `[data-ab-blocks-placement-id="${target.getAttribute(
        'data-ab-blocks-placement-id',
      )}"] a`;
      const mParticleAttributeBuilder = (clickedElement) => {
        const attrs = {
          'Content Position': getContentPosition(clickedElement),
          'Custom Shelf Title': getBlockHeading(clickedElement),
          'Item Clicked Name': getLinkedText(clickedElement),
        };
        if (flagName) {
          attrs['LD Feature Flag'] = `${flagName}::${flagIndex}`;
        }
        return attrs;
      };
      const elements = mParticle.trackClickEvent_ABTesting(
        selector,
        mParticleAttributeBuilder,
        element,
      );
      if (elements.length) {
        console.debug(
          '[A/B Blocks] Attaching click tracking to the links in the A/B tested block.',
          { elements, blockPluginLabel, flagName },
        );
      }

      if (typeof s !== 'undefined') {
        const adobeListener = ({ target: clickedElement }) => {
          const position = getContentPosition(clickedElement);
          let trackingValue = `${contentType}:${blockPluginLabel}`;
          if (flagName) {
            trackingValue = `${trackingValue}:${flagName}=${flagIndex}`;
          }
          trackingValue = `${trackingValue}|${position}`;
          s.Util.cookieWrite('linktrk', trackingValue, 0);
        };
        const elements = once('adobe-click-tracking', selector, element);
        elements.forEach((element) =>
          element.addEventListener('click', adobeListener),
        );
      }
    },
    attach(context, settings) {
      // Only attach the event listener once.
      const pending = once('mparticle-flag-tracking', document.body).length;
      if (!pending) {
        return;
      }
      document.addEventListener('ab_blocks:abBlocks', (e) => {
        const flagName = e.detail.flagMeta?.name;
        const flagIndex = e.detail.flagMeta?.index;
        if (flagName && typeof flagIndex !== 'undefined') {
          let attributes = {
            type: 'Navigation',
            name: 'Asynchronous Page Elements',
            attributes: {
              'LD Feature Flag': `${flagName}::${flagIndex}`,
            },
          };
          console.debug(
            '[A/B Blocks] Sending mParticle event to track the fact that we added the A/B tested block to the DOM.',
            { attributes },
          );
          Drupal.mParticle.trackEvent(attributes);
        }
        this.trackLinks(
          context,
          e?.detail?.target,
          flagName,
          flagIndex,
          e.detail.contextMetadata?.block?.label,
          e.detail.contextMetadata?.rootPage?.contentType,
        );
      });
    },
  };
})(document, Drupal, once);
