import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import extractText from 'flarum/common/utils/extractText';
import useStyles from '../styles';
import { call, logIn, rarityName, refreshStatus, status, t, until } from '../util';
import { openPacks } from './Reveal';
import Album from './Album';
import TradeList from './TradeList';
import Board from './Board';

const TABS = ['album', 'trades', 'board'];

/** /stickers: the member's album, their packs, their trades and the top collectors. */
export default class BookPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);
    useStyles();
    app.history.push('stickers', extractText(t('page.title')));
    app.setTitle(extractText(t('page.title')));
    app.setTitleCount(0);

    this.tab = TABS.includes(m.route.param('tab')) ? m.route.param('tab') : app.session.user ? 'album' : 'board';
    this.album = null;
    this.me = null;
    this.load();
    this.onChange = () => this.load();
    window.addEventListener('sticker-book:changed', this.onChange);
    this.clock = setInterval(() => m.redraw(), 60000);
  }

  onremove(vnode) {
    super.onremove(vnode);
    window.removeEventListener('sticker-book:changed', this.onChange);
    clearInterval(this.clock);
  }

  load() {
    const me = app.session.user;
    if (!me) return;
    call('GET', '/album', { user: me.slug() }, true)
      .then((album) => (this.album = album))
      .catch(() => {})
      .finally(() => m.redraw());
    if (status()) {
      call('GET', '/me', undefined, true)
        .then((s) => {
          this.me = s;
          app.forum.pushAttributes({ stickerStatus: { ...status(), ...s } });
        })
        .catch(() => {})
        .finally(() => m.redraw());
    }
  }

  view() {
    return (
      <PageStructure className="StickerBookPage" hero={() => this.hero()} sidebar={() => <IndexSidebar />}>
        <nav className="StickerTabs" role="tablist">
          {TABS.filter((tab) => tab !== 'trades' || (app.session.user && app.forum.attribute('stickerCanTrade'))).map((tab) => (
            <button
              className={`StickerTab ${this.tab === tab ? 'is-active' : ''}`}
              role="tab"
              aria-selected={this.tab === tab ? 'true' : 'false'}
              onclick={() => this.setTab(tab)}
            >
              <Icon name={{ album: 'fas fa-book-open', trades: 'fas fa-right-left', board: 'fas fa-ranking-star' }[tab]} />
              <span>{t(`page.tab.${tab}`)}</span>
            </button>
          ))}
        </nav>
        <div className="StickerBookPage-body">{this.body()}</div>
      </PageStructure>
    );
  }

  setTab(tab) {
    this.tab = tab;
    // Keep the tab in the address (a refresh or a shared link lands on it), without a new history entry.
    const url = app.route('stickers') + (tab === 'album' ? '' : `?tab=${tab}`);
    window.history.replaceState(window.history.state, '', url);
  }

  body() {
    if (this.tab === 'board') return <Board />;
    if (!app.session.user) {
      return (
        <div className="StickerAlbum-empty">
          <Icon name="fas fa-note-sticky" />
          <p>{t('page.guest')}</p>
          <Button className="Button Button--primary" onclick={logIn}>
            {app.translator.trans('core.forum.header.log_in_link')}
          </Button>
        </div>
      );
    }
    if (this.tab === 'trades') return <TradeList />;
    if (!this.album) return <LoadingIndicator display="block" />;
    return <Album album={this.album} own />;
  }

  hero() {
    const album = this.album;
    const s = this.me || status();
    const stats = album?.stats;
    const pct = stats?.total ? Math.round((stats.unique / stats.total) * 100) : 0;

    return (
      <header className="StickerHero">
        <div className="container">
          <div className="StickerHero-main">
            <div className="StickerHero-ring" style={{ '--p': pct }} aria-hidden={stats ? 'false' : 'true'}>
              <span>
                {stats ? (
                  <>
                    <strong>{pct}%</strong>
                    <small>{t('hero.complete')}</small>
                  </>
                ) : (
                  <Icon name="fas fa-note-sticky" />
                )}
              </span>
            </div>
            <div className="StickerHero-text">
              <h1>{t('page.title')}</h1>
              {stats ? (
                <ul className="StickerHero-stats">
                  <li>
                    <strong>
                      {stats.unique}/{stats.total}
                    </strong>{' '}
                    {t('hero.stickers')}
                  </li>
                  <li>
                    <strong>{stats.completed}</strong> {t('hero.sets', { count: stats.completed })}
                  </li>
                  <li>
                    <strong>{stats.opened}</strong> {t('hero.packs', { count: stats.opened })}
                  </li>
                </ul>
              ) : (
                <p>{t('hero.intro')}</p>
              )}
            </div>
          </div>
          {s ? this.shelf(s) : null}
        </div>
      </header>
    );
  }

  shelf(s) {
    const odds = this.me?.odds;
    return (
      <div className="StickerShelf">
        {s.packs ? (
          <button className="StickerShelf-pack is-ready" onclick={openPacks}>
            <span className="StickerShelf-art" aria-hidden="true">
              <Icon name="fas fa-note-sticky" />
              <span className="StickerShelf-badge">{s.packs}</span>
            </span>
            <span className="StickerShelf-text">
              <strong>{s.daily ? t('hero.open_daily') : t('hero.open_pack')}</strong>
              <small>{t('hero.waiting', { count: s.packs })}</small>
            </span>
          </button>
        ) : (
          <div className="StickerShelf-pack">
            <span className="StickerShelf-art is-empty" aria-hidden="true">
              <Icon name="fas fa-hourglass-half" />
            </span>
            <span className="StickerShelf-text">
              <strong>{t('hero.next_in', { time: until(s.nextDaily) })}</strong>
              <small>{t('hero.earn_more')}</small>
            </span>
          </div>
        )}
        {odds ? (
          <details className="StickerOdds">
            <summary>{t('hero.odds')}</summary>
            <ul>
              {Object.entries(odds).map(([name, pct], r) => (
                <li className={`StickerOdds-row StickerPill--r${r}`}>
                  <span>{rarityName(r)}</span>
                  <strong>{pct}%</strong>
                </li>
              ))}
            </ul>
            {this.me.pityLeft ? <p>{t('hero.pity', { count: this.me.pityLeft })}</p> : null}
          </details>
        ) : null}
      </div>
    );
  }
}
