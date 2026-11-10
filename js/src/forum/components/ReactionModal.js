import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import { call, rarityName, t } from '../util';
import { loadStyles } from '../styles';

/** Pick one of your own stickers to put on a post (or take yours off). */
export default class ReactionModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    loadStyles();
    this.stickers = null;
    this.filter = '';
    call('GET', '/mine')
      .then((data) => (this.stickers = data.stickers))
      .catch(() => (this.stickers = []))
      .finally(() => m.redraw());
  }

  className() {
    return 'StickerReactionModal Modal--small';
  }

  title() {
    return t('react.title');
  }

  content() {
    const post = this.attrs.post;
    const mine = (post.attribute('stickerReactions') || []).find((r) => r.mine);
    if (!this.stickers)
      return (
        <div className="Modal-body">
          <LoadingIndicator />
        </div>
      );

    if (!this.stickers.length) {
      return (
        <div className="Modal-body StickerReact-empty">
          <p>{t('react.none')}</p>
          <Link className="Button Button--primary" href={app.route('stickers')} onclick={() => this.hide()}>
            {t('react.go')}
          </Link>
        </div>
      );
    }

    const q = this.filter.trim().toLowerCase();
    const shown = q ? this.stickers.filter((s) => s.name.toLowerCase().includes(q)) : this.stickers;

    return (
      <div className="Modal-body">
        {this.stickers.length > 12 ? (
          <input
            className="FormControl StickerReact-filter"
            placeholder={t('react.filter')}
            value={this.filter}
            oninput={(e) => (this.filter = e.target.value)}
          />
        ) : null}
        <div className="StickerReact-grid">
          {shown.map((s) => (
            <button
              className={`StickerReact-pick StickerTile--r${s.rarity} ${mine?.id === s.id ? 'is-mine' : ''}`}
              title={`${s.name} · ${rarityName(s.rarity)}`}
              disabled={this.saving}
              onclick={() => this.react(mine?.id === s.id ? null : s.id)}
            >
              <img src={s.small} alt={s.name} />
            </button>
          ))}
        </div>
        {mine ? (
          <Button className="Button Button--link" onclick={() => this.react(null)}>
            {t('react.remove')}
          </Button>
        ) : null}
      </div>
    );
  }

  react(sticker) {
    this.saving = true;
    react(this.attrs.post, sticker)
      .then(() => this.hide())
      .catch(() => {})
      .finally(() => (this.saving = false));
  }
}

export function react(post, sticker) {
  return call('POST', '/react', { post: post.id(), sticker }).then((data) => {
    post.pushAttributes({ stickerReactions: data.reactions.length ? data.reactions : null });
    m.redraw();
  });
}
