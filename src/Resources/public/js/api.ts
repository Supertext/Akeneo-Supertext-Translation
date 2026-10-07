const Routing = require('routing');
const __ = require('oro/translator');

type EntityType = 'product' | 'product_model';

type Links = {signup: string; api_key: string};

type TargetPreview = {translate: number; existing: number; unavailable: number};

type Context = {
  configured: boolean;
  links: Links;
  source: string;
  variant: boolean;
  locales: {code: string; label: string}[];
  preview: {units: number; targets: {[locale: string]: TargetPreview}};
};

type TargetResult = {status: 'translated' | 'nothing' | 'error'; translated: number; existing: number; message: string};

type LanguageSetting = {locale: string; label: string; code: string; default_code: string; politeness: '' | 'more' | 'less'};

type SettingsData = {
  version: string;
  release_url: string;
  api_key_source: '' | 'settings' | 'environment';
  api_key_hint: string;
  environment: 'live' | 'staging' | 'testing' | 'custom';
  api_url: string;
  api_url_from_environment: boolean;
  effective_url: string;
  timeout: number;
  languages: LanguageSetting[];
  links: Links;
};

class ApiError extends Error {
  constructor(message: string, readonly links: Links | null = null) {
    super(message);
  }
}

const request = async <T>(route: string, params: {[key: string]: string}, body?: unknown): Promise<T> => {
  let response: Response;
  try {
    response = await fetch(Routing.generate(route, params), {
      method: body === undefined ? 'GET' : 'POST',
      credentials: 'same-origin',
      headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json', Accept: 'application/json'},
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch (e) {
    throw new ApiError(__('supertext_translation.error.network'));
  }

  const data = await response.json().catch(() => null);
  if (!response.ok) {
    throw new ApiError((data && data.message) || __('supertext_translation.error.http', {status: response.status}), (data && data.links) || null);
  }

  return data as T;
};

const fetchContext = (type: EntityType, id: string, from: string) =>
  request<Context>('supertext_translation_rest_context', from ? {type, id, from} : {type, id});

const translate = (type: EntityType, id: string, from: string, to: string[], overwrite: boolean) =>
  request<{results: {[locale: string]: TargetResult}}>('supertext_translation_rest_translate', {type, id}, {from, to, overwrite});

const fetchSettings = () => request<SettingsData>('supertext_translation_rest_settings_get', {});

const saveSettings = (input: object) => request<SettingsData>('supertext_translation_rest_settings_save', {}, input);

const testConnection = (input: object) =>
  request<{ok: boolean; message: string}>('supertext_translation_rest_settings_test', {}, input);

export {ApiError, fetchContext, translate, fetchSettings, saveSettings, testConnection};
export type {EntityType, Context, TargetResult, SettingsData, LanguageSetting, Links};
