class BlockTrackerBase extends BaseTracker {

  /**
   * Gets the block metadata in the global settings from the placement ID.
   *
   * @param {string} placementId
   *   The placement ID.
   *
   * @return {Object}
   *   The block metadata.
   *
   * @protected
   */
  _getBlockMetadata(placementId) {
    return drupalSettings?.ab_tests?.features?.ab_blocks?.blocks?.[placementId] || {};
  }

}
