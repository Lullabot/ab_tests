/**
 * Decision handler for view mode A/B testing.
 *
 * Handles variant rendering by making Ajax requests to re-render entities
 * with different view modes based on A/B test decisions.
 */
class ViewModeDecisionHandler extends BaseDecisionHandler {
  /**
   * @inheritDoc
   */
  async _loadVariant(element, decision) {
    const displayMode = decision.decisionValue;
    const uuid = element.getAttribute('data-ab-tests-instance-id');

    // Validate inputs to prevent security issues
    if (!uuid || !displayMode) {
      throw new Error(
        '[A/B Tests] Missing required parameters for variant loading',
      );
    }

    // Validate UUID format (standard UUID pattern)
    if (
      !/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(
        uuid,
      )
    ) {
      throw new Error('[A/B Tests] Invalid UUID format');
    }

    // Validate display mode (alphanumeric and underscores only)
    if (!/^[a-zA-Z0-9_]+$/.test(displayMode)) {
      throw new Error('[A/B Tests] Invalid display mode format');
    }

    this.debug &&
      console.debug(
        '[A/B Tests]',
        'Requesting node to be rendered via Ajax.',
        uuid,
        displayMode,
      );
    return new Promise((resolve, reject) => {
      Drupal.ajax({
        url: `/ab-tests/render/${encodeURIComponent(uuid)}/${encodeURIComponent(displayMode)}`,
        httpMethod: 'GET',
      })
        .execute()
        .then(response => {
          this.debug &&
            console.debug(
              '[A/B Tests]',
              'The entity was rendered with the new view mode.',
              uuid,
            );
          this.status = 'success';
          return response;
        })
        .then(resolve)
        .catch(error => {
          this.error = true;
          this.status = 'error';
          this.debug &&
            console.debug(
              '[A/B Tests]',
              'There was an error rendering the entity: ',
              JSON.stringify(error),
              uuid,
            );
          reject(error);
        });
    });
  }

  /**
   * @inheritDoc
   */
  _decisionChangesNothing(element, decision) {
    const { defaultDecisionValue } = this.settings;
    return (
      typeof defaultDecisionValue !== 'undefined' &&
      decision.decisionValue === defaultDecisionValue
    );
  }
}
