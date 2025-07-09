((document, console, Drupal, once) => {
  'use strict';

  function bytesToBase64(bytes) {
    const binString = Array.from(bytes, (x) => String.fromCodePoint(x)).join(
      '',
    );
    return btoa(binString);
  }

  function findBlocksInScope(allBlocks, context) {
    // Only process the blocks we have not processed yet, that appear in this
    // behavior context.
    const domElements =
      once(
        `ab-testable-block`,
        `.block__ab-testable-block[data-ab-blocks-placement-id]`,
        context,
      ) || [];
    const blocks = [];
    for (const block of allBlocks) {
      const { placementId } = block;
      const domElement = domElements.find(
        (element) =>
          element.getAttribute('data-ab-blocks-placement-id') ===
          placementId,
      );
      if (!domElement) {
        continue;
      }
      block.targetHtmlId = domElement.getAttribute('id');
      if (!block.targetHtmlId) {
        // Make a random ID.
        const randomString = bytesToBase64(
          new TextEncoder().encode(`${Math.random()}`),
        ).substring(3, 12);
        block.targetHtmlId = `ab-testable-block--${randomString}`;
        domElement.setAttribute('id', block.targetHtmlId);
      }
      blocks.push(block);
      console.debug('[A/B Blocks] Block detected with an active experiment.', {
        block,
      });
    }
    return blocks;
  }

  /**
   * Adds the block settings from LaunchDarkly to the blocks object.
   *
   * @param {Object} blocks
   *   A structured object with block information.
   *
   * @returns {Promise<Object>}
   *   The enhanced object.
   */
  async function addParsedLaunchDarklySettings(blocks) {
    // For performance reasons, we issue parallel requests to Launch Darkly
    // for all blocks.
    console.debug('[A/B Blocks] Fetching feature flag(s) from LaunchDarkly.', {
      flags: blocks.map(({ featureFlag }) => featureFlag),
    });
    const featureFlagsContents = await Promise.all(
      blocks.map(({ featureFlag }) =>
        Drupal.launchDarkly.getFlag(featureFlag, '', true),
      ),
    );
    console.debug(
      '[A/B Blocks] Feature flag(s) successfully fetched from LaunchDarkly.',
      { featureFlagsContents },
    );
    // Stitch together the block configuration with the data we got back from
    // Launch Darkly.
    for (let i = 0; i < blocks.length; i++) {
      // LaunchDarkly will give us the feature flag contents already parsed,
      // when declaring the feature flag with type JSON.
      const variationDetails = featureFlagsContents[i] || {};
      blocks[i].flagBlockSettings = variationDetails.value || null;
      blocks[i].flagMeta = {
        name: variationDetails.flagName,
        index:
          typeof variationDetails.variationIndex === 'undefined'
            ? null
            : variationDetails.variationIndex,
      };
    }
    return blocks;
  }

  /**
   * Handles errors by showing the original server-rendered block.
   */
  function handleError(
    context,
    targetHtmlId,
    contextMetadata,
    flagBlockSettings = null,
    flagMeta = null,
  ) {
    console.debug(
      '[A/B Blocks] There was an error with the A/B test. Falling back to the CMS configuration.',
      { flagMeta, targetHtmlId },
    );
    const originalBlock = context.querySelector(
      `#${targetHtmlId} .block--original`,
    );
    if (originalBlock) {
      const originalBlockWrapper = context.querySelector(`#${targetHtmlId}`);
      originalBlock.removeAttribute('style');
      const randomString = bytesToBase64(
        new TextEncoder().encode(`${Math.random()}`),
      ).substring(3, 12);
      originalBlock.setAttribute(
        'data-ab-blocks-placement-id',
        randomString,
      );
      const carouselBlock = originalBlockWrapper.querySelector('.swiper');
      if (carouselBlock) {
        carouselBlock.removeAttribute('data-once');
      }
      originalBlockWrapper.outerHTML = originalBlock.outerHTML;
      if (carouselBlock) {
        Drupal.behaviors.swiperCarousels.attach(context);
      }
    }
    // We should signal that the A/B replacement is done.
    const event = new CustomEvent('ab_blocks:abBlocks', {
      detail: {
        target: originalBlock,
        contextMetadata,
        flagBlockSettings,
        flagMeta,
        errorFlag: true,
      },
    });
    document.dispatchEvent(event);
  }

  function dispatchCustomEvent(
    placementId,
    flagBlockSettings,
    flagMeta,
    contextMetadata,
    status,
  ) {
    const selector = `[data-ab-blocks-placement-id="${placementId}"]`;
    const element = document.querySelector(selector);
    const event = new CustomEvent('ab_blocks:abBlocks', {
      detail: {},
    });
    event.detail.target = element;
    event.detail.flagBlockSettings = flagBlockSettings;
    event.detail.flagMeta = flagMeta;
    event.detail.contextMetadata = contextMetadata;
    console.debug(
      '[A/B Blocks] Dispatching the ab_blocks:abBlocks event.',
      { status, flagMeta },
    );
    document.dispatchEvent(event);
  }

  Drupal.behaviors.abBlocks = {
    attach: async (context, settings) => {
      // Each block can appear multiple times in the page. We need to ensure
      // each block placement runs the experiment, even when there are multiple
      // blocks of a type in the page.
      const blocks = findBlocksInScope(
        Object.values(settings.ab_blocks.blocks || {}),
        context,
      );
      if (!blocks.length) {
        // No point in continuing if there are no blocks in the page with A/B
        // tests.
        return;
      }
      const elements = blocks
        .map(({ targetHtmlId }) => document.getElementById(targetHtmlId))
        .map((element) => Drupal.abTests.registerElement(element));
      try {
        await addParsedLaunchDarklySettings(blocks);
      } catch (e) {
        console.debug(
          '[A/B Blocks] There was an error getting the LaunchDarkly data.',
          e,
        );
        blocks.forEach(({ targetHtmlId, contextMetadata }) =>
          handleError(context, targetHtmlId, contextMetadata),
        );
        return;
      }
      // Now issue a cacheable AJAX GET request to render the block using the
      // configuration from Launch Darkly, foreach block.
      blocks.forEach(
        ({
          pluginId,
          placementId,
          encodedContext,
          contextMetadata,
          blockSettings,
          flagBlockSettings,
          flagMeta,
          targetHtmlId,
        }) => {
          // If we could not find the LD settings, abort. Show the fallback
          // block and abort.
          if (flagBlockSettings === null) {
            console.log(
              '[A/B Blocks] Unable to find LaunchDarkly settings after fetching them.',
              { flagMeta, targetHtmlId },
            );
            handleError(context, targetHtmlId, contextMetadata);
            return;
          }

          const combinedSettings = Object.assign(
            {},
            blockSettings,
            flagBlockSettings,
          );
          if (combinedSettings?.hide_block) {
            // This is a small performance optimization, if we need to hide the
            // block, then we can skip the call to get the test group
            // recommendations.
            console.debug('[A/B Blocks] Block successfully hidden.', {
              flagMeta,
            });
            // Manually trigger the event without the AJAX rendering.
            dispatchCustomEvent(
              placementId,
              flagBlockSettings,
              flagMeta,
              contextMetadata,
              status,
            );
            document.getElementById(targetHtmlId).remove();
            return;
          }
          // Each block makes an AJAX request with the configuration from
          // LaunchDarkly.
          const encodedConfig = bytesToBase64(
            new TextEncoder().encode(JSON.stringify(combinedSettings)),
          );
          const abBlockLoader = Drupal.ajax({
            httpMethod: 'GET',
            url: `/ab-blocks/ajax-block/${pluginId}/${placementId}/${encodedConfig}/${encodedContext}`,
            wrapper: targetHtmlId,
          });
          abBlockLoader.commands.triggerCustomEvent = function (
            ajax,
            response,
            status,
          ) {
            console.debug(
              '[A/B Blocks] Block successfully rendered using the configuration from LaunchDarkly.',
              status,
            );
            dispatchCustomEvent(
              response.data,
              flagBlockSettings,
              flagMeta,
              contextMetadata,
              status,
            );
          };
          try {
            console.debug(
              '[A/B Blocks] Rendering block using the configuration from LaunchDarkly.',
              { pluginId, flagMeta },
            );
            abBlockLoader.execute();
          } catch (e) {
            console.debug(
              '[A/B Blocks] There was an error rendering the block using the configuration from LaunchDarkly.',
              { pluginId, flagMeta },
            );
            handleError(
              context,
              targetHtmlId,
              contextMetadata,
              flagBlockSettings,
              flagMeta,
            );
          }
        },
      );
    },
  };
})(document, console, Drupal, once);
