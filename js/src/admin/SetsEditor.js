import app from 'flarum/admin/app';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Switch from 'flarum/common/components/Switch';
import extractText from 'flarum/common/utils/extractText';

const t = (key, params = {}) => app.translator.trans(`ernestdefoe-sticker-book.admin.sets.${key}`, params);
const RARITIES = ['common', 'rare', 'epic', 'legendary'];
const rarity = (r) => app.translator.trans(`ernestdefoe-sticker-book.admin.rarity.${RARITIES[r]}`);

/** Our endpoints answer refusals with a code only; this turns it into its sentence (or Flarum's own, for validation). */
function errorText(error) {
  const first = error?.response?.errors?.[0];
  const key = `ernestdefoe-sticker-book.lib.errors.${first?.code}`;
  if (first?.code && app.translator.trans(key) !== key) return extractText(app.translator.trans(key));
  if (first?.detail) return first.detail;
  return extractText(app.translator.trans('core.lib.error.generic_message'));
}

function api(method, path, body, raw = false) {
  return app.request({
    method,
    url: app.forum.attribute('apiUrl') + '/sticker-book' + path,
    body,
    ...(raw ? { serialize: (data) => data } : {}),
    errorHandler: (error) => {
      app.alerts.show({ type: 'error' }, errorText(error));
      throw error;
    },
  });
}

/**
 * The sets and their stickers. A set is a draft while hidden: in no pack
 * and no album. Sticker art is uploaded here and stored as WebP (art, a
 * small copy and the silhouette members see until they have it).
 */
export default class SetsEditor {
  oninit() {
    this.data = null;
    this.editing = null; // 'set:new', 'set:<id>', 'sticker:<setId>:new', 'sticker:<id>'
    this.form = {};
    this.gift = { username: '', count: 1 };
    this.load();
  }

  load() {
    return api('GET', '/admin')
      .then((data) => (this.data = data))
      .finally(() => m.redraw());
  }

