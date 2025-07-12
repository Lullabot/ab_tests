'use strict';

class BlockDecisionHandler extends BaseDecisionHandler {

  /**
   * @inheritDoc
   */
  async _loadVariant(element, decision) {
    const blockMetadata = this._enhanceBlockMetadata(
      element,
      decision,
    );
    const {
      pluginId,
      placementId,
      encodedContext,
      contextMetadata,
      blockSettings,
      variantBlockSettings,
      deciderMeta,
      targetHtmlId,
    } = blockMetadata;
    if (!variantBlockSettings) {
      throw new Error('Unable to find block settings in the decision.');
    }
    const combinedSettings = Object.assign(
      {},
      blockSettings,
      variantBlockSettings,
    );
    if (combinedSettings?.hide_block) {
      // This is a small performance optimization, if we need to hide the
      // block, then we can skip the call to get the test group
      // recommendations.
      this._hideBlock(targetHtmlId, placementId, variantBlockSettings, deciderMeta, contextMetadata);
      return;
    }

    // Each block makes an Ajax request with the configuration from the Decider.
    const encodedConfig = this._bytesToBase64(
      new TextEncoder().encode(JSON.stringify(combinedSettings)),
    );
    const abBlockLoader = Drupal.ajax({
      httpMethod: 'GET',
      url: `/ab-blocks/ajax-block/${pluginId}/${placementId}/${encodedConfig}/${encodedContext}`,
      wrapper: targetHtmlId,
    });
    await new Promise((resolve, reject) => {
      abBlockLoader.execute()
        .then(response => {
          this.debug && console.debug('[A/B Tests]', 'The block was rendered with the new settings.', combinedSettings, pluginId, placementId);
          this.status = 'success';
          return response;
        })
        .then(resolve)
        .catch(error => {
          this.error = true;
          this.status = 'error';
          reject(error);
        });
    });
    console.debug(
      '[A/B Blocks] Block successfully rendered using the configuration from the decider.',
      this.status,
    );
  }

  /**
   * Enhances the block metadata from drupalSettings with the decision.
   *
   * The decision for A/B tests in blocks contains block settings that override
   * the server-stored block settings. This way the block can render
   * differently.
   *
   * @param element
   *   The HTML element for the block under test.
   * @param {Decision} decision
   *   The decision value.
   *
   * @returns {Object}
   *   The block metadata.
   *
   * @private
   */
  _enhanceBlockMetadata(element, decision) {
    const placementId = element.getAttribute('data-ab-blocks-placement-id');
    if (!placementId) {
      throw new Error('[A/B Blocks] Unable to find block metadata for a block without a placement ID.');
    }
    const blockMetadata = this.settings?.blocks?.[placementId];
    if (!blockMetadata) {
      throw new Error(`[A/B Blocks] Unable to find block metadata for a block with placement ID: ${placementId}`);
    }
    // If the HTML element does not have an ID, generate one and store it in the
    // block metadata object.
    blockMetadata.targetHtmlId = element.getAttribute('id');
    if (!blockMetadata.targetHtmlId) {
      // Make a random ID.
      const randomString = this._bytesToBase64(
        new TextEncoder().encode(`${Math.random()}`),
      ).substring(3, 12);
      blockMetadata.targetHtmlId = `ab-testable-block--${randomString}`;
      element.setAttribute('id', blockMetadata.targetHtmlId);
    }
    this.debug && console.debug('[A/B Blocks] Block detected with an active experiment.', element, blockMetadata);

    // IMPORTANT: The variant decider should return JSON stringified data.
    let parsedDecisionValue = {};
    try {
      parsedDecisionValue = JSON.parse(decision.decisionValue);
    }
    catch (e) {
      this.debug && console.error(`[A/B Blocks] Unable to parse the decision value. Variant deciders should return JSON encoded strings. Received:`, decision.decisionValue);
    }
    blockMetadata.variantBlockSettings = parsedDecisionValue;
    blockMetadata.deciderMeta = decision.decisionData;
    blockMetadata.deciderMeta.decisionId = decision.decisionId;

    return blockMetadata;
  }

  /**
   * Hides the block without having to re-render it.
   *
   * @private
   */
  _hideBlock(targetHtmlId, placementId, variantBlockSettings, deciderMeta, contextMetadata) {
    this.debug && console.debug('[A/B Blocks] Block successfully hidden.', {
      flagMeta,
    });
    document.getElementById(targetHtmlId).remove();
  }

  /**
   * Converts JS data to a base64-string.
   *
   * @private
   */
  _bytesToBase64(bytes) {
    const binString = Array.from(bytes, (x) => String.fromCodePoint(x)).join(
      '',
    );
    return btoa(binString);
  }

}
