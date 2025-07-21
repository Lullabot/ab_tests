class BlockDecisionHandler extends BaseDecisionHandler {
  /**
   * @inheritDoc
   */
  async _loadVariant(element, decision) {
    const blockMetadata = this._enhanceBlockMetadata(element, decision);
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
    const combinedSettings = {
      ...blockSettings,
      ...variantBlockSettings,
    };
    if (combinedSettings?.hide_block) {
      // This is a small performance optimization, if we need to hide the
      // block, then we can skip the call to get the test group
      // recommendations.
      this._hideBlock(
        targetHtmlId,
        placementId,
        variantBlockSettings,
        deciderMeta,
        contextMetadata,
      );
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
      element,
    });
    await new Promise((resolve, reject) => {
      abBlockLoader
        .execute()
        .then(response => {
          this.debug &&
            console.debug(
              '[A/B Tests] The block was rendered with the new settings.',
              combinedSettings,
              pluginId,
              placementId,
            );
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
    this.debug && console.debug(
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
   * @param {HTMLElement} element
   *   The HTML element for the block under test.
   * @param {Decision} decision
   *   The decision value.
   *
   * @return {Object}
   *   The block metadata.
   *
   * @private
   */
  _enhanceBlockMetadata(element, decision) {
    const placementId = element.getAttribute('data-ab-blocks-placement-id');
    if (!placementId) {
      throw new Error(
        '[A/B Blocks] Unable to find block metadata for a block without a placement ID.',
      );
    }
    const blockMetadata = this.settings?.blocks?.[placementId];
    if (!blockMetadata) {
      throw new Error(
        `[A/B Blocks] Unable to find block metadata for a block with placement ID: ${placementId}`,
      );
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
    this.debug &&
      console.debug(
        '[A/B Blocks] Block detected with an active experiment.',
        element,
        blockMetadata,
      );

    // IMPORTANT: The variant decider should return JSON stringified data.
    let parsedDecisionValue = {};
    try {
      parsedDecisionValue = JSON.parse(decision.decisionValue);
    } catch (e) {
      this.debug &&
        console.error(
          `[A/B Blocks] Unable to parse the decision value. Variant deciders should return JSON encoded strings. Received:`,
          decision.decisionValue,
        );
    }
    blockMetadata.variantBlockSettings = parsedDecisionValue;
    blockMetadata.deciderMeta = decision.decisionData;
    blockMetadata.deciderMeta.decisionId = decision.decisionId;

    return blockMetadata;
  }

  /**
   * Hides the block without having to re-render it.
   *
   * @param {string} targetHtmlId
   *   The HTML ID of the target element.
   * @param {string} placementId
   *   The placement ID of the block.
   * @param {Object} variantBlockSettings
   *   The variant block settings.
   * @param {Object} deciderMeta
   *   The decider metadata.
   * @param {Object} contextMetadata
   *   The context metadata.
   *
   * @private
   */
  _hideBlock(
    targetHtmlId,
    placementId,
    variantBlockSettings,
    deciderMeta,
    contextMetadata,
  ) {
    this.debug &&
      console.debug('[A/B Blocks] Block successfully hidden.', {
        targetHtmlId,
        placementId,
        variantBlockSettings,
        deciderMeta,
        contextMetadata,
      });
    document.getElementById(targetHtmlId).remove();
  }

  /**
   * Converts JS data to a base64-string.
   *
   * @param {Uint8Array} bytes
   *   The bytes to convert.
   *
   * @return {string}
   *   The base64-encoded string.
   *
   * @private
   */
  _bytesToBase64(bytes) {
    const binString = Array.from(bytes, x => String.fromCodePoint(x)).join('');
    return btoa(binString);
  }

  /**
   * @inheritDoc
   */
  _decisionChangesNothing(element, decision) {
    // If the combined settings before and after the decision are the same, then
    // the decision didn't change anything.
    // IMPORTANT: The variant decider should return JSON stringified data.
    const placementId = element.getAttribute('data-ab-blocks-placement-id');
    if (!placementId) {
      throw new Error(
        '[A/B Blocks] Unable to find block metadata for a block without a placement ID.',
      );
    }
    const blockMetadata = this.settings?.blocks?.[placementId];
    if (!blockMetadata) {
      throw new Error(
        `[A/B Blocks] Unable to find block metadata for placement ID: ${placementId}`,
      );
    }
    const { blockSettings } = blockMetadata;
    let parsedDecisionValue = {};
    try {
      parsedDecisionValue = JSON.parse(decision.decisionValue);
    } catch (e) {
      this.debug &&
        console.error(
          `[A/B Blocks] Unable to parse the decision value. Variant deciders should return JSON encoded strings. Received:`,
          decision.decisionValue,
        );
    }
    const combinedSettings = {
      ...blockSettings,
      ...parsedDecisionValue,
    };
    // Return true if blockSettings and combinedSettings are the same with a
    // deep comparison.
    return this._deepEqual(blockSettings, combinedSettings);
  }

  /**
   * Performs a deep comparison of two objects.
   *
   * Compares two objects recursively, checking all nested properties and values.
   * The comparison is order-independent for object keys.
   *
   * @param {*} obj1
   *   The first object to compare.
   * @param {*} obj2
   *   The second object to compare.
   *
   * @return {boolean}
   *   True if the objects are deeply equal, false otherwise.
   *
   * @private
   */
  _deepEqual(obj1, obj2) {
    // Check strict equality first (handles primitives, null, undefined, same
    // reference).
    if (obj1 === obj2) {
      return true;
    }

    // Check if either is null or undefined.
    if (obj1 == null || obj2 == null) {
      return obj1 === obj2;
    }

    // Check if types are different.
    if (typeof obj1 !== typeof obj2) {
      return false;
    }

    // Handle arrays.
    if (Array.isArray(obj1)) {
      if (!Array.isArray(obj2) || obj1.length !== obj2.length) {
        return false;
      }
      for (let i = 0; i < obj1.length; i++) {
        if (!this._deepEqual(obj1[i], obj2[i])) {
          return false;
        }
      }
      return true;
    }

    // Handle objects.
    if (typeof obj1 === 'object') {
      const keys1 = Object.keys(obj1);
      const keys2 = Object.keys(obj2);

      // Check if they have the same number of keys.
      if (keys1.length !== keys2.length) {
        return false;
      }

      // Check if all keys and values match.
      return keys1.every(
        key => keys2.includes(key) && this._deepEqual(obj1[key], obj2[key]),
      );
    }

    // For primitives that aren't strictly equal.
    return false;
  }
}
