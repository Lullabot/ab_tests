/**
 * Feature flag-based A/B test decider.
 *
 * This decider uses the Feature Flags module to determine which variant
 * to show. The decision is made by resolving the configured feature flag,
 * and the variant's JSON value is used as the decision value.
 */
class FeatureFlagDecider extends BaseDecider {
  /**
   * Constructs a FeatureFlagDecider instance.
   *
   * @param {string} flagId
   *   The feature flag machine name to resolve.
   */
  constructor(flagId) {
    super();
    this.flagId = flagId;
  }

  /**
   * @inheritDoc
   */
  async decide(element) {
    const decisionId = this.generateDecisionId();

    try {
      if (!Drupal.featureFlags) {
        throw new Error('Feature Flags manager not initialized');
      }

      this.getDebug() &&
        console.debug(
          '[A/B Tests - Feature Flag]',
          `Resolving feature flag: ${this.flagId}`,
        );

      const result = await Drupal.featureFlags.resolve(this.flagId);
      const variantValue = result.getValue();

      this.getDebug() &&
        console.debug(
          '[A/B Tests - Feature Flag]',
          `Resolved variant: ${result.getVariantLabel()}`,
          variantValue,
        );

      // For view modes: value should be a string
      // For blocks: value should be an object
      // Decision class will stringify it, so we need to handle both cases
      const decisionValue =
        typeof variantValue === 'object'
          ? JSON.stringify(variantValue)
          : variantValue;

      return new Decision(decisionId, decisionValue, {
        deciderId: 'feature_flag',
        flagId: this.flagId,
        variantUuid: result.getVariantUuid(),
        variantLabel: result.getVariantLabel(),
      });
    } catch (error) {
      console.error(
        '[A/B Tests - Feature Flag]',
        `Error resolving feature flag ${this.flagId}:`,
        error,
      );
      throw error;
    }
  }
}