  view() {
    if (!this.data) return <LoadingIndicator />;
    const { sets } = this.data;

    return (
      <div className="Form-group StickerAdmin">
        <h3>{t('title')}</h3>
        <p className="helpText">{t('help')}</p>

        {!sets.length ? (
          <div className="StickerAdmin-set">
            <p>{t('empty')}</p>
            <Button
              className="Button Button--primary"
              icon="fas fa-wand-magic-sparkles"
              loading={this.busy === 'starter'}
              onclick={() => this.starter()}
            >
              {t('add_starter')}
            </Button>
          </div>
        ) : null}

        {sets.map((set, i) => this.set(set, i, sets.length))}

        {this.editing === 'set:new' ? (
          this.setForm(null)
        ) : (
          <div className="ButtonGroup">
            <Button
              className="Button"
              icon="fas fa-plus"
              onclick={() => this.edit('set:new', { name: '', description: '', badgeId: '', hidden: true })}
            >
              {t('add_set')}
            </Button>
            {sets.length ? (
              <Button className="Button" icon="fas fa-wand-magic-sparkles" loading={this.busy === 'starter'} onclick={() => this.starter()}>
                {t('add_starter')}
              </Button>
            ) : null}
          </div>
        )}

        <h3>{t('gift_title')}</h3>
        <p className="helpText">{t('gift_help')}</p>
        <div className="StickerAdmin-form">
          <div className="StickerAdmin-row">
            <input
              className="FormControl"
              placeholder={extractText(t('gift_username'))}
              value={this.gift.username}
              oninput={(e) => (this.gift.username = e.target.value)}
            />
            <input
              className="FormControl"
              type="number"
              min="1"
              max="50"
              value={this.gift.count}
              oninput={(e) => (this.gift.count = e.target.value)}
            />
            <Button
              className="Button"
              icon="fas fa-gift"
              loading={this.busy === 'gift'}
              disabled={!this.gift.username.trim()}
              onclick={() => this.sendGift()}
            >
              {t('gift_send')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  set(set, i, count) {
    const badge = this.data.badges.find((b) => b.id === set.badgeId);
    return (
      <div className={`StickerAdmin-set ${set.hidden ? 'is-hidden' : ''}`} key={set.id}>
        <div className="StickerAdmin-setHead">
          <h4>{set.name}</h4>
          {set.hidden ? <span className="StickerAdmin-tag">{t('hidden')}</span> : null}
          <span className="StickerAdmin-tag">{t('sticker_count', { count: set.stickers.length })}</span>
          {badge ? <span className="StickerAdmin-tag">{t('badge_tag', { name: badge.name })}</span> : null}
          <Button
            className="Button Button--icon"
            icon="fas fa-arrow-up"
            disabled={i === 0}
            aria-label={extractText(t('move_up'))}
            onclick={() => this.move(i, -1)}
          />
          <Button
            className="Button Button--icon"
            icon="fas fa-arrow-down"
            disabled={i === count - 1}
            aria-label={extractText(t('move_down'))}
            onclick={() => this.move(i, 1)}
          />
          <Button
            className="Button"
            icon="fas fa-pen"
            onclick={() =>
              this.edit(`set:${set.id}`, { name: set.name, description: set.description || '', badgeId: set.badgeId || '', hidden: set.hidden })
            }
          >
            {t('edit')}
          </Button>
        </div>
        {set.description ? <p className="helpText">{set.description}</p> : null}
        {this.editing === `set:${set.id}` ? this.setForm(set) : null}

        <div className="StickerAdmin-grid">
          {set.stickers.map((s) => (
            <button
              type="button"
              className={`StickerAdmin-sticker r${s.rarity}`}
              onclick={() => this.edit(`sticker:${s.id}`, { name: s.name, rarity: s.rarity, file: null, preview: s.full })}
            >
              <img src={s.small} alt="" />
              <span className="StickerAdmin-name">{s.name}</span>
              <span className="StickerAdmin-meta">
                {rarity(s.rarity)} · {t('owners', { count: s.owners })}
              </span>
            </button>
          ))}
        </div>
        {set.stickers.map((s) => (this.editing === `sticker:${s.id}` ? this.stickerForm(set, s) : null))}
        {this.editing === `sticker:${set.id}:new` ? (
          this.stickerForm(set, null)
        ) : (
          <Button className="Button" icon="fas fa-plus" onclick={() => this.edit(`sticker:${set.id}:new`, { name: '', rarity: 0, file: null })}>
            {t('add_sticker')}
          </Button>
        )}
      </div>
    );
  }

  setForm(set) {
    const f = this.form;
    return (
      <div className="StickerAdmin-form">
        <div className="StickerAdmin-row">
          <input
            className="FormControl"
            maxlength="100"
            placeholder={extractText(t('name'))}
            value={f.name}
            oninput={(e) => (f.name = e.target.value)}
          />
          {this.data.badges.length ? (
            <select className="FormControl" value={String(f.badgeId || '')} onchange={(e) => (f.badgeId = e.target.value)}>
              <option value="">{t('no_badge')}</option>
              {this.data.badges.map((b) => (
                <option value={String(b.id)}>{t('badge_option', { name: b.name })}</option>
              ))}
            </select>
          ) : null}
        </div>
        <input
          className="FormControl"
          maxlength="255"
          placeholder={extractText(t('description'))}
          value={f.description}
          oninput={(e) => (f.description = e.target.value)}
        />
        {this.data.badges.length ? null : <p className="helpText">{t('badges_help')}</p>}
        <Switch state={!f.hidden} onchange={(on) => (f.hidden = !on)}>
          {t('visible')}
        </Switch>
        <div className="ButtonGroup">
          <Button className="Button Button--primary" loading={this.busy === 'set'} disabled={!f.name.trim()} onclick={() => this.saveSet(set)}>
            {t('save')}
          </Button>
          <Button className="Button" onclick={() => (this.editing = null)}>
            {t('cancel')}
          </Button>
          {set ? (
            <Button className="Button Button--danger" icon="fas fa-trash" onclick={() => this.deleteSet(set)}>
              {t('delete')}
            </Button>
          ) : null}
        </div>
      </div>
    );
  }

  stickerForm(set, sticker) {
    const f = this.form;
    return (
      <div className="StickerAdmin-form">
        <div className="StickerAdmin-row">
          {f.preview ? <img className="StickerAdmin-preview" src={f.preview} alt="" /> : null}
          <input
            className="FormControl"
            maxlength="100"
            placeholder={extractText(t('name'))}
            value={f.name}
            oninput={(e) => (f.name = e.target.value)}
          />
          <select className="FormControl" value={String(f.rarity)} onchange={(e) => (f.rarity = Number(e.target.value))}>
            {RARITIES.map((_, r) => (
              <option value={String(r)}>{rarity(r)}</option>
            ))}
          </select>
        </div>
        <label className="helpText">
          {sticker ? t('replace_art') : t('art')}
          <input
            type="file"
            accept="image/png,image/webp,image/jpeg"
            onchange={(e) => {
              f.file = e.target.files[0] || null;
              if (f.file) f.preview = URL.createObjectURL(f.file);
            }}
          />
        </label>
        <div className="ButtonGroup">
          <Button
            className="Button Button--primary"
            loading={this.busy === 'sticker'}
            disabled={!f.name.trim() || (!sticker && !f.file)}
            onclick={() => this.saveSticker(set, sticker)}
          >
            {t('save')}
          </Button>
          <Button className="Button" onclick={() => (this.editing = null)}>
            {t('cancel')}
          </Button>
          {sticker ? (
            <Button className="Button Button--danger" icon="fas fa-trash" onclick={() => this.deleteSticker(sticker)}>
              {t('delete')}
            </Button>
          ) : null}
        </div>
      </div>
    );
  }

  edit(key, form) {
    this.editing = key;
    this.form = form;
  }

  run(busy, promise) {
    this.busy = busy;
    return promise
      .then(() => {
        this.editing = null;
        return this.load();
      })
      .catch(() => {})
      .finally(() => {
        this.busy = null;
        m.redraw();
      });
  }

  saveSet(set) {
    const f = this.form;
    const body = { name: f.name, description: f.description, badgeId: f.badgeId ? Number(f.badgeId) : null, hidden: !!f.hidden };
    this.run('set', set ? api('PATCH', `/sets/${set.id}`, body) : api('POST', '/sets', body));
  }

  deleteSet(set) {
    if (!confirm(extractText(t('delete_set_confirm', { name: set.name })))) return;
    this.run('set', api('DELETE', `/sets/${set.id}`));
  }

  saveSticker(set, sticker) {
    const f = this.form;
    const data = new FormData();
    data.append('name', f.name);
    data.append('rarity', String(f.rarity));
    if (!sticker) data.append('set', String(set.id));
    if (f.file) data.append('art', f.file);
    this.run('sticker', api('POST', sticker ? `/stickers/${sticker.id}` : '/stickers', data, true));
  }

  deleteSticker(sticker) {
    if (!confirm(extractText(t('delete_sticker_confirm', { name: sticker.name, count: sticker.owners })))) return;
    this.run('sticker', api('DELETE', `/stickers/${sticker.id}`));
  }

  move(i, by) {
    const sets = [...this.data.sets];
    [sets[i], sets[i + by]] = [sets[i + by], sets[i]];
    this.data.sets = sets;
    api('POST', '/order', { sets: sets.map((s) => s.id) }).catch(() => this.load());
  }

  starter() {
    this.run(
      'starter',
      api('POST', '/starter').then((r) => app.alerts.show({ type: 'success' }, t('starter_added', { count: r.added })))
    );
  }

  sendGift() {
    this.busy = 'gift';
    api('POST', '/gift', { username: this.gift.username.trim(), count: Number(this.gift.count) || 1 })
      .then((r) => {
        app.alerts.show({ type: 'success' }, t('gift_sent', { count: r.count, username: r.username }));
        this.gift.username = '';
      })
      .catch(() => {})
      .finally(() => {
        this.busy = null;
        m.redraw();
      });
  }
}
