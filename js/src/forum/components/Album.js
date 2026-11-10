import app from 'flarum/forum/app';
import Icon from 'flarum/common/components/Icon';
import { rarityName, t } from '../util';
import { openSticker } from './StickerModal';

/**
 * An album, set by set: what the member has (art, and ×N for spares) and
 * what they're missing (a silhouette: the server never sent the art).
 * Filters: everything, missing, spares.
 */
export default class Album {
  oninit() {
    this.filter = 'all';
  }

  view(vnode) {
    const { album, own } = vnode.attrs;
    const filters = ['all', 'missing', 'spares'];
    const keep = (s) => this.filter === 'all' || (this.filter === 'missing' ? !s.count : s.count > 1);

    if (!album.sets.length) {
      return (
        <div className="StickerAlbum-empty">
          <Icon name="fas fa-note-sticky" />
          <p>{t('album.no_sets')}</p>
        </div>
      );
    }

    return (
      <div className="StickerAlbum">
        <div className="StickerAlbum-filters" role="tablist">
          {filters.map((f) => (
            <button
              className={`StickerChip ${this.filter === f ? 'is-active' : ''}`}
              role="tab"
              aria-selected={this.filter === f ? 'true' : 'false'}
              onclick={() => (this.filter = f)}
            >
              {t(`album.filter.${f}`)}
            </button>
          ))}
        </div>

        {/* Sets with nothing to show under a filter are left out before the keyed list is drawn: a null among keyed sections breaks Mithril's redraw. */}
        {album.sets
          .map((set) => ({ set, shown: set.stickers.filter(keep) }))
          .filter(({ shown }) => shown.length || this.filter === 'all')
          .map(({ set, shown }) => {
            const have = set.stickers.filter((s) => s.count).length;
            const pct = Math.round((have / set.stickers.length) * 100);

            return (
              <section className={`StickerSet ${set.completedAt ? 'is-complete' : ''}`} key={set.id}>
                <header className="StickerSet-header">
                  <div className="StickerSet-title">
                    <h3>
                      {set.name}
                      {set.completedAt ? (
                        <span className="StickerSet-done">
                          <Icon name="fas fa-trophy" /> {t('album.complete')}
                        </span>
                      ) : null}
                    </h3>
                    {set.description ? <p>{set.description}</p> : null}
                  </div>
                  <div className="StickerSet-progress" aria-label={t('album.progress_label', { have, total: set.stickers.length })}>
                    <span className="StickerSet-count">
                      {have}
                      <small>/{set.stickers.length}</small>
                    </span>
                    <span className="StickerSet-bar">
                      <span style={{ width: pct + '%' }} />
                    </span>
                  </div>
                </header>
                <div className="StickerGrid">
                  {shown.map((s) => (
                    <StickerTile sticker={s} onclick={() => openSticker({ sticker: s, album, own })} />
                  ))}
                </div>
              </section>
            );
          })}
      </div>
    );
  }
}

export function StickerTile() {
  return {
    view: ({ attrs: { sticker: s, onclick, compact } }) => (
      <button
        className={`StickerTile StickerTile--r${s.rarity} ${s.count ? '' : 'is-missing'} ${compact ? 'StickerTile--compact' : ''}`}
        onclick={onclick}
        title={`${s.name} · ${rarityName(s.rarity)}`}
      >
        <span className="StickerTile-art">
          <img src={s.count ? s.small : s.sil} alt="" loading="lazy" draggable="false" />
          {s.count > 1 ? <span className="StickerTile-count">×{s.count}</span> : null}
        </span>
        {compact ? null : (
          <span className="StickerTile-label">
            <span className="StickerTile-name">{s.name}</span>
            <span className="StickerTile-rarity">{rarityName(s.rarity)}</span>
          </span>
        )}
      </button>
    ),
  };
}

/** The member's own counts as a quick lookup, from their album. */
export function countsOf(album) {
  const out = {};
  for (const set of album?.sets || []) for (const s of set.stickers) out[s.id] = s.count;
  return out;
}

/** Every sticker in an album, by id. */
export function stickersOf(album) {
  const out = {};
  for (const set of album?.sets || []) for (const s of set.stickers) out[s.id] = s;
  return out;
}

export const isOwnAlbum = (album) => app.session.user && album && String(album.user.id) === app.session.user.id();
