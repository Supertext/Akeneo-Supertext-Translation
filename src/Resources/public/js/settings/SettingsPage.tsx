import React, {useEffect, useState} from 'react';
import styled from 'styled-components';
import {Breadcrumb, Button, Field, Helper, Link, Locale, SectionTitle, SelectInput, Table, TextInput, getColor} from 'akeneo-design-system';
import {PageContent, PageHeader, PimView, useRoute} from '@akeneo-pim-community/shared';
import {ApiError, LanguageSetting, SettingsData, fetchSettings, saveSettings, testConnection} from '../api';

const __ = require('oro/translator');

const Form = styled.div`
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 760px;
  padding-bottom: 40px;
`;

const Row = styled.div`
  display: flex;
  gap: 10px;
  align-items: center;
`;

const Note = styled.p`
  color: ${getColor('grey', 120)};
  margin: 0;
`;

const environments = ['live', 'staging', 'testing', 'custom'] as const;
const politeness = ['', 'more', 'less'] as const;

const SettingsPage = () => {
  const systemRoute = useRoute('pim_system_index');
  const [settings, setSettings] = useState<SettingsData | null>(null);
  const [apiKey, setApiKey] = useState<string>('');
  const [environment, setEnvironment] = useState<string>('live');
  const [apiUrl, setApiUrl] = useState<string>('');
  const [timeout, setTimeoutValue] = useState<string>('180');
  const [languages, setLanguages] = useState<LanguageSetting[]>([]);
  const [message, setMessage] = useState<{level: 'success' | 'error' | 'info'; text: string} | null>(null);
  const [busy, setBusy] = useState<boolean>(false);

  const load = (data: SettingsData) => {
    setSettings(data);
    setApiKey('');
    setEnvironment(data.environment);
    setApiUrl(data.api_url);
    setTimeoutValue(String(data.timeout));
    setLanguages(data.languages);
  };

  useEffect(() => {
    fetchSettings()
      .then(load)
      .catch((e: ApiError) => setMessage({level: 'error', text: e.message}));
  }, []);

  const input = () => ({
    api_key: apiKey,
    environment,
    api_url: apiUrl,
    timeout: Number(timeout),
    languages: Object.fromEntries(languages.map(language => [language.locale, {code: language.code, politeness: language.politeness}])),
  });

  const save = () => {
    setBusy(true);
    saveSettings(input())
      .then(data => {
        load(data);
        setMessage({level: 'success', text: __('supertext_translation.settings.saved')});
      })
      .catch((e: ApiError) => setMessage({level: 'error', text: e.message}))
      .finally(() => setBusy(false));
  };

  const test = () => {
    setBusy(true);
    testConnection(input())
      .then(result => setMessage({level: result.ok ? 'success' : 'error', text: result.message}))
      .catch((e: ApiError) => setMessage({level: 'error', text: e.message}))
      .finally(() => setBusy(false));
  };

  const updateLanguage = (locale: string, change: Partial<LanguageSetting>) =>
    setLanguages(current => current.map(language => (language.locale === locale ? {...language, ...change} : language)));

  const keyFromEnvironment = settings?.api_key_source === 'environment';

  return (
    <>
      <PageHeader>
        <PageHeader.Breadcrumb>
          <Breadcrumb>
            <Breadcrumb.Step href={`#${systemRoute}`}>{__('pim_menu.tab.system')}</Breadcrumb.Step>
            <Breadcrumb.Step>Supertext</Breadcrumb.Step>
          </Breadcrumb>
        </PageHeader.Breadcrumb>
        <PageHeader.UserActions>
          <PimView viewName="pim-menu-user-navigation" className="AknTitleContainer-userMenuContainer AknTitleContainer-userMenu" />
        </PageHeader.UserActions>
        <PageHeader.Actions>
          <Button level="tertiary" ghost onClick={test} disabled={busy || settings === null} className="supertext-test-button">
            {__('supertext_translation.settings.test')}
          </Button>
          <Button level="primary" onClick={save} disabled={busy || settings === null} className="supertext-save-button">
            {__('pim_common.save')}
          </Button>
        </PageHeader.Actions>
        <PageHeader.Title>{__('supertext_translation.settings.title')}</PageHeader.Title>
      </PageHeader>
      <PageContent>
        <Form className="supertext-settings">
          {message !== null && <Helper level={message.level}>{message.text}</Helper>}
          {settings !== null && (
            <>
              <SectionTitle>
                <SectionTitle.Title>{__('supertext_translation.settings.connection')}</SectionTitle.Title>
              </SectionTitle>
              <Field label={__('supertext_translation.settings.api_key')}>
                <TextInput
                  type="password"
                  value={apiKey}
                  autoComplete="new-password"
                  readOnly={keyFromEnvironment}
                  placeholder={
                    keyFromEnvironment
                      ? __('supertext_translation.settings.key_from_environment')
                      : settings.api_key_hint !== ''
                      ? __('supertext_translation.settings.key_saved', {hint: settings.api_key_hint})
                      : ''
                  }
                  onChange={(value: string) => setApiKey(value)}
                />
                <Helper level="info">
                  {__('supertext_translation.no_account')}{' '}
                  <Link href={settings.links.signup} target="_blank">
                    {__('supertext_translation.create_account')}
                  </Link>
                  {'. '}
                  {__('supertext_translation.generate_key')}{' '}
                  <Link href={settings.links.api_key} target="_blank">
                    {__('supertext_translation.key_page')}
                  </Link>{' '}
                  {__('supertext_translation.admin_role')}{' '}
                  {keyFromEnvironment ? __('supertext_translation.settings.key_environment_help') : __('supertext_translation.settings.key_help')}
                </Helper>
              </Field>
              <Field label={__('supertext_translation.settings.environment')}>
                <SelectInput
                  value={environment}
                  onChange={(value: string) => setEnvironment(value)}
                  clearable={false}
                  readOnly={settings.api_url_from_environment}
                  emptyResultLabel={__('pim_common.no_result')}
                  openLabel={__('pim_common.open')}
                >
                  {environments.map(value => (
                    <SelectInput.Option key={value} value={value}>
                      {__(`supertext_translation.settings.environments.${value}`)}
                    </SelectInput.Option>
                  ))}
                </SelectInput>
                {settings.api_url_from_environment && (
                  <Helper level="info">{__('supertext_translation.settings.url_from_environment', {url: settings.effective_url})}</Helper>
                )}
              </Field>
              {environment === 'custom' && !settings.api_url_from_environment && (
                <Field label={__('supertext_translation.settings.api_url')}>
                  <TextInput value={apiUrl} placeholder="https://api.supertext.com/v1/" onChange={(value: string) => setApiUrl(value)} />
                </Field>
              )}
              <Field label={__('supertext_translation.settings.timeout')}>
                <TextInput value={timeout} onChange={(value: string) => setTimeoutValue(value.replace(/[^0-9]/g, ''))} />
                <Helper level="info">{__('supertext_translation.settings.timeout_help')}</Helper>
              </Field>

              <SectionTitle>
                <SectionTitle.Title>{__('supertext_translation.settings.languages')}</SectionTitle.Title>
              </SectionTitle>
              <Note>{__('supertext_translation.settings.languages_help')}</Note>
              <Table className="supertext-languages">
                <Table.Header>
                  <Table.HeaderCell>{__('supertext_translation.settings.locale')}</Table.HeaderCell>
                  <Table.HeaderCell>{__('supertext_translation.settings.code')}</Table.HeaderCell>
                  <Table.HeaderCell>{__('supertext_translation.settings.politeness')}</Table.HeaderCell>
                </Table.Header>
                <Table.Body>
                  {languages.map(language => (
                    <Table.Row key={language.locale}>
                      <Table.Cell>
                        <Locale code={language.locale} languageLabel={language.label} />
                      </Table.Cell>
                      <Table.Cell>
                        <Row>
                          <TextInput
                            value={language.code}
                            placeholder={language.default_code}
                            onChange={(value: string) => updateLanguage(language.locale, {code: value})}
                          />
                        </Row>
                      </Table.Cell>
                      <Table.Cell>
                        <SelectInput
                          value={language.politeness}
                          onChange={(value: string) => updateLanguage(language.locale, {politeness: value as LanguageSetting['politeness']})}
                          clearable={false}
                          emptyResultLabel={__('pim_common.no_result')}
                          openLabel={__('pim_common.open')}
                        >
                          {politeness.map(value => (
                            <SelectInput.Option key={value || 'default'} value={value}>
                              {__(`supertext_translation.settings.politeness_${value || 'default'}`)}
                            </SelectInput.Option>
                          ))}
                        </SelectInput>
                      </Table.Cell>
                    </Table.Row>
                  ))}
                </Table.Body>
              </Table>
              <Note className="supertext-version">
                {__('supertext_translation.settings.version')}{' '}
                {settings.release_url !== '' ? (
                  <Link href={settings.release_url} target="_blank">
                    {settings.version}
                  </Link>
                ) : (
                  settings.version || __('supertext_translation.settings.version_unknown')
                )}
              </Note>
            </>
          )}
        </Form>
      </PageContent>
    </>
  );
};

export {SettingsPage};
