'use strict';

class ViewModeDecisionHandler extends BaseDecisionHandler {

  /**
   * @inheritDoc
   */
  async _loadVariant(element, decision) {
    const displayMode = decision.decisionValue;
    const uuid = element.getAttribute('data-ab-tests-entity-root');
    this.debug && console.debug('[A/B Tests]', 'Requesting node to be rendered via Ajax.', uuid, displayMode);
    return new Promise((resolve, reject) => {
      Drupal.ajax({
        url: `/ab-tests/render/${uuid}/${displayMode}`,
        httpMethod: 'GET',
      }).execute()
        .then(response => {
          this.debug && console.debug('[A/B Tests]', 'The entity was rendered with the new view mode.', uuid);
          this.status = 'success';
          return response;
        })
        .then(resolve)
        .catch(error => {
          this.error = true;
          this.status = 'error';
          this.debug && console.debug('[A/B Tests]', 'There was an error rendering the entity: ', JSON.stringify(error), uuid);
          reject(error);
        });
    });
  }

  /**
   * @inheritDoc
   */
  _decisionChangesNothing(element, decision) {
    const defaultDecisionValue = this.settings.defaultDecisionValue;
    return typeof defaultDecisionValue !== 'undefined' && decision.decisionValue === defaultDecisionValue;
  }

}
