import React, {useEffect, useState} from 'react';
import styled from 'styled-components';
import {Button, Checkbox, Field, Helper, Link, Locale, Modal, SelectInput, getColor} from 'akeneo-design-system';
import {ApiError, Context, EntityType, Links, TargetResult, fetchContext, translate} from '../api';

const __ = require('oro/translator');

const Content = styled.div`
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 520px;
  max-height: calc(100vh - 260px);
  overflow-y: auto;
  padding-right: 4px;
`;

const Targets = styled.div`
  display: flex;
  flex-direction: column;
  gap: 10px;
`;

const TargetRow = styled.div`
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
`;

const Hint = styled.span<{$warning?: boolean}>`
  color: ${({$warning}) => ($warning ? getColor('yellow', 140) : getColor('grey', 120))};
  font-size: 13px;
  white-space: nowrap;
`;

const Results = styled.ul`
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
`;

const Result = styled.li<{$failed: boolean}>`
  color: ${({$failed}) => ($failed ? getColor('red', 100) : getColor('grey', 140))};
`;

const Label = styled.div`
  color: ${getColor('grey', 140)};
  font-size: 13px;
  margin-bottom: 8px;
`;

type Props = {
  type: EntityType;
  id: string;
  onClose: () => void;
  onTranslated: () => void;
};

const AccountLinks = ({links}: {links: Links}) => (
  <>
    {__('supertext_translation.no_account')}{' '}
    <Link href={links.signup} target="_blank">
      {__('supertext_translation.create_account')}
    </Link>
    {'. '}
    {__('supertext_translation.generate_key')}{' '}
    <Link href={links.api_key} target="_blank">
      {__('supertext_translation.key_page')}
    </Link>
    {' '}
    {__('supertext_translation.admin_role')}
  </>
);

const languageName = (context: Context, code: string) => context.locales.find(locale => locale.code === code)?.label ?? code;

