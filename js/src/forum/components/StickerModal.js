import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Avatar from 'flarum/common/components/Avatar';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import username from 'flarum/common/helpers/username';
import { call, pushUsers, rarityName, t, user } from '../util';
import { openTrade } from './TradeModal';

export function openSticker(attrs) {
  app.modal.show(StickerModal, attrs);
}

/**
 * One sticker, big. On your own album: offer a spare, or find members who
 * have one to spare. On someone else's: ask them for their spare.
 */
export default class StickerModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    this.spares = null;
  }

  className() {
    return 'StickerModal Modal--small';
  }

  title() {
    return this.attrs.sticker.name;
  }

  content() {
    const { sticker: s, album, own } = this.attrs;
    const me = app.session.user;
    const canTrade = me && app.forum.attribute('stickerCanTrade');
    const owner = album.user;

    return (
      <div className="Modal-body">
        <div className={`StickerModal-art StickerModal-art--r${s.rarity} ${s.count ? '' : 'is-missing'}`}>
          <img src={s.count ? s.full : s.sil} alt="" />
        </div>
        <p className="StickerModal-meta">
          <span className={`StickerPill StickerPill--r${s.rarity}`}>{rarityName(s.rarity)}</span>
          <span className="StickerModal-have">
            {s.count
              ? own
                ? t('sticker.you_have', { count: s.count, spares: s.count - 1 })
                : t('sticker.they_have', { name: owner.displayName, count: s.count })
              : own
                ? t('sticker.you_need')
                : t('sticker.they_need', { name: owner.displayName })}
          </span>
        </p>

        {canTrade ? <div className="StickerModal-actions">{this.actions(s, own, owner)}</div> : null}
      </div>
    );
  }

  actions(s, own, owner) {
    if (own && s.count > 1) {
      return (
        <Button className="Button Button--primary Button--block" icon="fas fa-right-left" onclick={() => this.trade({ give: [[s.id, 1]] })}>
          {t('sticker.offer_spare')}
        </Button>
      );
    }
    if (!own && s.count > 1) {
      return (
        <Button
          className="Button Button--primary Button--block"
          icon="fas fa-hand-holding-heart"
          onclick={() => this.trade({ to: owner, take: [[s.id, 1]] })}
        >
          {t('sticker.ask', { name: owner.displayName })}
        </Button>
      );
    }
    if (own && !s.count) {
      if (this.spares === null) {
        return (
          <Button className="Button Button--block" icon="fas fa-magnifying-glass" loading={this.loading} onclick={() => this.findSpares(s)}>
            {t('sticker.find_spare')}
          </Button>
        );
      }
      if (!this.spares.length) return <p className="StickerModal-none">{t('sticker.no_spares')}</p>;
      return (
        <div className="StickerSpares">
          <h4>{t('sticker.spares_title')}</h4>
          <ul>
            {this.spares.map(([id, n]) => {
              const u = user(id);
              const row = { id, displayName: u?.displayName(), slug: u?.slug() };
              return (
                <li>
                  <span className="StickerSpares-who">
                    <Avatar user={u} /> {username(u)} <span className="StickerSpares-n">{t('sticker.spare_count', { count: n })}</span>
                  </span>
                  <Button className="Button Button--primary" onclick={() => this.trade({ to: row, take: [[s.id, 1]] })}>
                    {t('sticker.propose')}
                  </Button>
                </li>
              );
            })}
          </ul>
        </div>
      );
    }
    return null;
  }

  findSpares(s) {
    this.loading = true;
    call('GET', '/spares', { sticker: s.id })
      .then((data) => {
        pushUsers(data.users);
        this.spares = data.spares;
      })
      .catch(() => {})
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }

  trade(attrs) {
    // Replace this window with the trade form.
    openTrade(attrs);
  }
}
