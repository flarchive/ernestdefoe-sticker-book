import app from 'flarum/forum/app';
import extractText from 'flarum/common/utils/extractText';

export const t = (key, params = {}) => app.translator.trans(`ernestdefoe-sticker-book.forum.${key}`, params);

export const RARITIES = ['common', 'rare', 'epic', 'legendary'];
export const rarityName = (r) => t(`rarity.${RARITIES[r] || 'common'}`);

/** The sentence for an error answer from our endpoints. */
export function errorText(error) {
  const code = error?.response?.errors?.[0]?.code;
  const key = `ernestdefoe-sticker-book.lib.errors.${code}`;
  if (code && app.translator.trans(key) !== key) return extractText(app.translator.trans(key));
  if (error?.status === 0) return extractText(app.translator.trans('core.lib.error.network_message'));
  if (error?.status === 403 || error?.status === 401) return extractText(app.translator.trans('core.lib.error.permission_denied_message'));
  if (error?.status === 404) return extractText(app.translator.trans('core.lib.error.not_found_message'));
  return extractText(app.translator.trans('core.lib.error.generic_message'));
}

export function call(method, path, data, quiet = false) {
  const url = app.forum.attribute('apiUrl') + '/sticker-book' + path;
  return app.request({
    method,
    url: method === 'GET' && data ? url + '?' + new URLSearchParams(data).toString() : url,
    body: method === 'GET' ? undefined : data,
    background: method === 'GET',
    errorHandler: (error) => {
      if (!quiet) app.alerts.show({ type: 'error' }, errorText(error));
      throw error;
    },
  });
}

/** People arrive as compact rows; the store lets Avatar and username() draw them the usual way. */
export function pushUsers(users = []) {
  for (const user of users) {
    if (app.store.getById('users', String(user.id))) continue;
    app.store.pushObject({
      type: 'users',
      id: String(user.id),
      attributes: { username: user.username, displayName: user.displayName, slug: user.slug, avatarUrl: user.avatarUrl },
    });
  }
}

export const user = (id) => (id ? app.store.getById('users', String(id)) : null);

/** The member's pack status, as forum.js knows it (the button in the header). */
export const status = () => app.forum?.attribute('stickerStatus');

export function setStatus(value) {
  if (value) app.forum.pushAttributes({ stickerStatus: { ...status(), ...value } });
}

export function refreshStatus() {
  if (!status()) return Promise.resolve();
  return call('GET', '/me', undefined, true)
    .then((s) => {
      setStatus(s);
      m.redraw();
    })
    .catch(() => {});
}

/** "5h 12m", "12m": until a moment. */
export function until(iso) {
  const s = Math.max(0, Math.floor((Date.parse(iso) - Date.now()) / 1000));
  const h = Math.floor(s / 3600);
  const mm = Math.floor((s % 3600) / 60);
  return h ? `${h}h ${String(mm).padStart(2, '0')}m` : `${Math.max(1, mm)}m`;
}

export const reducedMotion = () => !!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

export function logIn() {
  app.modal.show(() => import('flarum/forum/components/LogInModal'));
}
