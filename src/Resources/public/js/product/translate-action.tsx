/**
 * "Translate with Supertext" in the "…" menu of the product and product model edit forms
 * (form_extensions/supertext.yml). Opens TranslateModal; after a translation the form is
 * reloaded so the editor sees the new values.
 */
import React from 'react';
import ReactDOM from 'react-dom';
import {ThemeProvider} from 'styled-components';
import {pimTheme} from 'akeneo-design-system';
import {TranslateModal} from './TranslateModal';
import {EntityType} from '../api';
import BaseView = require('pimui/js/view/base');

const __ = require('oro/translator');
const router = require('pim/router');

type Config = {type: EntityType};

class TranslateAction extends BaseView {
  readonly config: Config;
  private container: HTMLDivElement | null = null;

  constructor(options: {config: Config}) {
    super({...options, ...{className: 'AknDropdown-menuLink supertext-translate-action', tagName: 'button'}});

    this.config = {...this.config, ...options.config};
  }

  public events() {
    return {click: this.open};
  }

  public render(): BaseView {
    this.$el.text(__('supertext_translation.action'));

    return BaseView.prototype.render.apply(this, arguments);
  }

  public remove(): BaseView {
    this.unmount();

    return BaseView.prototype.remove.apply(this, arguments);
  }

  private open() {
    // Unsaved changes would be lost when the form reloads after translating: ask first.
    this.getRoot().trigger('pim_enrich:form:state:confirm', {
      message: __('supertext_translation.unsaved.message'),
      title: __('supertext_translation.unsaved.title'),
      action: () => this.mount(),
    });
  }

  private mount() {
    this.unmount();
    this.container = document.createElement('div');
    document.body.appendChild(this.container);

    ReactDOM.render(
      <ThemeProvider theme={pimTheme}>
        <TranslateModal
          type={this.config.type}
          id={String(this.getFormData().meta.id)}
          onClose={() => this.unmount()}
          onTranslated={() => this.reload()}
        />
      </ThemeProvider>,
      this.container
    );
  }

  private unmount() {
    if (this.container !== null) {
      ReactDOM.unmountComponentAtNode(this.container);
      this.container.remove();
      this.container = null;
    }
  }

  private reload() {
    this.unmount();
    // The editor already agreed to drop unsaved changes: mark the form as clean, then reload it.
    this.getRoot().trigger('pim_enrich:form:entity:post_fetch', this.getFormData());
    router.reloadPage();
  }
}

export = TranslateAction;
