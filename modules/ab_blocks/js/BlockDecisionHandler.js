class BlockDecisionHandler extends BaseDecisionHandler {
  /**
   * @inheritDoc
   */
  async handleDecision(element, decision) {
    // Pre-enhance block metadata to avoid a nasty race condition.
    this._enhanceBlockMetadata(element, decision);
    try {
      await this._loadVariant(element, decision);
    } catch (error) {
      this._handleError(error, element, decision);
    }

    // Dispatch decision event.
    this._dispatchCustomEvent(element, decision);
  }

  /**
   * @inheritDoc
   */
  async _loadVariant(element, decision) {
    const blockMetadata = this._getBlockMetadata(element);
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
    this.debug &&
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
   * @param {HTMLElement} element
   *   The HTML element for the block under test.
   * @param {Decision} decision
   *   The decision value.
   *
   * @private
   */
  _enhanceBlockMetadata(element, decision) {
    const blockMetadata = this._getBlockMetadata(element);
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
  }

  /**
   * Get the metadata in drupalSettings for this block.
   *
   * @param {HTMLElement} element
   *   The block div.
   *
   * @return {Object}
   *   The block metadata object.
   *
   * @private
   */
  _getBlockMetadata(element) {
    const placementId = element.getAttribute('data-ab-tests-instance-id');
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
    return blockMetadata;
  }

  /**
   * Dispatch the custom event.
   *
   * This will be used by other parts of the application to subscribe to the
   * results of the test.
   *
   * @param {HTMLElement} element
   *   The element under test.
   * @param {Decision} decision
   *   The decision object.
   *
   * @protected
   */
  _dispatchCustomEvent(element, decision) {
    const event = new CustomEvent(this.eventName, {
      detail: {},
      bubbles: true,
    });
    event.detail.element = element;
    const placementId = element.getAttribute('data-ab-tests-instance-id');
    event.detail.decision = decision;
    event.detail.status = this.status;
    event.detail.settings = this.settings?.blocks?.[placementId];
    event.detail.error = this.error;
    this.debug &&
      console.debug(
        '[A/B Tests]',
        'Dispatching event after processing the new content.',
        event,
      );
    document.dispatchEvent(event);
    this.debug && console.debug('[A/B Tests]', 'Event dispatched.', event);
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
}
