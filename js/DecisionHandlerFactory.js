'use strict';

/**
 * Factory class for creating decision handler instances based on A/B test features.
 * 
 * This factory creates the appropriate decision handler based on the feature type
 * (e.g., ab_view_modes, ab_blocks) to handle variant rendering.
 */
class DecisionHandlerFactory {

  /**
   * Creates the correct decision handler for each feature.
   *
   * @param {string} feature
   *   The 'ab_tests' feature. For instance, ab_view_modes, ab_blocks, ...
   * @param {Object} settings
   *   The drupalSettings.
   * @param {*[]} args
   *   The arguments to pass to the class constructor.
   * @returns {BaseDecisionHandler}
   *   The appropriate decision handler instance.
   * @throws {Error}
   *   If the feature type is unknown.
   */
  createInstance(feature, settings, ...args) {
    // Extract additional settings in drupalSetting.ab_blocks, ...
    const featureSettings = settings?.features?.[feature] || {};
    switch (feature) {
      case 'ab_view_modes':
        return new ViewModeDecisionHandler(featureSettings, ...args);
      case 'ab_blocks':
        return new BlockDecisionHandler(featureSettings, ...args);
      default:
        throw new Error(`[A/B Tests] Unknown A/B tests feature: ${feature}`);
    }
  }

}