const TranslateModal = ({type, id, onClose, onTranslated}: Props) => {
  const [context, setContext] = useState<Context | null>(null);
  const [source, setSource] = useState<string>('');
  const [targets, setTargets] = useState<string[]>([]);
  const [overwrite, setOverwrite] = useState<boolean>(false);
  const [busy, setBusy] = useState<boolean>(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [results, setResults] = useState<{[locale: string]: TargetResult} | null>(null);

  useEffect(() => {
    let cancelled = false;
    fetchContext(type, id, source)
      .then(data => {
        if (cancelled) return;
        setContext(data);
        if (source === '') setSource(data.source);
        // Preselect the languages without their own text yet.
        setTargets(
          Object.entries(data.preview.targets)
            .filter(([, preview]) => preview.translate > 0)
            .map(([code]) => code)
        );
        setError(null);
      })
      .catch((e: ApiError) => !cancelled && setError(e));

    return () => {
      cancelled = true;
    };
  }, [type, id, source]);

  const toggle = (code: string, checked: boolean) =>
    setTargets(current => (checked ? [...current, code] : current.filter(target => target !== code)));

  const run = () => {
    setBusy(true);
    setError(null);
    translate(type, id, source, targets, overwrite)
      .then(data => setResults(data.results))
      .catch((e: ApiError) => setError(e))
      .finally(() => setBusy(false));
  };

  const close = () => (results !== null && Object.values(results).some(result => result.status === 'translated') ? onTranslated() : onClose());

  const replaced =
    context === null || !overwrite
      ? []
      : targets.filter(code => (context.preview.targets[code]?.existing ?? 0) > 0).map(code => languageName(context, code));

  const nothingToTranslate = context !== null && context.preview.units === 0;

  return (
    <Modal onClose={close} closeTitle={__('pim_common.close')} className="supertext-translate-modal">
      <Modal.SectionTitle color="brand">Supertext</Modal.SectionTitle>
      <Modal.Title>{__('supertext_translation.modal.title')}</Modal.Title>
      <Content>
        {error !== null && (
          <Helper level="error">
            {error.message} {error.links !== null && <AccountLinks links={error.links} />}
          </Helper>
        )}

        {context !== null && !context.configured && (
          <Helper level="warning">
            {__('supertext_translation.not_configured')} <AccountLinks links={context.links} />
          </Helper>
        )}

        {context === null && error === null && <Hint>{__('supertext_translation.loading')}</Hint>}

        {context !== null && results === null && (
          <>
            <Field label={__('supertext_translation.modal.from')}>
              <SelectInput
                value={source}
                onChange={(value: string) => setSource(value)}
                emptyResultLabel={__('pim_common.no_result')}
                openLabel={__('pim_common.open')}
                clearable={false}
                readOnly={busy}
              >
                {context.locales.map(locale => (
                  <SelectInput.Option key={locale.code} value={locale.code}>
                    {locale.label}
                  </SelectInput.Option>
                ))}
              </SelectInput>
            </Field>

            {nothingToTranslate ? (
              <Helper level="info">
                {__('supertext_translation.modal.nothing', {language: languageName(context, source)})}
                {context.variant && ` ${__('supertext_translation.modal.variant')}`}
              </Helper>
            ) : (
              <div>
                <Label>{__('supertext_translation.modal.to')}</Label>
                <Targets className="supertext-targets">
                  {context.locales
                    .filter(locale => locale.code !== source)
                    .map(locale => {
                      const preview = context.preview.targets[locale.code];
                      const available = preview !== undefined && preview.translate + preview.existing > 0;
                      return (
                        <TargetRow key={locale.code}>
                          <Checkbox
                            checked={targets.includes(locale.code)}
                            readOnly={busy || !available}
                            onChange={(checked: boolean) => toggle(locale.code, checked)}
                          >
                            <Locale code={locale.code} languageLabel={locale.label} />
                          </Checkbox>
                          <Hint $warning={available && preview.existing > 0}>
                            {!available
                              ? __('supertext_translation.modal.not_in_channel')
                              : preview.existing > 0
                              ? __('supertext_translation.modal.existing', {count: preview.existing})
                              : __('supertext_translation.modal.to_translate', {count: preview.translate})}
                          </Hint>
                        </TargetRow>
                      );
                    })}
                </Targets>
              </div>
            )}

            {!nothingToTranslate && (
              <Checkbox checked={overwrite} readOnly={busy} onChange={(checked: boolean) => setOverwrite(checked)}>
                {__('supertext_translation.modal.overwrite')}
              </Checkbox>
            )}

            {replaced.length > 0 && (
              <div className="supertext-overwrite-warning">
                <Helper level="warning">{__('supertext_translation.modal.overwrite_warning', {languages: replaced.join(', ')})}</Helper>
              </div>
            )}
          </>
        )}

        {context !== null && results !== null && (
          <Results className="supertext-results">
            {Object.entries(results).map(([code, result]) => (
              <Result key={code} $failed={result.status === 'error'}>
                <strong>{languageName(context, code)}</strong>:{' '}
                {result.status === 'translated'
                  ? __('supertext_translation.result.translated', {count: result.translated})
                  : result.status === 'nothing'
                  ? result.existing > 0
                    ? __('supertext_translation.result.kept')
                    : __('supertext_translation.result.nothing')
                  : result.message}
                {result.status === 'translated' && result.message !== '' && ` ${result.message}`}
              </Result>
            ))}
          </Results>
        )}
      </Content>
      <Modal.BottomButtons>
        {results === null ? (
          <>
            <Button level="tertiary" onClick={onClose} disabled={busy}>
              {__('pim_common.cancel')}
            </Button>
            <Button
              level="primary"
              onClick={run}
              disabled={busy || context === null || !context.configured || targets.length === 0 || nothingToTranslate}
              className="supertext-translate-button"
            >
              {busy ? __('supertext_translation.modal.translating') : __('supertext_translation.modal.translate')}
            </Button>
          </>
        ) : (
          <Button level="primary" onClick={close} className="supertext-close-button">
            {__('supertext_translation.modal.done')}
          </Button>
        )}
      </Modal.BottomButtons>
    </Modal>
  );
};

export {TranslateModal, AccountLinks};
